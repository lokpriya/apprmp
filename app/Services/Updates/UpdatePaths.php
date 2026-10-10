<?php

namespace App\Services\Updates;

/**
 * What an update package is allowed to contain.
 *
 * This is the security boundary of the updater. A package can only ever write
 * inside these paths, whatever its manifest says and whoever signed it. In
 * particular it can never touch .env (which holds APP_KEY — replacing it would
 * destroy every stored 2FA secret), storage/ (uploads, sessions, logs),
 * vendor/ (app-only updates leave dependencies alone), or the web root's
 * index.php.
 *
 * The same rules are applied when building a package and when verifying one,
 * so a package that builds is a package that installs.
 */
final class UpdatePaths
{
    /** Directories shipped in full. Trailing slash matters. */
    public const DIRS = [
        'app/',
        'config/',
        'database/data/',
        'database/migrations/',
        'database/seeders/',
        'resources/views/',
        'routes/',
        'public/build/',
    ];

    /** Individual files shipped when present. */
    public const FILES = [
        'VERSION',
        'bootstrap/app.php',
        'bootstrap/providers.php',
        'public/sw.js',
        'public/manifest.webmanifest',
        'public/offline.html',
    ];

    /** Generated files whose names change on every build. */
    public const PATTERNS = [
        '#^public/workbox-[A-Za-z0-9]+\.js$#',
    ];

    /**
     * Public files that must ALSO be copied into the separate web root on
     * cPanel. The web root's index.php only copies these when they are missing,
     * so without this an update would ship new code with stale JavaScript —
     * and since @vite reads the manifest from the Laravel root, the page would
     * request hashed asset files the web root does not have.
     */
    public const WEBROOT_SINGLE_FILES = [
        'public/sw.js',
        'public/manifest.webmanifest',
        'public/offline.html',
    ];

    public const WEBROOT_BUILD_DIR = 'public/build/';

    /** Syntactically safe: relative, forward-slashed, no traversal, no hidden env files. */
    public static function isSafe(string $path): bool
    {
        if ($path === '' || strlen($path) > 300) {
            return false;
        }

        if (str_contains($path, "\0") || str_contains($path, '\\') || str_contains($path, ':')) {
            return false;
        }

        if (str_starts_with($path, '/')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
            if (str_starts_with(strtolower($segment), '.env')) {
                return false;
            }
        }

        return true;
    }

    /** Safe AND inside the allowlist. */
    public static function isAllowed(string $path): bool
    {
        if (!self::isSafe($path)) {
            return false;
        }

        if (in_array($path, self::FILES, true)) {
            return true;
        }

        foreach (self::DIRS as $dir) {
            if (str_starts_with($path, $dir) && strlen($path) > strlen($dir)) {
                return true;
            }
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /** Single public file that also lives directly in the web root (sw.js, workbox-*.js …). */
    public static function isWebrootSingleFile(string $path): bool
    {
        if (in_array($path, self::WEBROOT_SINGLE_FILES, true)) {
            return true;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
