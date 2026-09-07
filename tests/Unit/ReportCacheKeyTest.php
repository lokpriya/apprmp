<?php

namespace Tests\Unit;

use App\Services\CrossTabService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression tests for public-report cache keys.
 *
 * The bug: each report page cached its main payload under
 * cacheKey('demo', $lgId, $request->only(['age_min','age_max','gender'])) and
 * its per-LG breakdown under cacheKey('demo', $lgId, []). With no filters
 * applied the first call also produces an empty filter string, so both keys
 * were the byte-identical "crosstab.demo.1.v0.all" and the breakdown silently
 * served — and overwrote — the whole page's data. Distinct prefixes are the
 * only thing separating them.
 */
class ReportCacheKeyTest extends TestCase
{
    public function test_same_prefix_and_no_filters_collide(): void
    {
        // Documents the failure mode itself: prefixes must differ, because an
        // empty filter array is indistinguishable from unfiltered request input.
        $page = CrossTabService::cacheKey('demo', 1, ['age_min' => null, 'gender' => '']);
        $breakdown = CrossTabService::cacheKey('demo', 1, []);

        $this->assertSame($page, $breakdown);
    }

    public function test_distinct_prefixes_do_not_collide(): void
    {
        $this->assertNotSame(
            CrossTabService::cacheKey('demo', 1, []),
            CrossTabService::cacheKey('demo_lg', 1, [])
        );
    }

    public function test_filters_change_the_key(): void
    {
        $this->assertNotSame(
            CrossTabService::cacheKey('demo', 1, []),
            CrossTabService::cacheKey('demo', 1, ['gender' => 'female'])
        );
    }

    public function test_local_government_id_changes_the_key(): void
    {
        $this->assertNotSame(
            CrossTabService::cacheKey('demo', 1, []),
            CrossTabService::cacheKey('demo', 2, [])
        );
    }

    /** Bumping analytics_version must invalidate every existing key. */
    public function test_version_bump_changes_the_key(): void
    {
        $before = CrossTabService::cacheKey('demo', 1, []);
        Cache::put('analytics_version', 9);
        $after = CrossTabService::cacheKey('demo', 1, []);

        $this->assertNotSame($before, $after);
    }

    /**
     * The actual guard: within one controller, no cache-key prefix may be used
     * twice. Two calls sharing a prefix in the same file is precisely the shape
     * of the original bug, and it fails silently at runtime — wrong numbers on
     * a public page, no error anywhere.
     *
     * Scoped per file on purpose. ReportExportController deliberately reuses
     * ReportController's prefixes *with the same filter set* so an export
     * reuses the page's already-computed payload and shows identical figures;
     * that sharing is correct and must not be flagged.
     */
    public function test_no_report_controller_reuses_a_cache_key_prefix(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            'app/Http/Controllers/Public/ReportController.php',
            'app/Http/Controllers/Public/BusinessReportController.php',
            'app/Http/Controllers/Public/InstitutionReportController.php',
            'app/Http/Controllers/Public/ReportExportController.php',
        ];

        $duplicates = [];
        $total = 0;

        foreach ($files as $relative) {
            $path = $root.'/'.$relative;
            $this->assertFileExists($path);

            $seen = [];
            foreach (file($path) as $index => $line) {
                if (! preg_match("#cacheKey\(\s*'([a-z0-9_]+)'#i", $line, $m)) {
                    continue;
                }

                $total++;
                $prefix = $m[1];
                $where = $relative.':'.($index + 1);

                if (isset($seen[$prefix])) {
                    $duplicates[] = "'{$prefix}' at {$where} (already used at {$seen[$prefix]})";
                } else {
                    $seen[$prefix] = $where;
                }
            }
        }

        $this->assertGreaterThan(0, $total, 'No cacheKey() calls found — has the helper been renamed?');
        $this->assertSame([], $duplicates, "Duplicate report cache-key prefixes:\n  ".implode("\n  ", $duplicates));
    }
}
