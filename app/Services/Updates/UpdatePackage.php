<?php

namespace App\Services\Updates;

use App\Support\AppVersion;
use ZipArchive;

/**
 * A verified update package.
 *
 * Obtaining an instance IS the verification: open() refuses — by throwing —
 * anything that is not exactly what UpdatePackageBuilder produced and signed.
 * Every check runs before a single byte is written anywhere:
 *
 *   1. manifest.sig is a valid Ed25519 signature over manifest.json by the
 *      private key whose public half is configured on this site. Only packages
 *      built on the developer's machine install; a stolen super-admin login
 *      cannot upload arbitrary code.
 *   2. Every path in the manifest is inside UpdatePaths' allowlist.
 *   3. The ZIP contains manifest.json, manifest.sig and files/<listed path>,
 *      and nothing else — no smuggled entries.
 *   4. Every file's SHA-256 matches the signed manifest, so the files are the
 *      ones that were signed.
 *   5. Total uncompressed size is bounded (zip-bomb guard).
 *
 * checkAgainst() then compares the package to the target installation:
 * version must be newer, and composer.lock must match, because an app-only
 * package must not run against different dependencies.
 */
final class UpdatePackage
{
    /** Generous for app code + built assets (~8 MB today); stops a zip bomb. */
    public const MAX_UNCOMPRESSED_BYTES = 150 * 1024 * 1024;

    /** @param array<string, mixed> $manifest */
    private function __construct(
        public readonly string $zipPath,
        public readonly array $manifest,
    ) {
    }

    public static function open(string $zipPath, string $publicKeyB64): self
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new UpdateException('The sodium extension is required to verify update packages.');
        }

        $publicKey = base64_decode($publicKeyB64, true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new UpdateException('No valid update public key is configured on this site (config/updates.php).');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new UpdateException('This file is not a readable ZIP archive.');
        }

        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            $sigB64      = $zip->getFromName('manifest.sig');

            if ($manifestRaw === false || $sigB64 === false) {
                throw new UpdateException('This is not an update package: manifest.json or manifest.sig is missing. (A full deploy ZIP cannot be installed here.)');
            }

            $signature = base64_decode(trim($sigB64), true);
            if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
                || !sodium_crypto_sign_verify_detached($signature, $manifestRaw, $publicKey)) {
                throw new UpdateException('Signature check failed. This package was not signed with the trusted update key, or it has been altered. It will not be installed.');
            }

            $manifest = json_decode($manifestRaw, true);
            self::validateManifestShape($manifest);

            $listed = $manifest['files'];
            $total  = 0;

            // 3. No entry that the signed manifest does not account for.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];

                if (str_ends_with($name, '/')) {
                    continue; // directory entry
                }
                if ($name === 'manifest.json' || $name === 'manifest.sig') {
                    continue;
                }
                if (!str_starts_with($name, 'files/') || !array_key_exists(substr($name, 6), $listed)) {
                    throw new UpdateException("Package contains an unlisted entry '{$name}'. Refusing it.");
                }

                $total += (int) $stat['size'];
                if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new UpdateException('Package expands to more than ' . (self::MAX_UNCOMPRESSED_BYTES >> 20) . ' MB. Refusing it.');
                }
            }

            // 4. Every listed file is present and is the one that was signed.
            foreach ($listed as $path => $expected) {
                $stream = $zip->getStream('files/' . $path);
                if ($stream === false) {
                    throw new UpdateException("Package is missing listed file '{$path}'.");
                }

                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                fclose($stream);

                if (!hash_equals($expected, hash_final($ctx))) {
                    throw new UpdateException("File '{$path}' does not match the signed manifest. The package is corrupt or has been altered.");
                }
            }

            // The VERSION file installed must say what the manifest says, or the
            // site would record one version and report another.
            if (trim((string) $zip->getFromName('files/VERSION')) !== $manifest['version']) {
                throw new UpdateException('The VERSION file inside the package does not match the manifest version.');
            }
        } finally {
            $zip->close();
        }

        return new self($zipPath, $manifest);
    }

    /** Is this package installable on the given installation? */
    public function checkAgainst(string $basePath, string $currentVersion): void
    {
        $version = $this->version();

        if ($currentVersion !== AppVersion::UNKNOWN && version_compare($version, $currentVersion, '<=')) {
            throw new UpdateException("This site is already on {$currentVersion}. Package {$version} is not newer, so there is nothing to install.");
        }

        $lock = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . 'composer.lock';
        if (!is_file($lock)) {
            throw new UpdateException('composer.lock is missing on this site, so it cannot be confirmed that this package matches the installed dependencies. Use a full deploy ZIP instead.');
        }

        if (!hash_equals($this->manifest['composer_lock_sha256'], hash_file('sha256', $lock))) {
            throw new UpdateException("Package {$version} was built against different dependencies (composer.lock) from the ones installed here. An app-only update cannot change dependencies — use a full deploy ZIP for this release.");
        }
    }

    public function version(): string
    {
        return $this->manifest['version'];
    }

    /** @return array<string, string> path => sha256 */
    public function files(): array
    {
        return $this->manifest['files'];
    }

    /** @return string[] */
    public function seeders(): array
    {
        return $this->manifest['seeders'];
    }

    public function migrationCount(): int
    {
        return count(array_filter(
            array_keys($this->files()),
            fn ($p) => str_starts_with($p, 'database/migrations/') && str_ends_with($p, '.php')
        ));
    }

    /** @return array<string, mixed> What the super admin is shown before confirming. */
    public function summary(): array
    {
        return [
            'version'    => $this->version(),
            'built_at'   => $this->manifest['built_at'],
            'commit'     => $this->manifest['commit'],
            'files'      => count($this->files()),
            'migrations' => $this->migrationCount(),
            'seeders'    => $this->seeders(),
        ];
    }

    private static function validateManifestShape(mixed $m): void
    {
        $bad = fn (string $why) => new UpdateException("Manifest is invalid: {$why}.");

        if (!is_array($m)) {
            throw $bad('not a JSON object');
        }
        if (($m['format'] ?? null) !== UpdatePackageBuilder::FORMAT) {
            throw $bad('unsupported package format');
        }
        if (($m['app'] ?? null) !== UpdatePackageBuilder::APP) {
            throw $bad('built for a different application');
        }
        if (!is_string($m['version'] ?? null) || !AppVersion::isValid($m['version'])) {
            throw $bad('version is not MAJOR.MINOR.PATCH');
        }
        if (!is_string($m['composer_lock_sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $m['composer_lock_sha256'])) {
            throw $bad('composer_lock_sha256 missing');
        }
        if (!is_array($m['seeders'] ?? null)) {
            throw $bad('seeders must be a list');
        }
        foreach ($m['seeders'] as $s) {
            if (!is_string($s) || !preg_match('/^[A-Z][A-Za-z0-9]*Seeder$/', $s)) {
                throw $bad('seeder name is not a class name');
            }
        }
        if (!is_array($m['files'] ?? null) || $m['files'] === []) {
            throw $bad('no files');
        }
        foreach ($m['files'] as $path => $sha) {
            if (!is_string($path) || !UpdatePaths::isAllowed($path)) {
                throw new UpdateException("Package tries to write '{$path}', which is outside the paths an update may change. Refusing it.");
            }
            if (!is_string($sha) || !preg_match('/^[a-f0-9]{64}$/', $sha)) {
                throw $bad("checksum for {$path} is malformed");
            }
        }
        if (!isset($m['files']['VERSION'])) {
            throw $bad('package does not carry a VERSION file');
        }
    }
}
