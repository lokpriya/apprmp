<?php

namespace App\Services\Updates;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

/**
 * Applies a verified update package in short, separate steps.
 *
 *   verified ─prepare→ prepared ─extract→ extracted ─swap→ swapped
 *            ─migrate→ migrated ─finish→ finished
 *
 *   rollback: from any state before `finished`
 *
 * Why steps: on this hosting a single long web request is killed by CloudLinux
 * limits — Ajirkot's installer ran 95 migrations through one request, failed,
 * and reported success for everything after it. Here each step is its own
 * request, driven by the update page, and each does a bounded amount of work.
 * The order is enforced by persisted state, so a step can neither be skipped
 * nor run twice, and a lock stops two browser tabs racing each other.
 *
 * Why the snapshot: every file the package will overwrite is copied aside
 * before anything changes, and every file it will newly create is recorded, so
 * a failure at any point can put the code back exactly as it was. Database
 * migrations that already ran are NOT reversed — rollback restores files only,
 * and the UI says so.
 *
 * Side effects that need the framework (maintenance mode, migrations, cache
 * clearing) are injected as closures, so this class only ever touches the
 * filesystem paths it is given — which is also what lets it be tested against
 * a throwaway directory instead of the real installation.
 *
 * The step protocol (step names, state keys) must stay compatible across
 * versions: the steps after `swap` run on the NEW code.
 */
final class UpdateApplier
{
    public const STATE_FORMAT = 1;

    private const STEPS = [
        'prepare' => ['from' => 'verified',  'to' => 'prepared'],
        'extract' => ['from' => 'prepared',  'to' => 'extracted'],
        'swap'    => ['from' => 'extracted', 'to' => 'swapped'],
        'migrate' => ['from' => 'swapped',   'to' => 'migrated'],
        'finish'  => ['from' => 'migrated',  'to' => 'finished'],
    ];

    /** States in which an update is mid-flight and blocks a new upload. */
    private const IN_FLIGHT = ['prepared', 'extracted', 'swapped', 'migrated', 'failed'];

    private const ROLLBACKABLE = ['verified', 'prepared', 'extracted', 'swapped', 'migrated', 'failed'];

    /** Compiled caches that must not survive a code change. */
    private const BOOTSTRAP_CACHES = ['config.php', 'routes-v7.php', 'routes.php', 'events.php'];

    private const KEEP_SNAPSHOTS = 3;

    public function __construct(
        private readonly string $basePath,
        private readonly string $workDir,
        private readonly string $publicKeyB64,
        private readonly ?Closure $maintenance = null,   // fn(bool $on): void
        private readonly ?Closure $migrator = null,      // fn(string[] $seeders): string  (log)
        private readonly ?Closure $cacheClearer = null,  // fn(): string                   (log)
    ) {
    }

    // ── Public API ───────────────────────────────────────────────────────────

    public function state(): ?array
    {
        $file = $this->work('state.json');
        if (!is_file($file)) {
            return null;
        }
        $state = json_decode((string) file_get_contents($file), true);

        return is_array($state) ? $state : null;
    }

    /**
     * Verify an uploaded package against this installation and stage it.
     *
     * @param  string       $uploadedZip      Path of the uploaded file.
     * @param  string|null  $webrootCandidate Directory the current request was served from.
     */
    public function begin(
        string $uploadedZip,
        string $currentVersion,
        ?string $webrootCandidate,
        string $publicPath,
        ?int $userId = null,
        ?string $userName = null,
    ): array {
        return $this->locked(function () use ($uploadedZip, $currentVersion, $webrootCandidate, $publicPath, $userId, $userName) {
            $existing = $this->state();
            if ($existing && in_array($existing['status'], self::IN_FLIGHT, true)) {
                throw new UpdateException("Update {$existing['version']} is still in progress ({$existing['status']}). Finish it or roll it back before uploading another.");
            }
            if ($existing && $existing['status'] === 'verified') {
                $this->deletePackageFile($existing['id']);
            }

            $package = UpdatePackage::open($uploadedZip, $this->publicKeyB64);
            $package->checkAgainst($this->basePath, $currentVersion);

            $webroot = self::resolveWebroot($webrootCandidate, $this->basePath, $publicPath);

            $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
            $this->ensureDir($this->work('packages'));
            if (!copy($uploadedZip, $this->work("packages/{$id}.zip"))) {
                throw new UpdateException('Could not store the uploaded package on the server.');
            }

            $state = [
                'format'       => self::STATE_FORMAT,
                'id'           => $id,
                'version'      => $package->version(),
                'from_version' => $currentVersion,
                'summary'      => $package->summary(),
                'status'       => 'verified',
                'webroot'      => $webroot,
                'user_id'      => $userId,
                'user_name'    => $userName,
                'created_at'   => gmdate('c'),
                'error'        => null,
                'failed_step'  => null,
                'log'          => [],
            ];
            $this->log($state, 'verify', "Package {$package->version()} verified: signature, checksums and dependencies OK.");
            $this->saveState($state);

            return $state;
        });
    }

    /** Throw away a verified package that has not started applying. */
    public function discard(): void
    {
        $this->locked(function () {
            $state = $this->state();
            if (!$state) {
                return;
            }
            if (!in_array($state['status'], ['verified', 'finished', 'rolled_back'], true)) {
                throw new UpdateException("Cannot discard an update that is {$state['status']}. Roll it back instead.");
            }
            $this->deletePackageFile($state['id']);
            $this->deleteIfExists($this->work('state.json'));
        });
    }

    /** Run one step: prepare | extract | swap | migrate | finish | rollback. */
    public function run(string $step): array
    {
        return $this->locked(function () use ($step) {
            $state = $this->state();
            if (!$state) {
                throw new UpdateException('There is no update in progress.');
            }

            if ($step === 'rollback') {
                return $this->rollback($state);
            }

            if (!isset(self::STEPS[$step])) {
                throw new UpdateException("Unknown update step '{$step}'.");
            }

            ['from' => $from, 'to' => $to] = self::STEPS[$step];
            if ($state['status'] !== $from) {
                throw new UpdateException("Step '{$step}' needs the update to be '{$from}', but it is '{$state['status']}'.");
            }

            try {
                @set_time_limit(300);
                $this->{'step' . ucfirst($step)}($state);
                $state['status'] = $to;
                $this->saveState($state);

                return $state;
            } catch (Throwable $e) {
                $state['status']      = 'failed';
                $state['failed_step'] = $step;
                $state['error']       = $e->getMessage();
                $this->log($state, $step, 'FAILED: ' . $e->getMessage());
                $this->saveState($state);

                throw new UpdateException("Step '{$step}' failed: " . $e->getMessage(), 0, $e);
            }
        });
    }

    /**
     * Where the browser-facing files live.
     *
     * 'same'     — the web root IS Laravel's public/ (local dev, a plain server).
     * 'separate' — cPanel: public_html/<site>/ holds an index.php that points at
     *              the Laravel root via LARAVEL_ROOT, plus its own copies of
     *              build/ and sw.js which must be refreshed on every update.
     *
     * Refuses rather than guesses: updating code without refreshing the web
     * root's assets leaves the site requesting JavaScript files that do not
     * exist.
     *
     * @return array{mode: 'same'|'separate', path: ?string}
     */
    public static function resolveWebroot(?string $candidate, string $basePath, string $publicPath): array
    {
        $public = realpath($publicPath) ?: null;
        $cand   = $candidate ? (realpath($candidate) ?: null) : null;

        if (!$cand || ($public && self::samePath($cand, $public))) {
            return ['mode' => 'same', 'path' => null];
        }

        $index = $cand . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($index)
            && preg_match("/define\(\s*'LARAVEL_ROOT'\s*,\s*'([^']+)'/", (string) file_get_contents($index), $m)
            && ($root = realpath($m[1])) && ($base = realpath($basePath))
            && self::samePath($root, $base)) {
            return ['mode' => 'separate', 'path' => $cand];
        }

        throw new UpdateException("Cannot tell where this site's web root is (request served from {$cand}). Updating code without refreshing the web root's JavaScript would break the site, so the update was refused.");
    }

    // ── Steps ────────────────────────────────────────────────────────────────

    private function stepPrepare(array &$state): void
    {
        // Re-verify: the stored file could have been altered since upload.
        $package = UpdatePackage::open($this->packagePath($state), $this->publicKeyB64);
        if ($package->version() !== $state['version']) {
            throw new UpdateException('Stored package no longer matches the one that was verified.');
        }

        $this->maintenance?->__invoke(true);
        $this->log($state, 'prepare', 'Maintenance mode on — the public sees the maintenance page; signed-in users continue.');

        $snap = $this->snapshotDir($state);
        $this->rmTree($snap);
        $this->ensureDir($snap);

        $newFiles = [];
        $saved    = 0;
        foreach (array_keys($package->files()) as $path) {
            $live = $this->base($path);
            if (is_file($live)) {
                $this->copyFile($live, $snap . '/base/' . $path);
                $saved++;
            } else {
                $newFiles[] = $path;
            }
        }

        $webNew = [];
        if ($state['webroot']['mode'] === 'separate') {
            foreach (array_keys($package->files()) as $path) {
                if (!UpdatePaths::isWebrootSingleFile($path)) {
                    continue;
                }
                $name = basename($path);
                $live = $state['webroot']['path'] . DIRECTORY_SEPARATOR . $name;
                if (is_file($live)) {
                    $this->copyFile($live, $snap . '/webroot/' . $name);
                } else {
                    $webNew[] = $name;
                }
            }
        }

        file_put_contents($snap . '/new-files.json', json_encode(['base' => $newFiles, 'webroot' => $webNew]));
        $this->log($state, 'prepare', "Snapshot taken: {$saved} existing files saved, " . count($newFiles) . ' new files recorded.');
    }

    private function stepExtract(array &$state): void
    {
        $package = UpdatePackage::open($this->packagePath($state), $this->publicKeyB64);
        $staging = $this->stagingDir($state);
        $this->rmTree($staging);

        $zip = new ZipArchive();
        if ($zip->open($this->packagePath($state), ZipArchive::RDONLY) !== true) {
            throw new UpdateException('Cannot reopen the stored package.');
        }

        try {
            foreach ($package->files() as $path => $sha) {
                // Never extractTo(): every write goes through an allowlisted,
                // manifest-listed path, so a crafted entry name cannot escape.
                $target = $staging . '/' . $path;
                $this->ensureDir(dirname($target));

                $in  = $zip->getStream('files/' . $path);
                $out = fopen($target, 'wb');
                if ($in === false || $out === false) {
                    throw new UpdateException("Cannot extract {$path}.");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);

                if (!hash_equals($sha, hash_file('sha256', $target))) {
                    throw new UpdateException("Extracted {$path} does not match its checksum.");
                }
            }
        } finally {
            $zip->close();
        }

        $this->log($state, 'extract', count($package->files()) . ' files extracted to staging and re-checked.');
    }

    private function stepSwap(array &$state): void
    {
        $staging = $this->stagingDir($state);
        $package = UpdatePackage::open($this->packagePath($state), $this->publicKeyB64);

        $count = 0;
        foreach (array_keys($package->files()) as $path) {
            $this->replaceFile($staging . '/' . $path, $this->base($path), $state['id']);
            $count++;
        }
        $this->log($state, 'swap', "{$count} files installed into the application.");

        if ($state['webroot']['mode'] === 'separate') {
            $this->swapWebroot($state, $package);
        }

        $this->dropCompiledCaches();
        $this->log($state, 'swap', 'Compiled config and route caches removed; opcode cache reset.');
    }

    private function stepMigrate(array &$state): void
    {
        if (!$this->migrator) {
            $this->log($state, 'migrate', 'No migrator configured — skipped.');
            return;
        }

        $output = (string) ($this->migrator)($state['summary']['seeders'] ?? []);
        $this->log($state, 'migrate', trim($output) !== '' ? trim($output) : 'Migrations and seeders ran.');
    }

    private function stepFinish(array &$state): void
    {
        if ($this->cacheClearer) {
            $out = trim((string) ($this->cacheClearer)());
            $this->log($state, 'finish', $out !== '' ? $out : 'Caches cleared.');
        }

        $this->maintenance?->__invoke(false);
        $this->log($state, 'finish', 'Maintenance mode off.');

        if ($state['webroot']['mode'] === 'separate') {
            $this->rmTree($state['webroot']['path'] . DIRECTORY_SEPARATOR . 'build.old-' . $state['id']);
        }
        $this->rmTree($this->stagingDir($state));
        $this->pruneSnapshots();

        $this->log($state, 'finish', "Now running {$state['version']}.");
    }

    private function rollback(array $state): array
    {
        if (!in_array($state['status'], self::ROLLBACKABLE, true)) {
            throw new UpdateException("An update that is {$state['status']} cannot be rolled back.");
        }

        if ($state['status'] === 'verified') {
            $this->deletePackageFile($state['id']);
            $this->deleteIfExists($this->work('state.json'));
            $state['status'] = 'discarded';
            return $state;
        }

        try {
            $snap = $this->snapshotDir($state);

            // Put back every file that existed before.
            $baseSnap = $snap . '/base';
            $restored = 0;
            if (is_dir($baseSnap)) {
                foreach ($this->filesUnder($baseSnap) as $relative) {
                    $this->replaceFile($baseSnap . '/' . $relative, $this->base($relative), $state['id']);
                    $restored++;
                }
            }

            // Remove every file the update created.
            $new = is_file($snap . '/new-files.json')
                ? (json_decode((string) file_get_contents($snap . '/new-files.json'), true) ?: [])
                : [];
            $removed = 0;
            foreach ($new['base'] ?? [] as $relative) {
                if (UpdatePaths::isAllowed($relative) && is_file($this->base($relative))) {
                    $this->deleteIfExists($this->base($relative));
                    $removed++;
                }
            }

            if ($state['webroot']['mode'] === 'separate') {
                $this->restoreWebroot($state, $new['webroot'] ?? []);
            }

            $this->dropCompiledCaches();
            $this->maintenance?->__invoke(false);

            $state['status'] = 'rolled_back';
            $this->log($state, 'rollback', "Rolled back to {$state['from_version']}: {$restored} files restored, {$removed} new files removed. Database migrations that had already run were not reversed.");
            $this->saveState($state);

            return $state;
        } catch (Throwable $e) {
            $state['status'] = 'failed';
            $state['error']  = 'ROLLBACK FAILED: ' . $e->getMessage();
            $this->log($state, 'rollback', $state['error'] . ' — the pre-update files are in ' . $this->snapshotDir($state));
            $this->saveState($state);

            throw new UpdateException($state['error'] . '. The original files are saved in ' . $this->snapshotDir($state) . ' and can be copied back by hand.', 0, $e);
        }
    }

    // ── Web root ─────────────────────────────────────────────────────────────

    private function swapWebroot(array &$state, UpdatePackage $package): void
    {
        $web     = $state['webroot']['path'];
        $staging = $this->stagingDir($state);
        $id      = $state['id'];

        // build/: assemble the new tree beside the live one, then swap names.
        $stagedBuild = $staging . '/public/build';
        if (is_dir($stagedBuild)) {
            $new = $web . DIRECTORY_SEPARATOR . 'build.new-' . $id;
            $old = $web . DIRECTORY_SEPARATOR . 'build.old-' . $id;
            $cur = $web . DIRECTORY_SEPARATOR . 'build';

            $this->rmTree($new);
            $this->copyTree($stagedBuild, $new);

            if (is_dir($cur) && !rename($cur, $old)) {
                throw new UpdateException('Could not move the web root build/ aside.');
            }

            // For an instant there is no build/ — the web root index.php may
            // copy one in from the Laravel root on its own. That copy is the
            // NEW build (the application files were swapped first), so it is
            // safe to replace.
            if (!@rename($new, $cur)) {
                $this->rmTree($cur);
                if (!rename($new, $cur)) {
                    throw new UpdateException('Could not put the new build/ into the web root.');
                }
            }
            $this->log($state, 'swap', 'Web root build/ replaced.');
        }

        $singles = 0;
        foreach (array_keys($package->files()) as $path) {
            if (UpdatePaths::isWebrootSingleFile($path)) {
                $this->replaceFile($staging . '/' . $path, $web . DIRECTORY_SEPARATOR . basename($path), $id);
                $singles++;
            }
        }
        if ($singles) {
            $this->log($state, 'swap', "{$singles} web root file(s) refreshed (service worker, manifest).");
        }
    }

    private function restoreWebroot(array &$state, array $newWebFiles): void
    {
        $web = $state['webroot']['path'];
        $id  = $state['id'];
        $old = $web . DIRECTORY_SEPARATOR . 'build.old-' . $id;
        $cur = $web . DIRECTORY_SEPARATOR . 'build';

        $this->rmTree($web . DIRECTORY_SEPARATOR . 'build.new-' . $id);

        if (is_dir($old)) {
            $this->rmTree($cur);
            if (!rename($old, $cur)) {
                throw new UpdateException('Could not restore the web root build/.');
            }
        }

        $snapWeb = $this->snapshotDir($state) . '/webroot';
        if (is_dir($snapWeb)) {
            foreach ($this->filesUnder($snapWeb) as $name) {
                $this->replaceFile($snapWeb . '/' . $name, $web . DIRECTORY_SEPARATOR . $name, $id);
            }
        }
        foreach ($newWebFiles as $name) {
            if (UpdatePaths::isSafe($name) && !str_contains($name, '/')) {
                $this->deleteIfExists($web . DIRECTORY_SEPARATOR . $name);
            }
        }
    }

    // ── Filesystem helpers ───────────────────────────────────────────────────

    /** Write beside the target, then rename over it — never a half-written file. */
    private function replaceFile(string $from, string $to, string $id): void
    {
        $this->ensureDir(dirname($to));
        $tmp = $to . '.upd-' . $id;
        if (!copy($from, $tmp)) {
            throw new UpdateException("Could not write {$to}.");
        }
        if (!@rename($tmp, $to)) {
            // Windows cannot always rename over an open file; fall back to copy.
            if (!copy($tmp, $to)) {
                @unlink($tmp);
                throw new UpdateException("Could not replace {$to}.");
            }
            @unlink($tmp);
        }
    }

    /**
     * A stale route cache surviving an update is what made new routes 404 on
     * the live sites in September, so failing to remove these is an error, not
     * something to shrug off.
     */
    private function dropCompiledCaches(): void
    {
        foreach (self::BOOTSTRAP_CACHES as $file) {
            $path = $this->base('bootstrap/cache/' . $file);
            $this->deleteIfExists($path);
            if (is_file($path)) {
                throw new UpdateException("Could not remove the compiled cache bootstrap/cache/{$file}; the site would keep running the old configuration or routes.");
            }
        }
        clearstatcache(true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function pruneSnapshots(): void
    {
        $root = $this->work('snapshots');
        if (!is_dir($root)) {
            return;
        }
        $dirs = array_values(array_filter(glob($root . '/*') ?: [], 'is_dir'));
        sort($dirs); // ids start with a UTC timestamp
        foreach (array_slice($dirs, 0, max(0, count($dirs) - self::KEEP_SNAPSHOTS)) as $dir) {
            $this->rmTree($dir);
        }
    }

    /** @return string[] relative forward-slashed paths of files under $dir */
    private function filesUnder(string $dir): array
    {
        $out = [];
        $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $out[] = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
            }
        }
        sort($out);

        return $out;
    }

    private function deleteIfExists(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function copyFile(string $from, string $to): void
    {
        $this->ensureDir(dirname($to));
        if (!copy($from, $to)) {
            throw new UpdateException("Could not copy {$from}.");
        }
    }

    private function copyTree(string $from, string $to): void
    {
        $this->ensureDir($to);
        foreach ($this->filesUnder($from) as $relative) {
            $this->copyFile($from . '/' . $relative, $to . '/' . $relative);
        }
    }

    private function rmTree(string $dir): void
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

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UpdateException("Cannot create directory {$dir}.");
        }
    }

    // ── State & paths ────────────────────────────────────────────────────────

    private function saveState(array $state): void
    {
        $this->ensureDir($this->workDir);
        $tmp = $this->work('state.json.tmp');
        file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (!@rename($tmp, $this->work('state.json'))) {
            copy($tmp, $this->work('state.json'));
            @unlink($tmp);
        }
    }

    private function log(array &$state, string $step, string $message): void
    {
        $state['log'][] = ['at' => gmdate('c'), 'step' => $step, 'message' => $message];
    }

    /** One step at a time, across requests and tabs. */
    private function locked(Closure $fn): mixed
    {
        $this->ensureDir($this->workDir);
        $handle = fopen($this->work('update.lock'), 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            throw new UpdateException('Another update step is already running. Wait for it to finish.');
        }
        try {
            return $fn();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function deletePackageFile(string $id): void
    {
        $this->deleteIfExists($this->work("packages/{$id}.zip"));
    }

    private function packagePath(array $state): string
    {
        $path = $this->work("packages/{$state['id']}.zip");
        if (!is_file($path)) {
            throw new UpdateException('The stored package file has gone missing.');
        }
        return $path;
    }

    private function snapshotDir(array $state): string
    {
        return $this->work('snapshots/' . $state['id']);
    }

    private function stagingDir(array $state): string
    {
        return $this->work('staging/' . $state['id']);
    }

    private function work(string $relative): string
    {
        return rtrim($this->workDir, '/\\') . '/' . $relative;
    }

    private function base(string $relative): string
    {
        return rtrim($this->basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private static function samePath(string $a, string $b): bool
    {
        $norm = fn ($p) => rtrim(str_replace('\\', '/', $p), '/');

        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($norm($a), $norm($b)) === 0
            : $norm($a) === $norm($b);
    }
}
