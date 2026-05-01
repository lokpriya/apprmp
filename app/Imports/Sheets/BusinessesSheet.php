<?php

namespace App\Imports\Sheets;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\MemberBusiness;
use App\Models\Settlement;
use App\Models\Ward;
use App\Services\SurveyValueValidator;
use App\Services\UniqueCodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class BusinessesSheet implements ToCollection, WithHeadingRow, WithStartRow, SkipsEmptyRows
{
    public function startRow(): int { return 3; }

    private array $errors   = [];
    private int   $imported = 0;

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $forceWardId = null,
        private readonly ?SurveyValueValidator $validator = null,
    ) {}

    public function collection(Collection $rows): void
    {
        $codeService = app(UniqueCodeService::class);

        DB::transaction(function () use ($rows, $codeService) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;  // row 1 = header, row 2 = hints, data starts row 3
                try {
                    $this->processRow($row->toArray(), $rowNum, $codeService);
                } catch (\Throwable $e) {
                    $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        });
    }

    private function processRow(array $row, int $rowNum, UniqueCodeService $codeService): void
    {
        // ── Required: household_code ─────────────────────────────────────────
        $householdCode = trim($row['household_code'] ?? '');
        if (!$householdCode) {
            $this->errors[] = "Row {$rowNum}: household_code is required.";
            return;
        }

        $household = Household::where('unique_code', $householdCode)->first();
        if (!$household) {
            $this->errors[] = "Row {$rowNum}: Household '{$householdCode}' not found.";
            return;
        }

        // ── Required: member_name ────────────────────────────────────────────
        $memberName = trim($row['member_name'] ?? '');
        if (!$memberName) {
            $this->errors[] = "Row {$rowNum}: member_name is required.";
            return;
        }

        $member = HouseholdMember::where('household_id', $household->id)
            ->where('full_name', $memberName)
            ->first();

        if (!$member) {
            // Try partial match (trim spaces, case-insensitive)
            $member = HouseholdMember::where('household_id', $household->id)
                ->whereRaw('LOWER(full_name) = ?', [strtolower($memberName)])
                ->first();
        }

        if (!$member) {
            $this->errors[] = "Row {$rowNum}: Member '{$memberName}' not found in household '{$householdCode}'.";
            return;
        }

        // ── Check member doesn't already have a business ─────────────────────
        if (MemberBusiness::where('household_member_id', $member->id)->exists()) {
            $this->errors[] = "Row {$rowNum}: Member '{$memberName}' already has a business record.";
            return;
        }

        // ── Resolve ward ─────────────────────────────────────────────────────
        $wardId = $this->forceWardId ?? $household->ward_id;
        $ward   = Ward::find($wardId);
        if (!$ward) {
            $this->errors[] = "Row {$rowNum}: Ward not found.";
            return;
        }

        // ── Resolve settlement (optional) ────────────────────────────────────
        $settlementId   = null;
        $settlementName = trim($row['settlement_name'] ?? '');
        if ($settlementName) {
            $settlement = Settlement::where('ward_id', $wardId)
                ->where(fn($q) => $q->where('name_en', 'like', $settlementName)
                                    ->orWhere('name_np', 'like', $settlementName))
                ->first();
            $settlementId = $settlement?->id;
        }

        // ── Required: business_name ──────────────────────────────────────────
        $businessName = trim($row['business_name'] ?? '');
        if (!$businessName) {
            $this->errors[] = "Row {$rowNum}: business_name is required.";
            return;
        }

        // ── Survey option validation ──────────────────────────────────────────
        if ($this->validator) {
            $errs = $this->validator->checkFields([
                'business_type'     => trim($row['business_type']     ?? ''),
                'business_field'    => trim($row['business_field']    ?? ''),
                'business_location' => trim($row['business_location'] ?? ''),
            ], $rowNum);
            if ($errs) {
                foreach ($errs as $e) $this->errors[] = $e;
                return;
            }
        }

        // ── Generate or use provided code ────────────────────────────────────
        $code = trim($row['business_code'] ?? '');
        if (!$code) {
            $code = $codeService->generateBusinessCode($ward);
        } elseif (MemberBusiness::where('unique_code', $code)->exists()) {
            $this->errors[] = "Row {$rowNum}: Code '{$code}' already exists, skipped.";
            return;
        }

        // ── Parse place_of_registration (comma-separated) ───────────────────
        $regPlacesRaw = trim($row['place_of_registration'] ?? '');
        $regPlaces    = $regPlacesRaw
            ? array_values(array_filter(array_map('trim', explode(',', $regPlacesRaw))))
            : null;

        // ── Create the business record ───────────────────────────────────────
        MemberBusiness::create([
            'unique_code'              => $code,
            'household_member_id'      => $member->id,
            'household_id'             => $household->id,
            'ward_id'                  => $wardId,
            'settlement_id'            => $settlementId,
            'enumerator_id'            => $this->enumeratorId,
            'status'                   => 'draft',

            // Step 1
            'business_name'            => $businessName,
            'owner_father_name'        => trim($row['owner_father_name'] ?? '')        ?: null,
            'owner_grand_father_name'  => trim($row['owner_grand_father_name'] ?? '')  ?: null,
            'business_location'        => trim($row['business_location'] ?? '')         ?: null,
            'business_type'            => trim($row['business_type'] ?? '')             ?: null,
            'business_type_other'      => trim($row['business_type_other'] ?? '')       ?: null,

            // Step 2
            'business_field'           => trim($row['business_field'] ?? '')            ?: null,
            'business_field_other'     => trim($row['business_field_other'] ?? '')      ?: null,
            'business_sub_category'    => trim($row['business_sub_category'] ?? '')     ?: null,

            // Step 3
            'is_registered'            => $this->parseBool($row['is_registered'] ?? null),
            'place_of_registration'    => $regPlaces,
            'place_of_registration_other' => trim($row['place_of_registration_other'] ?? '') ?: null,
            'reg_no_municipality'      => trim($row['reg_no_municipality'] ?? '')        ?: null,
            'reg_no_home_cottage'      => trim($row['reg_no_home_cottage'] ?? '')        ?: null,
            'reg_no_company_registrar' => trim($row['reg_no_company_registrar'] ?? '')   ?: null,
            'reg_no_fcci'              => trim($row['reg_no_fcci'] ?? '')                 ?: null,
            'reg_no_govt_office'       => trim($row['reg_no_govt_office'] ?? '')          ?: null,
            'has_pan_vat'              => $this->parseBool($row['has_pan_vat'] ?? null),
            'pan_vat_number'           => trim($row['pan_vat_number'] ?? '')             ?: null,
            'paid_business_tax'        => $this->parseBool($row['paid_business_tax'] ?? null),
            'tax_amount'               => is_numeric($row['tax_amount'] ?? '') ? (float) $row['tax_amount'] : null,
            'contact_number'           => trim($row['contact_number'] ?? '')             ?: null,
        ]);

        $this->imported++;
    }

    private function parseBool(mixed $val): bool
    {
        if (is_null($val) || $val === '') return false;
        if (is_bool($val)) return $val;
        $v = strtolower(trim((string) $val));
        return in_array($v, ['1', 'yes', 'true', 'छ', 'हो']);
    }

    public function getErrors(): array  { return $this->errors; }
    public function getImportedCount(): int { return $this->imported; }
}
