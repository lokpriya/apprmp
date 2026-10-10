<?php

namespace Tests\Unit\Updates;

use App\Services\Updates\UpdatePackageBuilder;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Throwaway project trees for exercising the updater.
 *
 *   src/   the developer's project at the NEW version (packages are built here)
 *   base/  an installed site at the OLD version (packages are applied here)
 *   web/   that site's separate cPanel web root, pointing at base/
 *   work/  the updater's staging/snapshot/state directory
 *
 * Nothing here touches the real installation: every path is under a fresh
 * temporary directory that is deleted after each test.
 */
abstract class UpdateTestCase extends TestCase
{
    protected string $root;
    protected string $src;
    protected string $base;
    protected string $web;
    protected string $work;
    protected string $secret;
    protected string $public;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('sodium extension not available');
        }

        $this->root = rtrim(sys_get_temp_dir(), '/\\') . '/apprmp-upd-' . bin2hex(random_bytes(5));
        $this->src  = $this->root . '/src';
        $this->base = $this->root . '/base';
        $this->web  = $this->root . '/web';
        $this->work = $this->root . '/work';

        $pair         = sodium_crypto_sign_keypair();
        $this->secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $this->public = base64_encode(sodium_crypto_sign_publickey($pair));

        $this->writeOldInstall();
        $this->writeNewSource();
    }

    protected function tearDown(): void
    {
        $this->rm($this->root);
        parent::tearDown();
    }

    // ── Fixture trees ────────────────────────────────────────────────────────

    private const COMMON = [
        'composer.lock'                       => '{"content-hash":"same-on-both"}',
        'app/Kept.php'                        => '<?php // kept',
        'routes/web.php'                      => '<?php // routes',
        'resources/views/page.blade.php'      => 'view',
        'bootstrap/app.php'                   => '<?php // bootstrap',
        'config/app.php'                      => '<?php return [];',
        'database/seeders/DemoSeeder.php'     => '<?php // seeder',
        'database/migrations/2026_01_01_000000_a.php' => '<?php // m1',
    ];

    private function writeOldInstall(): void
    {
        $this->put($this->base, self::COMMON + [
            'VERSION'                         => "1.0.0\n",
            'app/Changed.php'                 => '<?php // old',
            'public/build/manifest.json'      => '{"old":true}',
            'public/build/assets/app-OLD111.js' => 'old js',
            'public/sw.js'                    => 'old sw',
            // Must never be touched by an update:
            '.env'                            => 'APP_KEY=secret',
            'storage/app/upload.txt'          => 'user upload',
            'vendor/autoload.php'             => '<?php // vendor',
            'bootstrap/cache/config.php'      => '<?php // cached config',
            'bootstrap/cache/routes-v7.php'   => '<?php // cached routes',
            'bootstrap/cache/packages.php'    => '<?php // keep me',
        ]);

        // cPanel web root: its own copies of build/ and sw.js, and an index.php
        // that points back at the Laravel root.
        $this->put($this->web, [
            'index.php'                   => "<?php\ndefine('LARAVEL_ROOT', '" . str_replace('\\', '/', $this->base) . "');\n",
            'build/manifest.json'         => '{"old":true}',
            'build/assets/app-OLD111.js'  => 'old js',
            'sw.js'                       => 'old sw',
            'pwa-192.png'                 => 'icon',
        ]);
    }

    private function writeNewSource(): void
    {
        $this->put($this->src, self::COMMON + [
            'VERSION'                         => "1.1.0\n",
            'app/Changed.php'                 => '<?php // new',
            'app/Added.php'                   => '<?php // added',
            'public/build/manifest.json'      => '{"new":true}',
            'public/build/assets/app-NEW222.js' => 'new js',
            'public/sw.js'                    => 'new sw',
            'database/migrations/2026_02_02_000000_b.php' => '<?php // m2',
        ]);
    }

    // ── Package helpers ──────────────────────────────────────────────────────

    /** Build a real package from src/ with the test key. */
    protected function buildPackage(array $seeders = [], ?string $secret = null): string
    {
        $out = $this->root . '/out/update-' . bin2hex(random_bytes(3)) . '.zip';
        (new UpdatePackageBuilder($this->src))->build($secret ?? $this->secret, $out, $seeders, 'abc1234');

        return $out;
    }

    /**
     * Hand-craft a package — correctly signed — to prove a VALID signature still
     * cannot make the site write somewhere it must not.
     *
     * @param array<string,string> $files path => content
     */
    protected function craftPackage(array $files, array $overrides = []): string
    {
        $manifest = array_merge([
            'format'               => UpdatePackageBuilder::FORMAT,
            'app'                  => UpdatePackageBuilder::APP,
            'version'              => '1.1.0',
            'built_at'             => '2026-10-10T00:00:00Z',
            'commit'               => 'crafted',
            'composer_lock_sha256' => hash('sha256', self::COMMON['composer.lock']),
            'seeders'              => [],
            'files'                => array_map(fn ($c) => hash('sha256', $c), $files),
        ], $overrides);

        $raw = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $sig = sodium_crypto_sign_detached($raw, base64_decode($this->secret));

        $out = $this->root . '/out/crafted-' . bin2hex(random_bytes(3)) . '.zip';
        if (!is_dir(dirname($out))) {
            mkdir(dirname($out), 0755, true);
        }
        $zip = new ZipArchive();
        $zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', $raw);
        $zip->addFromString('manifest.sig', base64_encode($sig));
        foreach ($files as $path => $content) {
            $zip->addFromString('files/' . $path, $content);
        }
        $zip->close();

        return $out;
    }

    /** Rewrite one entry of an existing ZIP. */
    protected function rewriteEntry(string $zipPath, string $entry, string $content): void
    {
        $zip = new ZipArchive();
        $zip->open($zipPath);
        $zip->deleteName($entry);
        $zip->addFromString($entry, $content);
        $zip->close();
    }

    // ── Filesystem helpers ───────────────────────────────────────────────────

    /** @param array<string,string> $files */
    protected function put(string $dir, array $files): void
    {
        foreach ($files as $path => $content) {
            $full = $dir . '/' . $path;
            if (!is_dir(dirname($full))) {
                mkdir(dirname($full), 0755, true);
            }
            file_put_contents($full, $content);
        }
    }

    protected function read(string $path): ?string
    {
        return is_file($path) ? file_get_contents($path) : null;
    }

    protected function rm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
