<?php

namespace App\Exports;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Stops enumerator-entered text becoming a live formula in exported spreadsheets.
 *
 * PhpSpreadsheet's default binder turns any string starting with "=" into
 * DataType::TYPE_FORMULA. Verified directly: "=cmd|'/c calc'!A1" and
 * "=HYPERLINK(...)" both bind as type "f" and execute when the file is opened.
 * The data exports carry free text typed by enumerators in the field —
 * respondent_name, institution names, settlement names — so a crafted household
 * name becomes code running on the machine of whichever municipal officer opens
 * the download.
 *
 * Unlike raw CSV, only "=" matters here: "@SUM(...)" binds as a string and "-42"
 * binds as a number, both inert. This binder therefore touches nothing else, and
 * numbers, dates and Devanagari text keep their normal types.
 *
 * Applied globally in AppServiceProvider rather than per-export, because it must
 * cover all fifteen export classes including any added later. That is safe here:
 * no export writes a real formula — every setCellValue() call in app/Exports is
 * static help text.
 *
 * The companion protection for true CSV output (which also has to neutralise
 * @, -, +, tab and CR) is OpenDataController::csvSafe().
 */
class SafeStringValueBinder extends DefaultValueBinder
{
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && str_starts_with($value, '=')) {
            // Store the text exactly as entered, but as a string rather than a
            // formula. Excel shows the original characters and evaluates nothing.
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
