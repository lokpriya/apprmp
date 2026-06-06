<?php

namespace App\Imports\Sheets;

use App\Models\InstitutionHealth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class InstHealthSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }
    public function chunkSize(): int { return 200; }

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
                    $this->errors[] = "Row {$rowNum}: institution_code '{$code}' not found.";
                    continue;
                }

                try {
                    InstitutionHealth::updateOrCreate(
                        ['institution_id' => $map[$code]],
                        [
                            'health_type'           => trim($row['health_type'] ?? '') ?: null,
                            'bed_count'             => is_numeric($row['bed_count'] ?? '') ? (int)$row['bed_count'] : null,
                            'male_ward_beds'        => is_numeric($row['male_ward_beds'] ?? '') ? (int)$row['male_ward_beds'] : null,
                            'female_ward_beds'      => is_numeric($row['female_ward_beds'] ?? '') ? (int)$row['female_ward_beds'] : null,
                            'children_ward_beds'    => is_numeric($row['children_ward_beds'] ?? '') ? (int)$row['children_ward_beds'] : null,
                            'maternity_beds'        => is_numeric($row['maternity_beds'] ?? '') ? (int)$row['maternity_beds'] : null,
                            'icu_beds'              => is_numeric($row['icu_beds'] ?? '') ? (int)$row['icu_beds'] : null,
                            'emergency_beds'        => is_numeric($row['emergency_beds'] ?? '') ? (int)$row['emergency_beds'] : null,
                            'delivery_service'      => $this->parseBool($row['delivery_service'] ?? null),
                            'emergency_service'     => $this->parseBool($row['emergency_service'] ?? null),
                            'has_lab'               => $this->parseBool($row['lab_service'] ?? null),
                            'has_xray'              => $this->parseBool($row['xray_service'] ?? null),
                            'has_ambulance'         => $this->parseBool($row['ambulance'] ?? null),
                            'vaccine_service'       => $this->parseBool($row['vaccine_service'] ?? null),
                            'family_planning'       => $this->parseBool($row['family_planning'] ?? null),
                            'opd_daily'             => is_numeric($row['opd_daily'] ?? '') ? (int)$row['opd_daily'] : null,
                            'opd_monthly'           => is_numeric($row['monthly_outpatient_avg'] ?? '') ? (int)$row['monthly_outpatient_avg'] : null,
                            'ipd_monthly'           => is_numeric($row['monthly_inpatient_avg'] ?? '') ? (int)$row['monthly_inpatient_avg'] : null,
                            'has_ultrasound'        => $this->parseBool($row['has_ultrasound'] ?? null),
                            'has_ecg'               => $this->parseBool($row['has_ecg'] ?? null),
                            'has_icu'               => $this->parseBool($row['has_icu'] ?? null),
                            'has_operation_theatre' => $this->parseBool($row['has_operation_theatre'] ?? null),
                            'has_blood_bank'        => $this->parseBool($row['has_blood_bank'] ?? null),
                            'has_dialysis'          => $this->parseBool($row['has_dialysis'] ?? null),
                            'has_dental'            => $this->parseBool($row['has_dental'] ?? null),
                            'has_physiotherapy'     => $this->parseBool($row['has_physiotherapy'] ?? null),
                            'has_oxygen_plant'      => $this->parseBool($row['has_oxygen_plant'] ?? null),
                            'health_services'       => $this->parseArr($row['health_services'] ?? '') ?: null,
                            'equipment'             => $this->parseArr($row['equipment'] ?? '') ?: null,
                            'lab_services'          => $this->parseArr($row['lab_services'] ?? '') ?: null,
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

    private function parseArr(mixed $val): array
    {
        $str = trim((string)($val ?? ''));
        if ($str === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $str))));
    }

    public function getErrors(): array { return $this->errors; }
}
