<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the PHP upload limits against the app's own validation rules.
 *
 * The landmark photo "would not upload and would not save" because
 * upload_max_filesize was 2M while the rule — and the label printed on the card —
 * promised 4 MB. PHP rejects an oversized upload at the SAPI layer, before any
 * Laravel code runs: no validation error, no exception, no log line. The request
 * simply comes back empty and the UI has nothing to show.
 *
 * A functional test cannot catch this, because a phpunit request never passes
 * through the SAPI limit. Comparing the two numbers is the only way to see it,
 * so this test reads the running PHP configuration rather than mocking it.
 *
 * If this fails on a server, raise the limits in php.ini (locally) or in
 * cPanel > MultiPHP INI Editor (on the deployments) — do not lower the rules.
 */
class UploadLimitsTest extends TestCase
{
    private function toKilobytes(string $iniValue): int
    {
        $value = trim($iniValue);
        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => (int) ($number / 1024),
        };
    }

    /**
     * @return array<string,int> "file:line rule" => max size in KB
     */
    private function declaredUploadMaxima(): array
    {
        $root = dirname(__DIR__, 2).'/app';
        $found = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            foreach (file($fileInfo->getPathname()) as $index => $line) {
                // Only validation strings that actually describe an upload.
                if (! preg_match('#\|(image|file|mimes)[|:]#', $line)) {
                    continue;
                }
                if (! preg_match('#max:(\d+)#', $line, $m)) {
                    continue;
                }

                $relative = str_replace(dirname(__DIR__, 2).DIRECTORY_SEPARATOR, '', $fileInfo->getPathname());
                $found[$relative.':'.($index + 1)] = (int) $m[1];
            }
        }

        return $found;
    }

    public function test_php_accepts_every_upload_size_the_app_promises(): void
    {
        $uploadMaxKb = $this->toKilobytes(ini_get('upload_max_filesize'));
        $declared = $this->declaredUploadMaxima();

        $this->assertNotEmpty($declared, 'No upload validation rules found — has the rule format changed?');

        $tooBig = [];
        foreach ($declared as $where => $ruleKb) {
            if ($ruleKb > $uploadMaxKb) {
                $tooBig[] = "{$where} allows {$ruleKb} KB";
            }
        }

        $this->assertSame([], $tooBig, sprintf(
            "upload_max_filesize is %s (%d KB), which is smaller than rules the app advertises.\n"
            ."Files between the two sizes fail silently: PHP discards them before Laravel validates,\n"
            ."so the user sees no error at all. Raise upload_max_filesize; do not lower the rules.\n  %s",
            ini_get('upload_max_filesize'),
            $uploadMaxKb,
            implode("\n  ", $tooBig)
        ));
    }

    /**
     * post_max_size caps the whole request body, so it must exceed
     * upload_max_filesize — a form carries other fields besides the file. When it
     * is too small PHP discards the entire body including the CSRF token, and the
     * user gets "419 Page Expired" instead of anything meaningful.
     */
    public function test_post_max_size_leaves_room_around_the_largest_upload(): void
    {
        $postMaxKb = $this->toKilobytes(ini_get('post_max_size'));
        $uploadMaxKb = $this->toKilobytes(ini_get('upload_max_filesize'));

        $this->assertGreaterThan($uploadMaxKb, $postMaxKb, sprintf(
            'post_max_size (%s) must exceed upload_max_filesize (%s), or uploads at the '
            .'limit are dropped with a 419 rather than a validation error.',
            ini_get('post_max_size'),
            ini_get('upload_max_filesize')
        ));
    }
}
