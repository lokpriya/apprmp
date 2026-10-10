<?php

namespace App\Support;

/**
 * The installed application version, read from the VERSION file at the
 * project root.
 *
 * Read at runtime rather than through config(), so it cannot go stale behind a
 * cached config after an update swaps the file. The one-file updater relies on
 * this to refuse a package that is not newer than what is installed.
 */
final class AppVersion
{
    public const UNKNOWN = '0.0.0';

    public static function current(?string $basePath = null): string
    {
        $file = rtrim($basePath ?? base_path(), '/\\') . DIRECTORY_SEPARATOR . 'VERSION';

        $raw = is_file($file) ? trim((string) @file_get_contents($file)) : '';

        return self::isValid($raw) ? $raw : self::UNKNOWN;
    }

    /** Plain MAJOR.MINOR.PATCH — no pre-release tags, so version_compare is unambiguous. */
    public static function isValid(string $version): bool
    {
        return (bool) preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}$/', $version);
    }
}
