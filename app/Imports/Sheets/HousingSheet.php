<?php

namespace App\Imports\Sheets;

use App\Models\HouseholdHousing;
use App\Services\SurveyValueValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class HousingSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }
    public function chunkSize(): int { return 200; }

    private array $errors   = [];
    private int   $imported = 0;

    public function __construct(
        private readonly HouseholdsSheet $householdsSheet,
        private readonly ?SurveyValueValidator $validator = null,
    ) {}

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
                    $householdId = $codeMap[$code];

                    // ── Survey option validation ──────────────────────────────
                    if ($this->validator) {
                        $errs = $this->validator->checkFields([
                            'house_type'      => trim($row['house_type']      ?? ''),
                            'roof_type'       => trim($row['roof_type']       ?? ''),
                            'wall_type'       => trim($row['wall_type']       ?? ''),
                            'foundation_type' => trim($row['foundation_type'] ?? ''),
                            'ownership_type'  => trim($row['ownership_type']  ?? ''),
                            'house_condition' => trim($row['house_condition'] ?? ''),
                        ], $rowNum);
                        if ($errs) {
                            foreach ($errs as $e) $this->errors[] = $e;
                            continue;
                        }
                    }

                    // Parse risk_types (comma-separated string → array)
                    $riskTypes = $this->parseArray($row['risk_types'] ?? null);

                    HouseholdHousing::updateOrCreate(
                        ['household_id' => $householdId],
                        [
                            'house_type'              => trim($row['house_type'] ?? '') ?: null,
                            'roof_type'               => trim($row['roof_type'] ?? '') ?: null,
                            'wall_type'               => trim($row['wall_type'] ?? '') ?: null,
                            'foundation_type'         => trim($row['foundation_type'] ?? '') ?: null,
                            'ownership_type'          => trim($row['ownership_type'] ?? '') ?: null,
                            'rent_amount'             => is_numeric($row['rent_amount'] ?? '') ? $row['rent_amount'] : null,
                            'total_rooms'             => is_numeric($row['total_rooms'] ?? '') ? (int)$row['total_rooms'] : null,
                            'total_floors'            => is_numeric($row['total_floors'] ?? '') ? (int)$row['total_floors'] : null,
                            'construction_year'       => is_numeric($row['construction_year'] ?? '') ? (int)$row['construction_year'] : null,
                            'house_condition'         => trim($row['house_condition'] ?? '') ?: null,
                            'has_building_permit'     => $this->bool($row['has_building_permit'] ?? 0),
                            'is_at_risk'              => $this->bool($row['is_at_risk'] ?? 0),
                            'risk_types'              => $riskTypes ?: null,
                            'land_measurement_unit'   => trim($row['land_measurement_unit'] ?? '') ?: null,
                            'house_land_type'         => trim($row['house_land_type'] ?? '') ?: null,
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

    private function parseArray(mixed $val): array
    {
        if (!$val) return [];
        return array_filter(array_map('trim', explode(',', (string)$val)));
    }

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
