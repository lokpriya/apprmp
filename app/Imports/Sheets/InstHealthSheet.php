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
                            'bed_count'              => is_numeric($row['bed_count'] ?? '') ? (int)$row['bed_count'] : null,
                            'delivery_service'       => $this->parseBool($row['delivery_service'] ?? null),
                            'emergency_service'      => $this->parseBool($row['emergency_service'] ?? null),
                            'lab_service'            => $this->parseBool($row['lab_service'] ?? null),
                            'xray_service'           => $this->parseBool($row['xray_service'] ?? null),
                            'ambulance'              => $this->parseBool($row['ambulance'] ?? null),
                            'monthly_outpatient_avg' => is_numeric($row['monthly_outpatient_avg'] ?? '') ? (int)$row['monthly_outpatient_avg'] : null,
                            'monthly_inpatient_avg'  => is_numeric($row['monthly_inpatient_avg'] ?? '') ? (int)$row['monthly_inpatient_avg'] : null,
                            'vaccine_service'        => $this->parseBool($row['vaccine_service'] ?? null),
                            'family_planning'        => $this->parseBool($row['family_planning'] ?? null),
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
