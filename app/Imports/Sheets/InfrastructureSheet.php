<?php

namespace App\Imports\Sheets;

use App\Models\InstitutionInfrastructure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class InfrastructureSheet implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }

    private array $errors = [];

    public function __construct(private readonly InstitutionsSheet $parent) {}

    public function collection(Collection $rows): void
    {
        $map = $this->parent->getCodeToIdMap();

        DB::transaction(function () use ($rows, $map) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                $row    = $row->toArray();
                $code   = trim($row['institution_code'] ?? '');

                if (!$code || !isset($map[$code])) {
                    $this->errors[] = "Row {$rowNum}: institution_code '{$code}' not found in Institutions sheet.";
                    continue;
                }

                try {
                    InstitutionInfrastructure::updateOrCreate(
                        ['institution_id' => $map[$code]],
                        [
                            'building_type'      => trim($row['building_type'] ?? '') ?: null,
                            'building_condition' => trim($row['building_condition'] ?? '') ?: null,
                            'total_rooms'        => is_numeric($row['total_rooms'] ?? '') ? (int)$row['total_rooms'] : null,
                            'total_floors'       => is_numeric($row['total_floors'] ?? '') ? (int)$row['total_floors'] : null,
                            'land_area_sqft'     => is_numeric($row['land_area_sqft'] ?? '') ? (float)$row['land_area_sqft'] : null,
                            'has_boundary_wall'  => $this->parseBool($row['has_boundary_wall'] ?? null),
                            'toilet_count'       => is_numeric($row['toilet_count'] ?? '') ? (int)$row['toilet_count'] : null,
                            'female_toilet'      => is_numeric($row['female_toilet'] ?? '') ? (int)$row['female_toilet'] : null,
                            'male_toilet'        => is_numeric($row['male_toilet'] ?? '') ? (int)$row['male_toilet'] : null,
                            'disabled_toilet'    => is_numeric($row['disabled_toilet'] ?? '') ? (int)$row['disabled_toilet'] : null,
                            'accessible_ramp'    => $this->parseBool($row['accessible_ramp'] ?? null),
                            'drinking_water'     => $this->parseBool($row['drinking_water'] ?? null),
                            'electricity'        => $this->parseBool($row['electricity'] ?? null),
                            'solar'              => $this->parseBool($row['solar'] ?? null),
                            'generator'          => $this->parseBool($row['generator'] ?? null),
                            'internet'           => $this->parseBool($row['internet'] ?? null),
                            'fire_extinguisher'  => $this->parseBool($row['fire_extinguisher'] ?? null),
                            'earthquake_safe'    => $this->parseBool($row['earthquake_safe'] ?? null),
                            'evacuation_plan'    => $this->parseBool($row['evacuation_plan'] ?? null),
                        ]
                    );
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    private function parseBool(mixed $val): bool
    {
        if (is_null($val) || $val === '') return false;
        return in_array(strtolower((string)$val), ['1', 'yes', 'true', 'y']);
    }

    public function getErrors(): array { return $this->errors; }
}
