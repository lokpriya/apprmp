<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;

/**
 * Reads all 7 data sheets from an Institution Google Sync XLSX in one import pass.
 */
class InstitutionSyncAllSheetsCollector implements WithMultipleSheets, SkipsUnknownSheets
{
    private const SHEETS = [
        'Institutions', 'Infrastructure', 'Staff',
        'Education', 'Health', 'Services', 'Teachers',
    ];

    /** @var RawRowCollector[] */
    private array $collectors = [];

    public function __construct()
    {
        foreach (self::SHEETS as $name) {
            $this->collectors[$name] = new RawRowCollector();
        }
    }

    public function sheets(): array
    {
        return $this->collectors;
    }

    public function onUnknownSheet($sheetName): void {}

    public function getRows(string $sheet): array
    {
        return $this->collectors[$sheet]->rows ?? [];
    }
}
