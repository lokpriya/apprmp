<?php

namespace Tests\Unit\Updates;

use App\Services\Updates\UpdateApplier;
use App\Services\Updates\UpdateException;
use RuntimeException;

/**
 * Applying an update, step by step, against a throwaway installation.
 *
 * What must hold:
 *   - the new code and the separate cPanel web root's assets arrive together
 *     (stale JavaScript after an update would break every page);
 *   - .env, storage/, vendor/ are never touched;
 *   - a failure at any step can be rolled back to exactly the previous files;
 *   - steps run in order, once each, and a new upload cannot start while an
 *     update is half-applied.
 */
class UpdateApplierTest extends UpdateTestCase
{
    private array $calls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calls = ['maintenance' => [], 'migrate' => [], 'clear' => 0];
    }

    private function applier(?\Closure $migrator = null): UpdateApplier
    {
        return new UpdateApplier(
            basePath:     $this->base,
            workDir:      $this->work,
            publicKeyB64: $this->public,
            maintenance:  function (bool $on) { $this->calls['maintenance'][] = $on; },
            migrator:     $migrator ?? function (array $seeders) {
                $this->calls['migrate'][] = $seeders;
                return 'migrated';
            },
            cacheClearer: function () { $this->calls['clear']++; return 'cleared'; },
        );
    }

    private function begin(UpdateApplier $applier, array $seeders = []): array
    {
        return $applier->begin($this->buildPackage($seeders), '1.0.0', $this->web, $this->base . '/public', 7, 'Admin');
    }

    private function runAll(UpdateApplier $applier): array
    {
        $state = null;
        foreach (['prepare', 'extract', 'swap', 'migrate', 'finish'] as $step) {
            $state = $applier->run($step);
        }
        return $state;
    }

    public function test_a_full_update_installs_code_and_refreshes_the_separate_web_root(): void
    {
        $applier = $this->applier();
        $state   = $this->begin($applier, ['DemoSeeder']);

        $this->assertSame('verified', $state['status']);
        $this->assertSame('separate', $state['webroot']['mode']);

        $state = $this->runAll($applier);

        $this->assertSame('finished', $state['status']);

        // Code
        $this->assertSame("1.1.0\n", $this->read($this->base . '/VERSION'));
        $this->assertSame('<?php // new', $this->read($this->base . '/app/Changed.php'));
        $this->assertSame('<?php // added', $this->read($this->base . '/app/Added.php'));
        $this->assertSame('<?php // kept', $this->read($this->base . '/app/Kept.php'));
        $this->assertSame('<?php // m2', $this->read($this->base . '/database/migrations/2026_02_02_000000_b.php'));

        // The separate web root: whole build/ replaced, sw.js refreshed.
        $this->assertSame('{"new":true}', $this->read($this->web . '/build/manifest.json'));
        $this->assertSame('new js', $this->read($this->web . '/build/assets/app-NEW222.js'));
        $this->assertNull($this->read($this->web . '/build/assets/app-OLD111.js'), 'old hashed assets leave with the old build/');
        $this->assertSame('new sw', $this->read($this->web . '/sw.js'));
        $this->assertSame('icon', $this->read($this->web . '/pwa-192.png'), 'web root files the package does not carry are left alone');
        $this->assertSame([], glob($this->web . '/build.*') ?: [], 'no build.old / build.new left behind');

        // Never touched
        $this->assertSame('APP_KEY=secret', $this->read($this->base . '/.env'));
        $this->assertSame('user upload', $this->read($this->base . '/storage/app/upload.txt'));
        $this->assertSame('<?php // vendor', $this->read($this->base . '/vendor/autoload.php'));

        // Stale compiled caches removed, others kept
        $this->assertNull($this->read($this->base . '/bootstrap/cache/config.php'));
        $this->assertNull($this->read($this->base . '/bootstrap/cache/routes-v7.php'));
        $this->assertSame('<?php // keep me', $this->read($this->base . '/bootstrap/cache/packages.php'));

        // Side effects, in order
        $this->assertSame([true, false], $this->calls['maintenance'], 'maintenance on at prepare, off at finish');
        $this->assertSame([['DemoSeeder']], $this->calls['migrate']);
        $this->assertSame(1, $this->calls['clear']);

        $this->assertDirectoryDoesNotExist($this->work . '/staging/' . $state['id']);
    }

    public function test_rollback_after_swap_restores_exactly_the_previous_files(): void
    {
        $applier = $this->applier();
        $this->begin($applier);
        $applier->run('prepare');
        $applier->run('extract');
        $applier->run('swap');

        $this->assertSame('<?php // new', $this->read($this->base . '/app/Changed.php'), 'precondition: swapped');

        $state = $applier->run('rollback');

        $this->assertSame('rolled_back', $state['status']);
        $this->assertSame("1.0.0\n", $this->read($this->base . '/VERSION'));
        $this->assertSame('<?php // old', $this->read($this->base . '/app/Changed.php'));
        $this->assertNull($this->read($this->base . '/app/Added.php'), 'files the update created are removed');
        $this->assertNull($this->read($this->base . '/database/migrations/2026_02_02_000000_b.php'));
        $this->assertSame('{"old":true}', $this->read($this->base . '/public/build/manifest.json'));

        $this->assertSame('{"old":true}', $this->read($this->web . '/build/manifest.json'));
        $this->assertSame('old js', $this->read($this->web . '/build/assets/app-OLD111.js'));
        $this->assertSame('old sw', $this->read($this->web . '/sw.js'));

        $this->assertSame([true, false], $this->calls['maintenance'], 'maintenance turned back off');
        $this->assertSame([], $this->calls['migrate'], 'migrations never ran');
    }

    /** The site must never be left half-updated when the database step fails. */
    public function test_a_failing_migration_can_be_rolled_back(): void
    {
        $applier = $this->applier(function () {
            throw new RuntimeException('SQLSTATE[42S01]: Table already exists');
        });
        $this->begin($applier);
        $applier->run('prepare');
        $applier->run('extract');
        $applier->run('swap');

        try {
            $applier->run('migrate');
            $this->fail('migrate should have failed');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('Table already exists', $e->getMessage());
        }

        $state = $applier->state();
        $this->assertSame('failed', $state['status']);
        $this->assertSame('migrate', $state['failed_step']);

        $state = $applier->run('rollback');
        $this->assertSame('rolled_back', $state['status']);
        $this->assertSame('<?php // old', $this->read($this->base . '/app/Changed.php'));
        $this->assertSame('old sw', $this->read($this->web . '/sw.js'));
        $this->assertStringContainsString('migrations that had already run were not reversed', end($state['log'])['message']);
    }

    public function test_steps_cannot_be_skipped_or_repeated(): void
    {
        $applier = $this->applier();
        $this->begin($applier);

        try {
            $applier->run('swap');
            $this->fail('swap ran before prepare/extract');
        } catch (UpdateException $e) {
            $this->assertStringContainsString("needs the update to be 'extracted'", $e->getMessage());
        }

        $applier->run('prepare');
        $this->expectExceptionMessage("needs the update to be 'verified'");
        $applier->run('prepare');
    }

    public function test_a_new_upload_is_refused_while_an_update_is_half_applied(): void
    {
        $applier = $this->applier();
        $this->begin($applier);
        $applier->run('prepare');

        $this->expectExceptionMessage('still in progress');
        $this->begin($applier);
    }

    /** The stored package is re-verified at prepare, not just at upload. */
    public function test_a_package_altered_after_upload_is_caught(): void
    {
        $applier = $this->applier();
        $state   = $this->begin($applier);

        $this->rewriteEntry($this->work . "/packages/{$state['id']}.zip", 'files/app/Changed.php', '<?php // tampered');

        try {
            $applier->run('prepare');
            $this->fail('tampered package was applied');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('does not match the signed manifest', $e->getMessage());
        }

        $this->assertSame('<?php // old', $this->read($this->base . '/app/Changed.php'), 'nothing was changed');
    }

    /** Local development: web root IS public/, so only the base is written. */
    public function test_same_webroot_mode_updates_public_in_place(): void
    {
        $applier = $this->applier();
        $state   = $applier->begin($this->buildPackage(), '1.0.0', $this->base . '/public', $this->base . '/public');

        $this->assertSame('same', $state['webroot']['mode']);
        $this->runAll($applier);

        $this->assertSame('new js', $this->read($this->base . '/public/build/assets/app-NEW222.js'));
        $this->assertSame('new sw', $this->read($this->base . '/public/sw.js'));
        $this->assertSame('old sw', $this->read($this->web . '/sw.js'), 'an unrelated directory is never written');
    }

    /** Refuse rather than ship new code with stale JavaScript. */
    public function test_an_unrecognised_web_root_is_refused(): void
    {
        file_put_contents($this->web . '/index.php', "<?php\ndefine('LARAVEL_ROOT', '/some/other/site');\n");

        $this->expectExceptionMessage("Cannot tell where this site's web root is");
        $this->begin($this->applier());
    }

    public function test_a_verified_package_can_be_discarded(): void
    {
        $applier = $this->applier();
        $this->begin($applier);
        $applier->discard();

        $this->assertNull($applier->state());
        $this->assertSame([], glob($this->work . '/packages/*.zip') ?: []);
    }
}
