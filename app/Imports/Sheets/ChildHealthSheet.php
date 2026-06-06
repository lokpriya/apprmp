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

class ChildHealthSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
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
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;
                try {
                    $code = trim($row['household_code'] ?? '');
                    if (!$code || !isset($codeMap[$code])) {
                        $this->errors[] = "Row {$rowNum}: household_code '{$code}' not found.";
                        continue;
                    }
                    $hId  = $codeMap[$code];
                    $name = trim($row['member_name'] ?? '');
                    if (!$name) {
                        $this->errors[] = "Row {$rowNum}: member_name is required.";
                        continue;
                    }

                    $member = HouseholdMember::where('household_id', $hId)
                        ->whereRaw('LOWER(TRIM(full_name)) = ?', [mb_strtolower($name)])
                        ->first();

                    if (!$member) {
                        $this->errors[] = "Row {$rowNum}: member '{$name}' not found in household '{$code}'.";
                        continue;
                    }

                    $age = is_numeric($row['age'] ?? '') ? (int)$row['age'] : ($member->age ?? 0);

                    $data = [];

                    if ($age <= 3) {
                        $data = [
                            'childbornplace'            => trim($row['childbornplace']           ?? '') ?: null,
                            'child_weight_in_birth'     => is_numeric($row['child_weight_in_birth'] ?? '') ? $row['child_weight_in_birth'] : null,
                            'colostrum'                 => $this->bool($row['colostrum']          ?? 0),
                            'childvaccine'              => $this->parseArray($row['childvaccine'] ?? null) ?: null,
                            'vit_a_dewormin'            => $this->bool($row['vit_a_dewormin']    ?? 0),
                            'fullmilkcourse'            => $this->bool($row['fullmilkcourse']    ?? 0),
                            'durationbrestfeed'         => is_numeric($row['durationbrestfeed']  ?? '') ? (int)$row['durationbrestfeed'] : null,
                            'childhealthcheckup'        => $this->bool($row['childhealthcheckup'] ?? 0),
                            'child_caregiver'           => trim($row['child_caregiver']          ?? '') ?: null,
                            'diarrhea_before_two_weeks' => $this->bool($row['diarrhea_before_two_weeks'] ?? 0),
                            'fever_resp'                => $this->bool($row['fever_resp']        ?? 0),
                        ];
                    } elseif ($age <= 5) {
                        $data = [
                            'vitaminA'          => $this->bool($row['vitaminA']           ?? 0),
                            'childphydev'       => trim($row['childphydev']               ?? '') ?: null,
                            'childlgdev'        => trim($row['childlgdev']                ?? '') ?: null,
                            'childsocialization'=> trim($row['childsocialization']         ?? '') ?: null,
                        ];
                    } elseif ($age <= 15) {
                        $data = [
                            'phy_dev_6_15'          => $this->bool($row['phy_dev_6_15']           ?? 0),
                            'school_interest_6_15'  => trim($row['school_interest_6_15']          ?? '') ?: null,
                            'interesting_subject_6_15' => trim($row['interesting_subject_6_15']   ?? '') ?: null,
                            'hw_support_6_15'       => trim($row['hw_support_6_15']               ?? '') ?: null,
                            'allergy_infection_6_15'=> $this->bool($row['allergy_infection_6_15'] ?? 0),
                            'watch_mobile_tv_6_15'  => $this->bool($row['watch_mobile_tv_6_15']   ?? 0),
                            'digital_habit_6_15'    => trim($row['digital_habit_6_15']            ?? '') ?: null,
                        ];
                    } else {
                        // Age > 15 — child health fields do not apply
                        $this->errors[] = "Row {$rowNum}: member '{$name}' age {$age} is over 15 — no child health fields to set.";
                        continue;
                    }

                    if (!empty($data)) {
                        $member->update($data);
                        $this->imported++;
                    }
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

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
