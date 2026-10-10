<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdatePackage;
use App\Services\Updates\UpdatePackageBuilder;
use App\Services\Updates\UpdatePaths;
use App\Support\AppVersion;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Build a signed one-file update package from the current commit.
 *
 *   1. Bump VERSION, commit.
 *   2. npm run build
 *   3. php artisan update:build --seed=SurveyOptionSeeder
 *   4. Upload updates/apprmp-update-<version>.zip on each site:
 *      Settings → प्रणाली अद्यावधिक
 *
 * It refuses to build anything that would not install cleanly, and checks the
 * things that have gone wrong with manual deploys before: a package built from
 * uncommitted code, a forgotten `npm run build`, or a key the sites do not
 * trust. The finished package is verified exactly as a site would verify it
 * before the command reports success.
 */
class UpdateBuild extends Command
{
    protected $signature = 'update:build
        {--seed=* : Seeder class to run after migrating (repeatable), e.g. --seed=SurveyOptionSeeder}
        {--key= : Secret signing key (default: ~/.apprmp/update-signing.key)}
        {--out= : Output path (default: updates/apprmp-update-<version>.zip)}
        {--allow-dirty : Build even with uncommitted changes in shipped files (not for real releases)}';

    protected $description = 'Build a signed one-file update package';

    public function handle(): int
    {
        try {
            $version = AppVersion::current();
            if ($version === AppVersion::UNKNOWN) {
                throw new UpdateException('VERSION is missing or not MAJOR.MINOR.PATCH.');
            }

            $this->checkKeyMatchesConfig($secret = $this->readSecretKey());
            $this->checkCommitted();
            $this->checkFrontendBuilt();

            $commit = $this->git(['rev-parse', '--short', 'HEAD']);
            $out    = $this->option('out') ?: base_path("updates/apprmp-update-{$version}.zip");

            $result = (new UpdatePackageBuilder(base_path()))
                ->build($secret, $out, (array) $this->option('seed'), $commit ?: null);

            // Verify it exactly as a site will, before calling it done.
            $package = UpdatePackage::open($result['path'], (string) config('updates.public_key'));

            $s = $package->summary();
            $this->info("Built {$result['path']}");
            $this->line(sprintf('  version     %s  (commit %s)', $s['version'], $s['commit'] ?? '?'));
            $this->line(sprintf('  size        %.1f MB', $result['bytes'] / 1048576));
            $this->line(sprintf('  files       %d', $s['files']));
            $this->line(sprintf('  migrations  %d shipped (only new ones run)', $s['migrations']));
            $this->line('  seeders     ' . ($s['seeders'] ? implode(', ', $s['seeders']) : 'none'));
            $this->line('  signature   verified with the configured public key');

            return self::SUCCESS;
        } catch (UpdateException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function readSecretKey(): string
    {
        $path = $this->option('key') ?: UpdateKeygen::defaultKeyPath();
        if (!is_file($path)) {
            throw new UpdateException("No signing key at {$path}. Run `php artisan update:keygen --write-config` once on this machine.");
        }

        return trim((string) file_get_contents($path));
    }

    /** A package signed with a key the sites do not trust would be refused everywhere. */
    private function checkKeyMatchesConfig(string $secretB64): void
    {
        $secret = base64_decode($secretB64, true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new UpdateException('The signing key file is not a valid Ed25519 secret key.');
        }

        $derived    = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret));
        $configured = (string) config('updates.public_key');

        if ($configured === '' || !hash_equals($configured, $derived)) {
            throw new UpdateException('The signing key does not match the public key in config/updates.php. Packages signed with it would be refused by every site.');
        }
    }

    /**
     * Refuse uncommitted changes in anything that ships. The package must be a
     * known commit — the 12 September deploy shipped code that did not match
     * what had been committed, and nothing caught it.
     */
    private function checkCommitted(): void
    {
        if ($this->option('allow-dirty')) {
            $this->warn('--allow-dirty: uncommitted changes may be included. Do not install this on a live site.');
            return;
        }

        $status = $this->git(['status', '--porcelain', '--untracked-files=all']);
        $dirty  = [];

        foreach (preg_split('/\R/', (string) $status) as $line) {
            if (strlen($line) < 4) {
                continue;
            }
            $path = trim(substr($line, 3), '"');
            if (str_contains($path, ' -> ')) {
                $path = substr($path, strrpos($path, ' -> ') + 4);
            }
            // Generated by `npm run build`; checked separately for freshness.
            if ($path === 'public/sw.js' || str_starts_with($path, 'public/workbox-') || str_starts_with($path, 'public/build/')) {
                continue;
            }
            if (UpdatePaths::isAllowed($path)) {
                $dirty[] = $path;
            }
        }

        if ($dirty) {
            throw new UpdateException("Uncommitted changes in files that would ship:\n  " . implode("\n  ", array_slice($dirty, 0, 15)) . "\nCommit them first, so the package is a known commit.");
        }
    }

    /** Catch a forgotten `npm run build`: source must not be newer than the build. */
    private function checkFrontendBuilt(): void
    {
        $manifest = public_path('build/manifest.json');
        if (!is_file($manifest)) {
            throw new UpdateException('public/build/manifest.json is missing. Run `npm run build` first.');
        }

        $builtAt = filemtime($manifest);
        foreach (['resources/js', 'resources/css'] as $dir) {
            $abs = base_path($dir);
            if (!is_dir($abs)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getMTime() > $builtAt) {
                    throw new UpdateException("{$dir}/" . str_replace('\\', '/', substr($file->getPathname(), strlen($abs) + 1)) . ' changed after the last `npm run build`. Rebuild the frontend first, or the update would ship old JavaScript.');
                }
            }
        }
    }

    private function git(array $args): string
    {
        $result = Process::path(base_path())->run(array_merge(['git'], $args));
        if (!$result->successful()) {
            throw new UpdateException('git ' . implode(' ', $args) . ' failed: ' . trim($result->errorOutput()));
        }

        return trim($result->output());
    }
}
