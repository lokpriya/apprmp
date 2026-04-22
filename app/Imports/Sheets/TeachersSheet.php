<?php

namespace App\Imports\Sheets;

use App\Models\Institution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class TeachersSheet implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }

    private array $errors = [];

    public function __construct(private readonly InstitutionsSheet $parent) {}

    public function collection(Collection $rows): void
    {
        $map = $this->parent->getCodeToIdMap();

        $grouped = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $row    = $row->toArray();
            $code   = trim($row['institution_code'] ?? '');

            if (!$code) continue;

            if (!isset($map[$code])) {
                $this->errors[] = "Row {$rowNum}: institution_code '{$code}' not found.";
                continue;
            }

            $fullName = trim($row['full_name'] ?? '');
            if (!$fullName) {
                $this->errors[] = "Row {$rowNum}: full_name is required.";
                continue;
            }

            // subjects column: pipe-separated values e.g. "math|science|nepali"
            $subjectsRaw = trim($row['subjects'] ?? '');
            $subjects = $subjectsRaw
                ? array_values(array_filter(array_map('trim', explode('|', $subjectsRaw))))
                : [];

            $grouped[$code][] = [
                'full_name'         => $fullName,
                'gender'            => trim($row['gender']            ?? '') ?: null,
                'category'          => trim($row['category']          ?? '') ?: null,
                'qualification'     => trim($row['qualification']     ?? '') ?: null,
                'appointment_level' => trim($row['appointment_level'] ?? '') ?: null,
                'specialization'    => trim($row['specialization']    ?? '') ?: null,
                'subjects'          => $subjects,
                'enrollment_date'   => trim($row['enrollment_date']   ?? '') ?: null,
            ];
        }

        DB::transaction(function () use ($grouped, $map) {
            foreach ($grouped as $code => $teachers) {
                $institution = Institution::find($map[$code]);
                if (!$institution) continue;

                $surveyData              = $institution->survey_data ?? [];
                $surveyData['teachers']  = $teachers;
                $institution->update(['survey_data' => $surveyData]);
            }
        });
    }

    public function getErrors(): array { return $this->errors; }
}
