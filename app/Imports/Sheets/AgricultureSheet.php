<?php

namespace App\Imports\Sheets;

use App\Models\HouseholdAgriculture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class AgricultureSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
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

                    // Template columns → model columns (aligned with HouseholdAgriculture $fillable)
                    HouseholdAgriculture::updateOrCreate(
                        ['household_id' => $codeMap[$code]],
                        [
                            'owns_land'              => $this->bool($row['owns_land'] ?? 0),
                            'land_area_ropani'       => is_numeric($row['land_area_ropani'] ?? '') ? $row['land_area_ropani'] : null,
                            'irrigated_area_ropani'  => is_numeric($row['irrigated_area_ropani'] ?? '') ? $row['irrigated_area_ropani'] : null,
                            'agri_categories'        => $this->parseArray($row['agri_categories'] ?? null) ?: null,
                            'use_of_chem_fertilizer' => $this->bool($row['use_of_chem_fertilizer'] ?? 0),
                            'Farm_Challenges'        => $this->parseArray($row['farm_challenges'] ?? null) ?: null,
                            'annual_agri_income'     => is_numeric($row['annual_agri_income'] ?? '') ? $row['annual_agri_income'] : null,
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
