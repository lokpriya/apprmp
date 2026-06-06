<?php

namespace App\Imports\Sheets;

use App\Models\HouseholdDisaster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class DisasterSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }
    public function chunkSize(): int { return 200; }

    private array $errors   = [];
    private int   $imported = 0;

    public function __construct(private readonly HouseholdsSheet $householdsSheet) {}

    public function collection(Collection $rows): void
    {
        $codeMap = $this->householdsSheet->getCodeToIdMap();

        DB::transaction(function () use ($rows, $codeMap) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                try {
                    $code = trim($row['household_code'] ?? '');
                    if (!$code || !isset($codeMap[$code])) {
                        $this->errors[] = "Row {$rowNum}: household_code '{$code}' not found.";
                        continue;
                    }

                    $riskRaw = trim($row['disaster_type'] ?? '');
                    $riskArr = $riskRaw
                        ? array_values(array_filter(array_map('trim', explode(',', $riskRaw))))
                        : null;

                    HouseholdDisaster::updateOrCreate(
                        ['household_id' => $codeMap[$code]],
                        [
                            'has_death_this_year'  => $this->bool($row['has_death_this_year'] ?? 0),
                            'risk_types'           => $riskArr,
                            'risk_reduction_plan'  => trim($row['risk_reduction_plan'] ?? '') ?: null,
                            'affected_last_3years' => $this->bool($row['affected_last_3years'] ?? 0),
                            'received_relief'      => $this->bool($row['received_relief'] ?? 0),
                        ]
                    );
                    $this->imported++;
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    private function bool(mixed $val): bool
    {
        return in_array(strtolower((string)$val), ['1', 'yes', 'true', 'y'], true);
    }

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
