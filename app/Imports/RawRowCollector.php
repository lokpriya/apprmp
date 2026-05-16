<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

/**
 * Reads XLSX rows as plain arrays without writing anything to DB.
 * Google Sync exports have no hints row — data starts at row 2 (row 1 = header).
 */
class RawRowCollector implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public array $rows = [];

    public function startRow(): int  { return 2; }
    public function chunkSize(): int { return 100; }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->rows[] = $row->toArray();
        }
    }
}
