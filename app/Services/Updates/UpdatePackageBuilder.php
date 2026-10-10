<?php

namespace App\Services\Updates;

use App\Support\AppVersion;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * Builds a signed, app-only update package from a project tree.
 *
 * Package layout:
 *
 *   manifest.json   what the package contains and what it requires
 *   manifest.sig    base64 Ed25519 signature over the exact manifest bytes
 *   files/<path>    every file listed in the manifest, nothing else
 *
 * The package is a full copy of the runtime directories (see UpdatePaths), not
 * a diff, so it does not depend on which version a site is currently running.
 * It deliberately excludes vendor/: the manifest records the composer.lock it
 * was built against, and a site whose dependencies differ refuses the package
 * rather than running new code against old libraries.
 */
final class UpdatePackageBuilder
{
    public const FORMAT = 1;
    public const APP    = 'apprmp';

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param  string[]  $seeders  Seeder class names to run after migrating.
     * @return array{path: string, manifest: array, bytes: int}
     */
    public function build(string $secretKeyB64, string $outPath, array $seeders = [], ?string $commit = null): array
    {
        if (!function_exists('sodium_crypto_sign_detached')) {
            throw new UpdateException('The sodium extension is required to sign update packages.');
        }

        $secretKey = base64_decode($secretKeyB64, true);
        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new UpdateException('The signing key is not a valid Ed25519 secret key.');
        }

        $version = AppVersion::current($this->basePath);
        if ($version === AppVersion::UNKNOWN) {
            throw new UpdateException('VERSION file is missing or not in MAJOR.MINOR.PATCH form.');
        }

        $lock = $this->path('composer.lock');
        if (!is_file($lock)) {
            throw new UpdateException('composer.lock is missing; the package must record which dependencies it was built against.');
        }

        foreach ($seeders as $seeder) {
            if (!preg_match('/^[A-Z][A-Za-z0-9]*Seeder$/', $seeder)) {
                throw new UpdateException("'{$seeder}' is not a valid seeder class name.");
            }
            if (!is_file($this->path("database/seeders/{$seeder}.php"))) {
                throw new UpdateException("Seeder {$seeder} does not exist in database/seeders/.");
            }
        }

        $files = [];
        foreach ($this->collect() as $relative) {
            $files[$relative] = hash_file('sha256', $this->path($relative));
        }
        ksort($files);

        if (!isset($files['VERSION'])) {
            throw new UpdateException('VERSION was not collected into the package.');
        }

        $manifest = [
            'format'               => self::FORMAT,
            'app'                  => self::APP,
            'version'              => $version,
            'built_at'             => gmdate('Y-m-d\TH:i:s\Z'),
            'commit'               => $commit,
            'composer_lock_sha256' => hash_file('sha256', $lock),
            'seeders'              => array_values($seeders),
            'files'                => $files,
        ];

        $manifestRaw = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature   = sodium_crypto_sign_detached($manifestRaw, $secretKey);

        $dir = dirname($outPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UpdateException("Cannot create output directory {$dir}.");
        }
        if (is_file($outPath)) {
            unlink($outPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException("Cannot create {$outPath}.");
        }

        $zip->addFromString('manifest.json', $manifestRaw);
        $zip->addFromString('manifest.sig', base64_encode($signature));
        foreach (array_keys($files) as $relative) {
            // Forward-slash entry names regardless of host OS — Compress-Archive
            // wrote backslashes, which broke every by-name lookup in verify-zip.
            $zip->addFile($this->path($relative), 'files/' . $relative);
        }
        $zip->close();

        return ['path' => $outPath, 'manifest' => $manifest, 'bytes' => (int) filesize($outPath)];
    }

    /** @return string[] Relative, forward-slashed paths allowed by UpdatePaths. */
    private function collect(): array
    {
        $out = [];

        foreach (UpdatePaths::FILES as $file) {
            if (is_file($this->path($file))) {
                $out[] = $file;
            }
        }

        foreach (glob($this->path('public') . DIRECTORY_SEPARATOR . 'workbox-*.js') ?: [] as $abs) {
            $out[] = 'public/' . basename($abs);
        }

        foreach (UpdatePaths::DIRS as $dir) {
            $absDir = $this->path(rtrim($dir, '/'));
            if (!is_dir($absDir)) {
                continue;
            }

            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $relative = $dir . str_replace('\\', '/', substr($file->getPathname(), strlen($absDir) + 1));
                $out[] = $relative;
            }
        }

        $out = array_values(array_unique($out));

        // Never let the builder emit something the verifier would reject.
        foreach ($out as $relative) {
            if (!UpdatePaths::isAllowed($relative)) {
                throw new UpdateException("Refusing to package '{$relative}': outside the allowed update paths.");
            }
        }

        return $out;
    }

    private function path(string $relative): string
    {
        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
