<?php

namespace App\Imports\Sheets;

use App\Models\Institution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class TypeSpecificSheet implements ToCollection, WithChunkReading, WithHeadingRow, WithStartRow, SkipsEmptyRows
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
                    $ts = array_filter([
                        // School
                        'school_level'      => trim($row['school_level'] ?? '') ?: null,
                        'management_type'   => trim($row['management_type'] ?? '') ?: null,
                        'medium'            => trim($row['medium'] ?? '') ?: null,
                        // Cooperative
                        'coop_type'         => trim($row['coop_type'] ?? '') ?: null,
                        'share_capital'     => is_numeric($row['share_capital'] ?? '') ? (float)$row['share_capital'] : null,
                        'loan_outstanding'  => is_numeric($row['loan_outstanding'] ?? '') ? (float)$row['loan_outstanding'] : null,
                        'male_members'      => is_numeric($row['male_members'] ?? '') ? (int)$row['male_members'] : null,
                        'female_members'    => is_numeric($row['female_members'] ?? '') ? (int)$row['female_members'] : null,
                        'other_members'     => is_numeric($row['other_members'] ?? '') ? (int)$row['other_members'] : null,
                        // Bank
                        'bank_type'         => trim($row['bank_type'] ?? '') ?: null,
                        'branch_count'      => is_numeric($row['branch_count'] ?? '') ? (int)$row['branch_count'] : null,
                        'total_deposits'    => is_numeric($row['total_deposits'] ?? '') ? (float)$row['total_deposits'] : null,
                        'total_loans'       => is_numeric($row['total_loans'] ?? '') ? (float)$row['total_loans'] : null,
                        // Government
                        'office_level'      => trim($row['office_level'] ?? '') ?: null,
                        'department'        => trim($row['department'] ?? '') ?: null,
                        // NGO / INGO
                        'registration_type' => trim($row['registration_type'] ?? '') ?: null,
                        'donor'             => trim($row['donor'] ?? '') ?: null,
                        'beneficiaries_m'   => is_numeric($row['beneficiaries_m'] ?? '') ? (int)$row['beneficiaries_m'] : null,
                        'beneficiaries_f'   => is_numeric($row['beneficiaries_f'] ?? '') ? (int)$row['beneficiaries_f'] : null,
                        'working_areas'     => $this->parseArr($row['working_areas'] ?? '') ?: null,
                        // Religious
                        'religion_type'     => trim($row['religion_type'] ?? '') ?: null,
                        'daily_visitors'    => is_numeric($row['daily_visitors'] ?? '') ? (int)$row['daily_visitors'] : null,
                        'has_trust'         => $this->parseBoolNull($row['has_trust'] ?? null),
                        // Market
                        'shop_count'        => is_numeric($row['shop_count'] ?? '') ? (int)$row['shop_count'] : null,
                        'goods_types'       => trim($row['goods_types'] ?? '') ?: null,
                        'market_days'       => $this->parseArr($row['market_days'] ?? '') ?: null,
                        // Industry
                        'industry_type'     => trim($row['industry_type'] ?? '') ?: null,
                        'annual_production' => is_numeric($row['annual_production'] ?? '') ? (float)$row['annual_production'] : null,
                        'male_workers'      => is_numeric($row['male_workers'] ?? '') ? (int)$row['male_workers'] : null,
                        'female_workers'    => is_numeric($row['female_workers'] ?? '') ? (int)$row['female_workers'] : null,
                        // Media
                        'media_type'        => trim($row['media_type'] ?? '') ?: null,
                        'coverage_area'     => trim($row['coverage_area'] ?? '') ?: null,
                        'frequency'         => trim($row['frequency'] ?? '') ?: null,
                        // Forest / CFUG
                        'forest_type'              => trim($row['forest_type'] ?? '') ?: null,
                        'forest_area_hectare'      => is_numeric($row['forest_area_hectare'] ?? '') ? (float)$row['forest_area_hectare'] : null,
                        'forest_covered_wards'     => trim($row['forest_covered_wards'] ?? '') ?: null,
                        'consumer_households'      => is_numeric($row['consumer_households'] ?? '') ? (int)$row['consumer_households'] : null,
                        'total_trees'              => is_numeric($row['total_trees'] ?? '') ? (int)$row['total_trees'] : null,
                        'annual_budget'            => is_numeric($row['annual_budget'] ?? '') ? (float)$row['annual_budget'] : null,
                        'annual_income'            => is_numeric($row['annual_income'] ?? '') ? (float)$row['annual_income'] : null,
                        'member_count_m'           => is_numeric($row['member_count_m'] ?? '') ? (int)$row['member_count_m'] : null,
                        'member_count_f'           => is_numeric($row['member_count_f'] ?? '') ? (int)$row['member_count_f'] : null,
                        'committee_chairperson'    => trim($row['committee_chairperson'] ?? '') ?: null,
                        'tree_types'               => $this->parseArr($row['tree_types'] ?? '') ?: null,
                        'herb_types'               => $this->parseArr($row['herb_types'] ?? '') ?: null,
                    ], fn ($v) => $v !== null);

                    if (empty($ts)) continue;

                    $inst = Institution::find($map[$code]);
                    if ($inst) {
                        $sd = $inst->survey_data ?? [];
                        $sd['typespecific'] = array_merge($sd['typespecific'] ?? [], $ts);
                        $inst->update(['survey_data' => $sd]);
                    }
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    private function parseBoolNull(mixed $val): ?bool
    {
        if ($val === null || $val === '') return null;
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
