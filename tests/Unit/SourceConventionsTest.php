<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Static guards against bug patterns that have already been fixed and then
 * silently reintroduced by later edits.
 *
 * These are not style checks. Each pattern below shipped to production at least
 * once, broke a live site, and was reintroduced because the fix lived in a
 * single easily-rewritten line with nothing asserting it.
 */
class SourceConventionsTest extends TestCase
{
    /** @return string[] absolute paths of every PHP file under app/ and routes/ */
    private function sourceFiles(): array
    {
        $roots = [dirname(__DIR__, 2).'/app', dirname(__DIR__, 2).'/routes'];
        $files = [];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<string,int[]> file => matching line numbers
     */
    private function grep(string $pattern): array
    {
        $hits = [];

        foreach ($this->sourceFiles() as $path) {
            foreach (file($path) as $index => $line) {
                if (preg_match($pattern, $line)) {
                    $hits[$path][] = $index + 1;
                }
            }
        }

        return $hits;
    }

    private function assertNoMatches(string $pattern, string $because): void
    {
        $hits = $this->grep($pattern);

        $report = [];
        foreach ($hits as $path => $lines) {
            $relative = str_replace(dirname(__DIR__, 2).DIRECTORY_SEPARATOR, '', $path);
            $report[] = $relative.':'.implode(',', $lines);
        }

        $this->assertSame([], $report, $because."\nFound in:\n  ".implode("\n  ", $report));
    }

    /**
     * On the cPanel deployments storage/ is not writable in place, so the public
     * disk is relocated with PUBLIC_DISK_ROOT (config/filesystems.php). Any code
     * that builds storage_path('app/public/...') by hand bypasses that override
     * and reports every uploaded file as missing — this is what blanked the
     * logos on all four public report controllers, twice.
     */
    public function test_public_storage_is_never_addressed_by_raw_path(): void
    {
        $this->assertNoMatches(
            "#storage_path\(\s*['\"]app[/\\\\]public#",
            'Use Storage::disk(\'public\') instead of storage_path(\'app/public/...\'); '
            .'the raw path ignores the PUBLIC_DISK_ROOT override used in production.'
        );
    }

    /**
     * LocalGovernment::first() returns whichever row the storage engine hands
     * back first and hits the database on every request. getFirstCached() is the
     * single source of truth for "the active LG".
     */
    public function test_active_local_government_is_always_read_through_the_cache(): void
    {
        $this->assertNoMatches(
            '#LocalGovernment::(query\(\)->)?first\(\)#',
            'Use LocalGovernment::getFirstCached() rather than ::first().'
        );
    }

    /**
     * `env()` outside config/ returns null as soon as the config is cached
     * (php artisan config:cache), which is how production runs. Values must be
     * read through config() so a cached config cannot silently blank them.
     */
    public function test_env_is_not_read_outside_config(): void
    {
        $this->assertNoMatches(
            '#(?<![A-Za-z_>$])env\(#',
            'Read configuration through config(), not env(): env() returns null '
            .'once the config is cached, which is how production runs.'
        );
    }
}
