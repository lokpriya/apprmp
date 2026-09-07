<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contract tests for /healthz.
 *
 * Uptime monitoring keys off the HTTP status, so the status codes matter as much
 * as the body: 200/503 is the alerting signal. The endpoint also has to work
 * while the app is licence-gated or in maintenance, because "the app is
 * deliberately gated" and "the server is down" must be distinguishable — that
 * distinction is what the 48-hour outage made expensive to establish.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthz_is_reachable_without_authentication(): void
    {
        $this->getJson('/healthz')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_it_reports_every_subsystem(): void
    {
        $response = $this->getJson('/healthz')->assertOk();

        foreach (['database', 'cache', 'storage', 'queue'] as $check) {
            $response->assertJsonPath("checks.{$check}.status", 'ok');
        }

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['status', 'ms'],
                'cache' => ['status'],
                'storage' => ['status'],
                'queue' => ['status', 'pending', 'failed', 'oldest_wait_min'],
            ],
        ]);
    }

    /**
     * The probe must not become an information-disclosure hole: it is public, so
     * it may expose liveness and timings but never counts of households, names,
     * or any survey data.
     */
    public function test_it_leaks_no_survey_data(): void
    {
        $body = $this->getJson('/healthz')->getContent();

        foreach (['household', 'member', 'institution', 'business', 'password', 'DB_', 'secret'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $body);
        }
    }

    /**
     * The real failure this was written for: the queue:work cron is missing or
     * points at a broken PHP binary, so jobs pile up and bulk imports silently
     * never run. Two of the three live sites are in exactly that state.
     */
    public function test_a_stalled_queue_reports_degraded_without_failing_the_probe(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subHour()->timestamp,
            'created_at' => now()->subHour()->timestamp,
        ]);

        $response = $this->getJson('/healthz')
            ->assertOk() // degraded is still a live site: do not page anyone at 200
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.queue.status', 'degraded')
            ->assertJsonPath('checks.queue.pending', 1);

        $this->assertGreaterThanOrEqual(15, $response->json('checks.queue.oldest_wait_min'));
    }

    public function test_a_freshly_queued_job_is_not_treated_as_a_stall(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->getJson('/healthz')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.queue.status', 'ok')
            ->assertJsonPath('checks.queue.pending', 1);
    }

    /** The route is rate limited so the probe cannot itself become a load source. */
    public function test_healthz_is_rate_limited(): void
    {
        $this->getJson('/healthz')->assertHeader('X-RateLimit-Limit', 60);
    }
}
