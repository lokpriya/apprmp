<?php

namespace App\Imports\Sheets;

use App\Models\Household;
use App\Models\HouseholdEconomic;
use App\Services\SurveyValueValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EconomicSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
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

                    $loanSources = $this->parseArray($row['loan_sources'] ?? null);
                    $householdId = $codeMap[$code];

                    // ── Survey option validation ──────────────────────────────
                    if ($this->validator) {
                        $errs = $this->validator->checkFields([
                            'primary_income_source'    => trim($row['primary_income_source']    ?? ''),
                            'annual_income_range'       => trim($row['annual_income_range']      ?? ''),
                            'annual_expenditure_range'  => trim($row['annual_expenditure_range'] ?? ''),
                        ], $rowNum);
                        if ($errs) {
                            foreach ($errs as $e) $this->errors[] = $e;
                            continue;
                        }
                    }

                    HouseholdEconomic::updateOrCreate(
                        ['household_id' => $householdId],
                        [
                            'primary_income_source'     => trim($row['primary_income_source'] ?? '') ?: null,
                            'annual_income_range'        => trim($row['annual_income_range'] ?? '') ?: null,
                            'annual_expenditure_range'   => trim($row['annual_expenditure_range'] ?? '') ?: null,
                            'remittance_amount'          => is_numeric($row['remittance_amount'] ?? '') ? $row['remittance_amount'] : null,
                            'loan_sources'               => $loanSources ?: null,
                            'total_loan_amount'          => is_numeric($row['total_loan_amount'] ?? '') ? $row['total_loan_amount'] : null,
                            'has_bank_account'           => $this->bool($row['has_bank_account'] ?? 0),
                            'has_insurance'              => $this->bool($row['has_insurance'] ?? 0),
                            'poverty_card'               => $this->bool($row['poverty_card'] ?? 0),
                            'social_security_allowance'  => $this->bool($row['social_security_allowance'] ?? 0),
                        ]
                    );

                    // Map decision columns → survey_data.decisions (form key names)
                    $decisionsMap = [
                        'decisioninHHWork'     => trim($row['decision_household_work']       ?? ''),
                        'decisioninProperty'   => trim($row['decision_property']             ?? ''),
                        'decisioninCCommittee' => trim($row['decision_consumer_committee']   ?? ''),
                        'decisioninSMC'        => trim($row['decision_school_mgmt']          ?? ''),
                        'decisioninBusiness'   => trim($row['decision_business']             ?? ''),
                        'bank_account_owner'   => trim($row['decision_bank_account']         ?? ''),
                    ];
                    $decisionData = array_filter($decisionsMap, fn ($v) => $v !== '');

                    $occupationData = array_filter([
                        'has_foreign_employment'      => trim($row['has_foreign_employment']      ?? '') ?: null,
                        'foreign_employment_country'  => trim($row['foreign_employment_country']  ?? '') ?: null,
                    ]);

                    if ($decisionData || $occupationData) {
                        $hh = Household::find($householdId);
                        if ($hh) {
                            $sd = $hh->survey_data ?? [];
                            if ($decisionData)  $sd['decisions']  = array_merge($sd['decisions']  ?? [], $decisionData);
                            if ($occupationData) $sd['occupation'] = array_merge($sd['occupation'] ?? [], $occupationData);
                            $hh->update(['survey_data' => $sd]);
                        }
                    }

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
        return array_filter(array_map('trim', explode(',', (string)$val)));
    }

    public function getErrors(): array      { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
