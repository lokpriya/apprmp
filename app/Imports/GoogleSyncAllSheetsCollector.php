<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;

/**
 * Reads all 8 data sheets from a Google Sync XLSX in a single import pass.
 */
class GoogleSyncAllSheetsCollector implements WithMultipleSheets, SkipsUnknownSheets
{
    private const SHEETS = [
        'Households', 'Members', 'Housing',
        'Utilities', 'Economic', 'Agriculture', 'Livestock', 'LandParcels',
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
