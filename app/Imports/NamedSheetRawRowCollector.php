<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;

/**
 * Reads a specific named sheet from an XLSX by tab name.
 * Falls back to RawRowCollector (first sheet) when sheet name is not found.
 */
class NamedSheetRawRowCollector implements WithMultipleSheets, SkipsUnknownSheets
{
    private RawRowCollector $inner;

    public function __construct(private string $sheetName)
    {
        $this->inner = new RawRowCollector();
    }

    public function sheets(): array
    {
        return [$this->sheetName => $this->inner];
    }

    public function onUnknownSheet($sheetName): void {}

    public function getRows(): array
    {
        return $this->inner->rows;
    }
}
