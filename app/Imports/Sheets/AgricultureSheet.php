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
                    // agriculture, owns_land, uses_* are VARCHAR(10) storing 'yes'/'no' — use yn() not bool()
                    $chemFert = $row['use_of_chem_fertilizer'] ?? 0;
                    HouseholdAgriculture::updateOrCreate(
                        ['household_id' => $codeMap[$code]],
                        [
                            'agriculture'               => $this->yn($row['agriculture'] ?? 0),
                            'owns_land'                 => $this->yn($row['owns_land'] ?? 0),
                            'land_area_ropani'          => is_numeric($row['land_area_ropani'] ?? '') ? $row['land_area_ropani'] : null,
                            'irrigated_area_ropani'     => is_numeric($row['irrigated_area_ropani'] ?? '') ? $row['irrigated_area_ropani'] : null,
                            'irrigation_source'         => trim($row['irrigation_source'] ?? '') ?: null,
                            'agri_categories'           => $this->parseArray($row['agri_category'] ?? null) ?: null,
                            'use_of_chem_fertilizer'    => $this->yn($chemFert),
                            'uses_chemical_fertilizer'  => $this->yn($chemFert),
                            'chem_fertilizer_types'     => $this->parseArray($row['chem_fertilizer'] ?? null) ?: null,
                            'chem_fertilizer_kg'        => is_numeric($row['chem_fertilizer_kg'] ?? '') ? $row['chem_fertilizer_kg'] : null,
                            'fertilizer_sources'        => $this->parseArray($row['fertilizer_source'] ?? null) ?: null,
                            'use_of_chem_pesticide'     => $this->yn($row['use_of_chem_pesticide'] ?? 0),
                            'chem_pesticide_types'      => $this->parseArray($row['chem_pesticide'] ?? null) ?: null,
                            'chem_pesticide_cost'       => is_numeric($row['chem_pesticide_cost'] ?? '') ? $row['chem_pesticide_cost'] : null,
                            'Farm_Challenges'           => $this->parseArray($row['farm_challenge'] ?? null) ?: null,
                            'annual_agri_income'        => is_numeric($row['annual_agri_income'] ?? '') ? $row['annual_agri_income'] : null,
                            'uses_organic_fertilizer'   => $this->yn($row['uses_organic_fertilizer'] ?? 0),
                            'sells_produce'             => $this->yn($row['sells_produce'] ?? 0),
                            'has_agri_loan'             => $this->yn($row['has_agri_loan'] ?? 0),
                        ]
                    );
                    $this->imported++;
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    private function yn(mixed $val): string
    {
        return in_array(strtolower((string)$val), ['1', 'yes', 'true', 'y'], true) ? 'yes' : 'no';
    }

    private function parseArray(mixed $val): array
    {
        if (!$val) return [];
        return array_filter(array_map('trim', explode(',', (string)$val)));
    }

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
