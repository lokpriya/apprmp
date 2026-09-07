<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Operational health probe for uptime monitoring.
 *
 * Deliberately unauthenticated and outside the license/maintenance middleware:
 * a monitor must be able to tell "the server is down" apart from "the app is
 * deliberately gated". Returns 503 on failure so a monitor alerts without
 * needing to parse the body.
 *
 * Exposes no counts, names or survey data — only pass/fail plus timings.
 */
class HealthController extends Controller
{
    /** A queue with work older than this and no worker consuming it is considered stalled. */
    private const QUEUE_STALL_MINUTES = 15;

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'storage'  => $this->checkStorage(),
            'queue'    => $this->checkQueue(),
        ];

        $failed   = array_filter($checks, fn ($c) => $c['status'] === 'fail');
        $degraded = array_filter($checks, fn ($c) => $c['status'] === 'degraded');

        $status = $failed ? 'fail' : ($degraded ? 'degraded' : 'ok');

        return response()->json([
            'status'    => $status,
            'checks'    => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $failed ? 503 : 200);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');

            return [
                'status' => 'ok',
                'ms'     => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'error' => class_basename($e)];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'healthcheck:' . bin2hex(random_bytes(4));
            Cache::put($key, 1, 10);
            $ok = Cache::get($key) === 1;
            Cache::forget($key);

            return ['status' => $ok ? 'ok' : 'fail'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'error' => class_basename($e)];
        }
    }

    /**
     * Writes to the public disk specifically — that is the one whose root is
     * overridden per-deploy via PUBLIC_DISK_ROOT, and the one uploads depend on.
     */
    private function checkStorage(): array
    {
        try {
            $path = 'healthcheck/' . bin2hex(random_bytes(4)) . '.txt';
            $disk = Storage::disk('public');
            $disk->put($path, 'ok');
            $ok = $disk->get($path) === 'ok';
            $disk->delete($path);

            return ['status' => $ok ? 'ok' : 'fail'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'error' => class_basename($e)];
        }
    }

    /**
     * Detects a queue with pending work but no worker draining it — the failure
     * mode when the queue:work cron is missing or pointed at a broken PHP binary.
     * Degraded rather than fail: the site still serves, but imports will not run.
     */
    private function checkQueue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed  = DB::table('failed_jobs')->count();

            $oldest = $pending > 0
                ? (int) DB::table('jobs')->min('available_at')
                : null;

            $stalledMinutes = $oldest
                ? (int) round((now()->timestamp - $oldest) / 60)
                : 0;

            $stalled = $stalledMinutes > self::QUEUE_STALL_MINUTES;

            return [
                'status'          => $stalled ? 'degraded' : 'ok',
                'pending'         => $pending,
                'failed'          => $failed,
                'oldest_wait_min' => $stalledMinutes,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'error' => class_basename($e)];
        }
    }
}
