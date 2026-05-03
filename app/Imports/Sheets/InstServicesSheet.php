<?php

namespace App\Imports\Sheets;

use App\Models\InstitutionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class InstServicesSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }
    public function chunkSize(): int { return 200; }

    private array $errors  = [];
    private bool  $deleted = false;

    public function __construct(private readonly InstitutionsSheet $parent) {}

    public function collection(Collection $rows): void
    {
        $map = $this->parent->getCodeToIdMap();

        DB::transaction(function () use ($rows, $map) {
            if (!$this->deleted) {
                $ids = array_values($map);
                if ($ids) InstitutionService::whereIn('institution_id', $ids)->delete();
                $this->deleted = true;
            }

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                $row    = $row->toArray();
                $code   = trim($row['institution_code'] ?? '');

                if (!$code || !isset($map[$code])) {
                    $this->errors[] = "Row {$rowNum}: institution_code '{$code}' not found.";
                    continue;
                }

                $serviceName = trim($row['service_name'] ?? '');
                if (!$serviceName) {
                    $this->errors[] = "Row {$rowNum}: service_name is required.";
                    continue;
                }

                try {
                    InstitutionService::create([
                        'institution_id'       => $map[$code],
                        'service_name'         => $serviceName,
                        'service_type'         => trim($row['service_type'] ?? '') ?: null,
                        'capacity'             => is_numeric($row['capacity'] ?? '') ? (int)$row['capacity'] : null,
                        'annual_beneficiaries' => is_numeric($row['annual_beneficiaries'] ?? '') ? (int)$row['annual_beneficiaries'] : null,
                        'coverage_description' => trim($row['coverage_description'] ?? '') ?: null,
                        'is_free'              => $this->parseBool($row['is_free'] ?? null),
                    ]);
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
