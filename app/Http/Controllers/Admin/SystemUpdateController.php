<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LocalGovernment;
use App\Models\SystemUpdate;
use App\Services\Updates\UpdateApplier;
use App\Services\Updates\UpdateException;
use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → प्रणाली अद्यावधिक: install a signed one-file update.
 *
 * Super admin only, behind password re-confirmation, and every package must be
 * signed with the developer's key (see UpdatePackage). The page uploads the
 * package, shows what it contains, then drives UpdateApplier one short step
 * per request so no single request hits the hosting's time limit.
 *
 * Steps after `swap` execute on the newly installed code, so the step URLs and
 * the JSON shape returned here must stay compatible between versions.
 */
class SystemUpdateController extends Controller
{
    public function index(): Response
    {
        $this->guard();

        $publicKey = (string) config('updates.public_key');
        $applier   = $this->applier();

        return Inertia::render('Admin/SystemUpdate', [
            'currentVersion' => AppVersion::current(),
            'keyConfigured'  => $publicKey !== '',
            'keyFingerprint' => $publicKey !== '' ? substr(hash('sha256', (string) base64_decode($publicKey)), 0, 16) : null,
            'state'          => $this->present($applier->state()),
            'uploadLimitKb'  => $this->uploadLimitKb(),
            'history'        => SystemUpdate::latest()->limit(15)->get()->map(fn (SystemUpdate $u) => [
                'id'           => $u->id,
                'version'      => $u->version,
                'from_version' => $u->from_version,
                'status'       => $u->status,
                'commit'       => $u->commit,
                'files_count'  => $u->files_count,
                'by'           => $u->applied_by_name,
                'error'        => $u->error,
                'finished_at'  => $u->finished_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $this->guard();

        $request->validate([
            'package' => ['required', 'file', 'max:' . $this->uploadLimitKb()],
        ], [
            'package.max' => 'Package is larger than this server accepts (' . $this->uploadLimitKb() . ' KB).',
        ]);

        try {
            $state = $this->applier()->begin(
                $request->file('package')->getRealPath(),
                AppVersion::current(),
                $this->webrootCandidate($request),
                public_path(),
                $request->user()->id,
                $request->user()->name,
            );
        } catch (UpdateException $e) {
            Log::warning('System update package refused', ['user' => $request->user()->id, 'reason' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $this->audit($request, 'system_update_verified', $state);

        return response()->json(['ok' => true, 'state' => $this->present($state)]);
    }

    public function step(Request $request, string $step): JsonResponse
    {
        $this->guard();

        // A dropped connection must not abandon a half-written swap.
        ignore_user_abort(true);

        $applier = $this->applier();

        try {
            $state = $applier->run($step);
        } catch (UpdateException $e) {
            $state = $applier->state();
            if ($step === 'rollback' && $state) {
                SystemUpdate::recordFromState($state);
            }
            Log::error('System update step failed', ['step' => $step, 'error' => $e->getMessage()]);

            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
                'state'   => $this->present($state),
            ], 422);
        }

        if (in_array($state['status'], ['finished', 'rolled_back'], true)) {
            SystemUpdate::recordFromState($state);
            $this->audit($request, 'system_update_' . $state['status'], $state);
        }

        return response()->json(['ok' => true, 'state' => $this->present($state)]);
    }

    public function discard(): JsonResponse
    {
        $this->guard();

        try {
            $this->applier()->discard();
        } catch (UpdateException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    // ── Wiring ───────────────────────────────────────────────────────────────

    private function applier(): UpdateApplier
    {
        return new UpdateApplier(
            basePath:     base_path(),
            workDir:      (string) config('updates.work_dir'),
            publicKeyB64: (string) config('updates.public_key'),
            maintenance:  fn (bool $on) => $this->setMaintenance($on),
            migrator:     function (array $seeders): string {
                // In-process: no shell_exec and no guessing the PHP binary, which
                // is what the run-*.php scripts depend on.
                Artisan::call('migrate', ['--force' => true]);
                $out = Artisan::output();

                foreach ($seeders as $seeder) {
                    $class = "Database\\Seeders\\{$seeder}";
                    if (!class_exists($class)) {
                        throw new UpdateException("Seeder {$seeder} was not found after installing the update.");
                    }
                    Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
                    $out .= Artisan::output();
                }

                return $out;
            },
            cacheClearer: function (): string {
                Artisan::call('optimize:clear');
                return Artisan::output();
            },
        );
    }

    /**
     * Public visitors see the maintenance page during an update; signed-in users
     * pass through (CheckMaintenance), so the super admin keeps working. Any
     * maintenance message the municipality had set is put back afterwards.
     */
    private function setMaintenance(bool $on): void
    {
        $id = LocalGovernment::getFirstCached()?->id;
        $lg = $id ? LocalGovernment::find($id) : null;
        if (!$lg) {
            return;
        }

        $keys = ['is_active', 'message', 'message_sub', 'countdown_at'];
        $cfg  = $lg->maintenance_config ?? [];

        if ($on) {
            if (!isset($cfg['_before_update'])) {
                $cfg['_before_update'] = array_intersect_key($cfg, array_flip($keys));
            }
            $cfg['is_active']   = true;
            $cfg['message']     = 'प्रणाली अद्यावधिक हुँदैछ';
            $cfg['message_sub'] = 'केही मिनेटमा फेरि प्रयास गर्नुहोस्। — A system update is in progress; please try again in a few minutes.';
            unset($cfg['countdown_at']);
        } else {
            $before = $cfg['_before_update'] ?? null;
            unset($cfg['_before_update']);
            if (is_array($before)) {
                foreach ($keys as $k) {
                    if (array_key_exists($k, $before)) {
                        $cfg[$k] = $before[$k];
                    } else {
                        unset($cfg[$k]);
                    }
                }
            } else {
                $cfg['is_active'] = false;
            }
        }

        $lg->maintenance_config = $cfg;
        $lg->save();
        LocalGovernment::bustCache($lg->id);
    }

    /** The directory this request was served from — public_html/<site>/ on cPanel. */
    private function webrootCandidate(Request $request): ?string
    {
        $script = (string) $request->server('SCRIPT_FILENAME', '');
        $dir    = $script !== '' ? dirname($script) : null;

        return ($dir && is_file($dir . DIRECTORY_SEPARATOR . 'index.php')) ? $dir : public_path();
    }

    /** Effective limit: the smaller of our own cap and PHP's upload/post limits. */
    private function uploadLimitKb(): int
    {
        $toKb = function (string $v): int {
            $v = trim($v);
            if ($v === '' || $v === '-1' || $v === '0') {
                return PHP_INT_MAX;
            }
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) {
                'g'     => $n * 1024 * 1024,
                'm'     => $n * 1024,
                'k'     => $n,
                default => intdiv($n, 1024),
            };
        };

        return min(
            (int) config('updates.max_upload_kb'),
            $toKb((string) ini_get('upload_max_filesize')),
            $toKb((string) ini_get('post_max_size')),
        );
    }

    private function present(?array $state): ?array
    {
        if (!$state) {
            return null;
        }

        return [
            'id'           => $state['id'] ?? null,
            'version'      => $state['version'] ?? null,
            'from_version' => $state['from_version'] ?? null,
            'status'       => $state['status'] ?? null,
            'summary'      => $state['summary'] ?? null,
            'webroot_mode' => $state['webroot']['mode'] ?? null,
            'failed_step'  => $state['failed_step'] ?? null,
            'error'        => $state['error'] ?? null,
            'log'          => $state['log'] ?? [],
        ];
    }

    private function audit(Request $request, string $event, array $state): void
    {
        try {
            AuditLog::create([
                'user_id'    => $request->user()->id,
                'event_type' => $event,
                'new_values' => [
                    'version'      => $state['version'] ?? null,
                    'from_version' => $state['from_version'] ?? null,
                    'status'       => $state['status'] ?? null,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not write system update audit entry', ['error' => $e->getMessage()]);
        }
    }

    private function guard(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }
}
