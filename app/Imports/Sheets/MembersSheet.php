<?php

namespace App\Imports\Sheets;

use App\Models\HouseholdMember;
use App\Services\SurveyValueValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class MembersSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
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
            $sortCounters = [];
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                try {
                    $code = trim($row['household_code'] ?? '');
                    if (!$code || !isset($codeMap[$code])) {
                        $this->errors[] = "Row {$rowNum}: household_code '{$code}' not found in imported households.";
                        continue;
                    }
                    $householdId = $codeMap[$code];
                    $sortCounters[$householdId] = ($sortCounters[$householdId] ?? 0) + 1;

                    // ── Survey option validation ──────────────────────────────
                    if ($this->validator) {
                        $errs = $this->validator->checkFields([
                            'relation_to_head'  => trim($row['relation_to_head']  ?? ''),
                            'ethnicity'         => trim($row['ethnicity']         ?? ''),
                            'religion'          => trim($row['religion']          ?? ''),
                            'language'          => trim($row['mother_tongue']     ?? ''),
                            'marital_status'    => trim($row['marital_status']    ?? ''),
                            'education_level'   => trim($row['education_level']   ?? ''),
                            'occupation_type'   => trim($row['occupation_type']   ?? ''),
                            'employment_status' => trim($row['employment_status'] ?? ''),
                            'disability_type'   => trim($row['disability_type']   ?? ''),
                            'blood_group'       => trim($row['blood_group']       ?? ''),
                            'residence_status'  => trim($row['residence_status']  ?? ''),
                        ], $rowNum);
                        if ($errs) {
                            foreach ($errs as $e) $this->errors[] = $e;
                            continue;
                        }
                    }

                    HouseholdMember::create([
                        // ── Identity ─────────────────────────────────────────
                        'household_id'           => $householdId,
                        'is_household_head'      => $this->bool($row['is_household_head'] ?? 0),
                        'full_name'              => trim($row['full_name'] ?? ''),
                        'father_name'            => trim($row['father_name'] ?? '') ?: null,
                        'relation_to_head'       => trim($row['relation_to_head'] ?? '') ?: null,
                        'date_of_birth'          => $this->parseDate($row['date_of_birth'] ?? null),
                        'age'                    => is_numeric($row['age'] ?? '') ? (int)$row['age'] : null,
                        'gender'                 => trim($row['gender'] ?? '') ?: null,
                        'ethnicity'              => trim($row['ethnicity'] ?? '') ?: null,
                        'religion'               => trim($row['religion'] ?? '') ?: null,
                        'mother_tongue'          => trim($row['mother_tongue'] ?? '') ?: null,
                        // ── Marital ───────────────────────────────────────────
                        'marital_status'         => trim($row['marital_status'] ?? '') ?: null,
                        'ageofmarriage'          => is_numeric($row['ageofmarriage'] ?? '') ? (int)$row['ageofmarriage'] : null,
                        // ── Education & Occupation ────────────────────────────
                        'education_level'        => trim($row['education_level'] ?? '') ?: null,
                        'is_literate'            => $this->bool($row['is_literate'] ?? 0),
                        'occupation_type'        => trim($row['occupation_type'] ?? '') ?: null,
                        'vocational_training'    => $this->bool($row['vocational_training'] ?? 0),
                        'vt_subject'             => trim($row['vt_subject'] ?? '') ?: null,
                        'employment_status'      => trim($row['employment_status'] ?? '') ?: null,
                        // ── Disability & Health ───────────────────────────────
                        'disability_status'      => $this->bool($row['disability_status'] ?? 0),
                        'disability_type'        => trim($row['disability_type'] ?? '') ?: null,
                        'chronic_illness'        => $this->bool($row['chronic_illness'] ?? 0),
                        'chronic_illness_types'  => $this->parseArray($row['chronic_illness_types'] ?? null) ?: null,
                        'child_labor'            => $this->bool($row['child_labor'] ?? 0),
                        'workplace_child_labor'  => trim($row['workplace_child_labor'] ?? '') ?: null,
                        // ── Blood & Documents ─────────────────────────────────
                        'blood_group'            => trim($row['blood_group'] ?? '') ?: null,
                        'has_birth_certificate'  => $this->bool($row['has_birth_certificate'] ?? 0),
                        'has_citizenship'        => $this->bool($row['has_citizenship'] ?? 0),
                        'has_voters_card'        => $this->bool($row['has_voters_card'] ?? 0),
                        'has_national_id_card'   => $this->bool($row['has_national_id_card'] ?? 0),
                        'has_passport'           => $this->bool($row['has_passport'] ?? 0),
                        'has_pan'                => $this->bool($row['has_pan'] ?? 0),
                        'has_drivers_license'    => $this->bool($row['has_drivers_license'] ?? 0),
                        'drivers_license_category' => $this->parseArray($row['drivers_license_category'] ?? null) ?: null,
                        // ── Location & Contact ────────────────────────────────
                        'residence_status'       => trim($row['residence_status'] ?? '') ?: null,
                        'addrslocation'          => trim($row['addrslocation'] ?? '') ?: null,
                        'contact_phone'          => trim($row['contact_phone'] ?? '') ?: null,
                        'national_id'            => trim($row['national_id'] ?? '') ?: null,
                        // ── Social & Deceased ─────────────────────────────────
                        'ss_allowance'           => $this->bool($row['social_security_allowance'] ?? 0),
                        'is_deceased'            => $this->bool($row['is_deceased'] ?? 0),
                        'death_cause'            => trim($row['death_cause'] ?? '') ?: null,
                        'remarks'                => trim($row['remarks'] ?? '') ?: null,
                        'sort_order'             => $sortCounters[$householdId],
                    ]);
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
        return array_values(array_filter(array_map('trim', explode(',', (string)$val))));
    }

    private function parseDate(mixed $val): ?string
    {
        if (!$val) return null;
        if (is_numeric($val)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val)->format('Y-m-d');
            } catch (\Throwable) {}
        }
        $d = \DateTime::createFromFormat('Y-m-d', trim((string)$val));
        return $d ? $d->format('Y-m-d') : null;
    }

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
