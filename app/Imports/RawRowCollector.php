<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

/**
 * Reads XLSX rows as plain arrays without writing anything to DB.
 * Uses start row 3 to match the import template format (row 2 = hints).
 */
class RawRowCollector implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public array $rows = [];

    public function startRow(): int { return 3; }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows->map(fn($r) => $r->toArray())->toArray();
    }
}
