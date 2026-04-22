<?php

namespace App\Imports\Sheets;

use App\Models\InstitutionStaff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class StaffSheet implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }

    private array $errors = [];

    public function __construct(private readonly InstitutionsSheet $parent) {}

    public function collection(Collection $rows): void
    {
        $map = $this->parent->getCodeToIdMap();

        DB::transaction(function () use ($rows, $map) {
            // Delete existing staff records for all institutions being imported
            $ids = array_values($map);
            if ($ids) {
                InstitutionStaff::whereIn('institution_id', $ids)->delete();
            }

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                $row    = $row->toArray();
                $code   = trim($row['institution_code'] ?? '');

                if (!$code || !isset($map[$code])) {
                    $this->errors[] = "Row {$rowNum}: institution_code '{$code}' not found.";
                    continue;
                }

                $category = trim($row['staff_category'] ?? '');
                if (!$category) {
                    $this->errors[] = "Row {$rowNum}: staff_category is required.";
                    continue;
                }

                try {
                    InstitutionStaff::create([
                        'institution_id'  => $map[$code],
                        'staff_category'  => $category,
                        'male_count'      => is_numeric($row['male_count'] ?? '') ? (int)$row['male_count'] : 0,
                        'female_count'    => is_numeric($row['female_count'] ?? '') ? (int)$row['female_count'] : 0,
                        'dalit_count'     => is_numeric($row['dalit_count'] ?? '') ? (int)$row['dalit_count'] : 0,
                        'janajati_count'  => is_numeric($row['janajati_count'] ?? '') ? (int)$row['janajati_count'] : 0,
                        'other_count'     => is_numeric($row['other_count'] ?? '') ? (int)$row['other_count'] : 0,
                        'permanent_count' => is_numeric($row['permanent_count'] ?? '') ? (int)$row['permanent_count'] : 0,
                        'contract_count'  => is_numeric($row['contract_count'] ?? '') ? (int)$row['contract_count'] : 0,
                    ]);
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    public function getErrors(): array { return $this->errors; }
}
