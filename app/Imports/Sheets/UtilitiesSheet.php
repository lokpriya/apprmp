<?php

namespace App\Imports\Sheets;

use App\Models\HouseholdUtility;
use App\Services\SurveyValueValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class UtilitiesSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
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

                    // ── Survey option validation ──────────────────────────────
                    if ($this->validator) {
                        $errs = $this->validator->checkFields([
                            'water_source'           => trim($row['water_source']           ?? ''),
                            'toilet_type'            => trim($row['toilet_type']            ?? ''),
                            'sanitation_type'        => trim($row['sanitation_type']        ?? ''),
                            'electricity_source'     => trim($row['electricity_source']     ?? ''),
                            'cooking_fuel'           => trim($row['cooking_fuel']           ?? ''),
                            'waste_management'       => trim($row['waste_management']       ?? ''),
                            'internet_type'          => trim($row['internet_type']          ?? ''),
                        ], $rowNum);
                        if ($errs) {
                            foreach ($errs as $e) $this->errors[] = $e;
                            continue;
                        }
                    }

                    // Build vehicles array from individual count columns
                    $vehicleTypes = [
                        'motorcycle' => (int)($row['vehicles_bike']     ?? 0),
                        'car'        => (int)($row['vehicles_car']      ?? 0),
                        'tempo'      => (int)($row['vehicles_tempo']    ?? 0),
                        'tractor'    => (int)($row['vehicles_tractor']  ?? 0),
                        'other'      => (int)($row['vehicles_other']    ?? 0),
                    ];
                    $vehiclesArr = [];
                    foreach ($vehicleTypes as $type => $cnt) {
                        if ($cnt > 0) $vehiclesArr[] = ['type' => $type, 'count' => $cnt];
                    }
                    $hasVehicle = $this->bool($row['has_own_vehicle'] ?? 0)
                        || array_sum($vehicleTypes) > 0;

                    HouseholdUtility::updateOrCreate(
                        ['household_id' => $codeMap[$code]],
                        [
                            'water_source'              => trim($row['water_source'] ?? '') ?: null,
                            'water_distance_minutes'    => is_numeric($row['water_distance_minutes'] ?? '') ? (int)$row['water_distance_minutes'] : null,
                            'uses_firewood'             => $this->yesNo($row['uses_firewood'] ?? ''),
                            'firewood_source'           => $this->parseArr($row['firewood_source'] ?? '') ?: null,
                            'cooking_stove'             => trim($row['cooking_stove'] ?? '') ?: null,
                            'cooking_fuel_arr'          => $this->parseArr($row['cooking_fuel_arr'] ?? '') ?: null,
                            'cooking_fuel'              => trim($row['cooking_fuel'] ?? '') ?: null,
                            'has_biogas'                => $this->bool($row['has_biogas'] ?? 0),
                            'has_alt_electricity'       => $this->yesNo($row['has_alt_electricity'] ?? ''),
                            'alt_electricity_sources'   => $this->parseArr($row['alt_electricity_source'] ?? '') ?: null,
                            'electricity_source'        => trim($row['electricity_source'] ?? '') ?: null,
                            'monthly_electricity_units' => is_numeric($row['monthly_electricity_units'] ?? '') ? (int)$row['monthly_electricity_units'] : null,
                            'solid_waste_management'    => trim($row['waste_management'] ?? '') ?: null,
                            'has_toilet'                => $this->bool($row['has_toilet'] ?? 0),
                            'toilet_type'               => trim($row['toilet_type'] ?? '') ?: null,
                            'sanitation_type'           => trim($row['sanitation_type'] ?? '') ?: null,
                            'handwashing'               => trim($row['handwashing'] ?? '') ?: null,
                            'access_to_facilities'      => $this->parseArr($row['facilities'] ?? '') ?: null,
                            'has_internet'              => $this->bool($row['has_internet'] ?? 0),
                            'internet_type'             => trim($row['internet_type'] ?? '') ?: null,
                            'vehicle'                   => $hasVehicle ? 'Yes' : 'No',
                            'vehicles'                  => $vehiclesArr ?: null,
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

    private function yesNo(mixed $val): ?string
    {
        $s = strtolower(trim((string)$val));
        if (in_array($s, ['1', 'yes', 'true', 'y'])) return 'yes';
        if (in_array($s, ['0', 'no', 'false', 'n'])) return 'no';
        return null;
    }

    private function parseArr(mixed $val): array
    {
        $str = trim((string)($val ?? ''));
        if ($str === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $str))));
    }

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
