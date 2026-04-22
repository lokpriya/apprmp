<?php

namespace App\Imports\Sheets;

use App\Models\InstitutionEducation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class InstEducationSheet implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }

    private array $errors = [];

    public function __construct(private readonly InstitutionsSheet $parent) {}

    public function collection(Collection $rows): void
    {
        $map = $this->parent->getCodeToIdMap();

        DB::transaction(function () use ($rows, $map) {
            $ids = array_values($map);
            if ($ids) {
                InstitutionEducation::whereIn('institution_id', $ids)->delete();
            }

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                $row    = $row->toArray();
                $code   = trim($row['institution_code'] ?? '');

                if (!$code || !isset($map[$code])) {
                    $this->errors[] = "Row {$rowNum}: institution_code '{$code}' not found.";
                    continue;
                }

                $grade = trim($row['grade'] ?? '');
                if (!$grade) {
                    $this->errors[] = "Row {$rowNum}: grade is required.";
                    continue;
                }

                try {
                    InstitutionEducation::create([
                        'institution_id'       => $map[$code],
                        'grade'                => $grade,
                        'male_students'        => is_numeric($row['male_students'] ?? '') ? (int)$row['male_students'] : 0,
                        'female_students'      => is_numeric($row['female_students'] ?? '') ? (int)$row['female_students'] : 0,
                        'dalit_students'       => is_numeric($row['dalit_students'] ?? '') ? (int)$row['dalit_students'] : 0,
                        'janajati_students'    => is_numeric($row['janajati_students'] ?? '') ? (int)$row['janajati_students'] : 0,
                        'scholarship_students' => is_numeric($row['scholarship_students'] ?? '') ? (int)$row['scholarship_students'] : 0,
                        'dropout_count'        => is_numeric($row['dropout_count'] ?? '') ? (int)$row['dropout_count'] : 0,
                    ]);
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    public function getErrors(): array { return $this->errors; }
}
