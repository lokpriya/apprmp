<?php

namespace Tests\Unit\Updates;

use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdatePackage;
use App\Services\Updates\UpdatePaths;
use ZipArchive;

/**
 * Verification is the security boundary of the one-file updater.
 *
 * A super admin can reach the update page, so the page must not be a door for
 * code. These tests pin that only a package signed by the developer's key, for
 * a newer version, built against the same dependencies, and writing only
 * inside the allowed paths is ever accepted — and that a valid signature does
 * not stretch any of those rules.
 */
class UpdatePackageTest extends UpdateTestCase
{
    public function test_a_correctly_signed_package_verifies(): void
    {
        $package = UpdatePackage::open($this->buildPackage(['DemoSeeder']), $this->public);

        $this->assertSame('1.1.0', $package->version());
        $this->assertSame(['DemoSeeder'], $package->seeders());
        $this->assertArrayHasKey('app/Added.php', $package->files());
        $this->assertSame(2, $package->migrationCount());

        $package->checkAgainst($this->base, '1.0.0');   // does not throw
    }

    /** A stolen super-admin login must not be enough to install code. */
    public function test_a_package_signed_with_another_key_is_refused(): void
    {
        $other = base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
        $zip   = $this->buildPackage([], $other);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Signature check failed');
        UpdatePackage::open($zip, $this->public);
    }

    public function test_editing_the_manifest_after_signing_is_refused(): void
    {
        $zip = $this->buildPackage();
        $z   = new ZipArchive();
        $z->open($zip);
        $manifest = json_decode($z->getFromName('manifest.json'), true);
        $z->close();

        $manifest['seeders'] = ['DemoSeeder'];   // sneak in a seeder
        $this->rewriteEntry($zip, 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->expectExceptionMessage('Signature check failed');
        UpdatePackage::open($zip, $this->public);
    }

    public function test_altering_a_file_after_signing_is_refused(): void
    {
        $zip = $this->buildPackage();
        $this->rewriteEntry($zip, 'files/app/Changed.php', '<?php system($_GET["c"]);');

        $this->expectExceptionMessage("does not match the signed manifest");
        UpdatePackage::open($zip, $this->public);
    }

    public function test_an_entry_the_manifest_does_not_list_is_refused(): void
    {
        $zip = $this->buildPackage();
        $z   = new ZipArchive();
        $z->open($zip);
        $z->addFromString('files/app/Smuggled.php', '<?php // not in manifest');
        $z->close();

        $this->expectExceptionMessage('unlisted entry');
        UpdatePackage::open($zip, $this->public);
    }

    /** Even correctly signed, a package cannot write .env — that holds APP_KEY. */
    public function test_a_signed_package_cannot_write_env(): void
    {
        $zip = $this->craftPackage(['VERSION' => '1.1.0', '.env' => 'APP_KEY=attacker']);

        $this->expectExceptionMessage('outside the paths an update may change');
        UpdatePackage::open($zip, $this->public);
    }

    public function test_a_signed_package_cannot_traverse_out_of_the_install(): void
    {
        $zip = $this->craftPackage(['VERSION' => '1.1.0', 'app/../../evil.php' => '<?php']);

        $this->expectExceptionMessage('outside the paths an update may change');
        UpdatePackage::open($zip, $this->public);
    }

    public function test_a_signed_package_cannot_write_vendor_or_storage(): void
    {
        foreach (['vendor/autoload.php', 'storage/app/x.php', 'public/index.php', 'artisan'] as $path) {
            $zip = $this->craftPackage(['VERSION' => '1.1.0', $path => 'x']);
            try {
                UpdatePackage::open($zip, $this->public);
                $this->fail("A package writing {$path} was accepted.");
            } catch (UpdateException $e) {
                $this->assertStringContainsString('outside the paths', $e->getMessage(), $path);
            }
        }
    }

    public function test_the_version_file_must_match_the_manifest(): void
    {
        $zip = $this->craftPackage(['VERSION' => '9.9.9', 'app/X.php' => '<?php']);

        $this->expectExceptionMessage('does not match the manifest version');
        UpdatePackage::open($zip, $this->public);
    }

    public function test_a_full_deploy_zip_is_refused_with_a_clear_message(): void
    {
        $zip = $this->root . '/out/deploy.zip';
        if (!is_dir(dirname($zip))) {
            mkdir(dirname($zip), 0755, true);
        }
        $z = new ZipArchive();
        $z->open($zip, ZipArchive::CREATE);
        $z->addFromString('app/Kept.php', '<?php');
        $z->close();

        $this->expectExceptionMessage('A full deploy ZIP cannot be installed here');
        UpdatePackage::open($zip, $this->public);
    }

    public function test_a_package_that_is_not_newer_is_refused(): void
    {
        $package = UpdatePackage::open($this->buildPackage(), $this->public);

        $this->expectExceptionMessage('is not newer');
        $package->checkAgainst($this->base, '1.1.0');
    }

    /** App-only packages must not run against different libraries. */
    public function test_a_package_built_against_different_dependencies_is_refused(): void
    {
        $package = UpdatePackage::open($this->buildPackage(), $this->public);
        file_put_contents($this->base . '/composer.lock', '{"content-hash":"something-else"}');

        $this->expectExceptionMessage('different dependencies');
        $package->checkAgainst($this->base, '1.0.0');
    }

    public function test_no_configured_key_means_nothing_installs(): void
    {
        $this->expectExceptionMessage('No valid update public key');
        UpdatePackage::open($this->buildPackage(), '');
    }

    public function test_the_path_allowlist(): void
    {
        $allowed = [
            'VERSION', 'app/Models/Household.php', 'config/app.php', 'routes/web.php',
            'resources/views/app.blade.php', 'database/migrations/2026_x.php', 'database/seeders/X.php',
            'public/build/assets/app-1.js', 'public/build/manifest.json', 'public/sw.js',
            'public/workbox-7f14a0f4.js', 'bootstrap/app.php',
        ];
        $refused = [
            '.env', 'app/.env', 'config/.env.backup', 'vendor/x.php', 'storage/x', 'public/index.php',
            'public/install.php', 'artisan', 'composer.json', 'bootstrap/cache/config.php',
            'app/../vendor/x.php', '/etc/passwd', 'app\\X.php', 'C:/x.php', 'app//X.php',
            'resources/js/app.js', 'database/sql/dump.sql', 'app/', 'tests/X.php',
        ];

        foreach ($allowed as $p) {
            $this->assertTrue(UpdatePaths::isAllowed($p), "should allow {$p}");
        }
        foreach ($refused as $p) {
            $this->assertFalse(UpdatePaths::isAllowed($p), "should refuse {$p}");
        }
    }
}
