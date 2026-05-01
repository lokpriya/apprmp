<?php

namespace App\Services;

use App\Models\MemberBusiness;
use App\Models\HouseholdMember;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;

class BusinessService
{
    /** Generate next unique code for a ward, e.g. BIZ-001-0001 */
    public function generateUniqueCode(int $wardId): string
    {
        $ward = Ward::find($wardId);
        $wardNum = str_pad($ward?->ward_number ?? $wardId, 3, '0', STR_PAD_LEFT);
        $prefix  = "BIZ-{$wardNum}-";

        $last = MemberBusiness::withTrashed()
            ->where('unique_code', 'like', $prefix . '%')
            ->orderByDesc('unique_code')
            ->value('unique_code');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /** Create a new business record for a member */
    public function create(array $data, int $enumeratorId): MemberBusiness
    {
        $member = HouseholdMember::with('household')->findOrFail($data['household_member_id']);

        // Mark member as involved in business
        $member->update(['involveinbusiness' => true]);

        $wardId      = $member->household->ward_id;
        $settlementId = $member->household->settlement_id;

        return DB::transaction(function () use ($data, $member, $wardId, $settlementId, $enumeratorId) {
            return MemberBusiness::create([
                'household_member_id'       => $member->id,
                'household_id'              => $member->household_id,
                'ward_id'                   => $wardId,
                'settlement_id'             => $settlementId,
                'enumerator_id'             => $enumeratorId,
                'unique_code'               => $this->generateUniqueCode($wardId),
                'status'                    => 'draft',

                // Step 1
                'business_name'             => $data['business_name'] ?? '',
                'owner_father_name'         => $data['owner_father_name'] ?? null,
                'owner_grand_father_name'   => $data['owner_grand_father_name'] ?? null,
                'business_location'         => $data['business_location'] ?? null,
                'business_type'             => $data['business_type'] ?? null,
                'business_type_other'       => $data['business_type_other'] ?? null,

                // Step 2
                'business_field'            => $data['business_field'] ?? null,
                'business_field_other'      => $data['business_field_other'] ?? null,
                'business_sub_category'     => $data['business_sub_category'] ?? null,

                // Step 3
                'is_registered'             => $data['is_registered'] ?? null,
                'place_of_registration'     => $data['place_of_registration'] ?? null,
                'place_of_registration_other'=> $data['place_of_registration_other'] ?? null,
                'reg_no_municipality'       => $data['reg_no_municipality'] ?? null,
                'reg_no_home_cottage'       => $data['reg_no_home_cottage'] ?? null,
                'reg_no_company_registrar'  => $data['reg_no_company_registrar'] ?? null,
                'reg_no_fcci'               => $data['reg_no_fcci'] ?? null,
                'reg_no_govt_office'        => $data['reg_no_govt_office'] ?? null,
                'has_pan_vat'               => $data['has_pan_vat'] ?? null,
                'pan_vat_number'            => $data['pan_vat_number'] ?? null,
                'paid_business_tax'         => $data['paid_business_tax'] ?? null,
                'tax_amount'                => $data['tax_amount'] ?? null,
                'contact_number'            => $data['contact_number'] ?? null,
            ]);
        });
    }

    /** Update an existing business record */
    public function update(MemberBusiness $business, array $data): MemberBusiness
    {
        DB::transaction(function () use ($business, $data) {
            $business->update([
                'sync_locked'               => true,
                // Step 1
                'business_name'             => $data['business_name'] ?? $business->business_name,
                'owner_father_name'         => $data['owner_father_name'] ?? null,
                'owner_grand_father_name'   => $data['owner_grand_father_name'] ?? null,
                'business_location'         => $data['business_location'] ?? null,
                'business_type'             => $data['business_type'] ?? null,
                'business_type_other'       => $data['business_type_other'] ?? null,

                // Step 2
                'business_field'            => $data['business_field'] ?? null,
                'business_field_other'      => $data['business_field_other'] ?? null,
                'business_sub_category'     => $data['business_sub_category'] ?? null,

                // Step 3
                'is_registered'             => $data['is_registered'] ?? null,
                'place_of_registration'     => $data['place_of_registration'] ?? null,
                'place_of_registration_other'=> $data['place_of_registration_other'] ?? null,
                'reg_no_municipality'       => $data['reg_no_municipality'] ?? null,
                'reg_no_home_cottage'       => $data['reg_no_home_cottage'] ?? null,
                'reg_no_company_registrar'  => $data['reg_no_company_registrar'] ?? null,
                'reg_no_fcci'               => $data['reg_no_fcci'] ?? null,
                'reg_no_govt_office'        => $data['reg_no_govt_office'] ?? null,
                'has_pan_vat'               => $data['has_pan_vat'] ?? null,
                'pan_vat_number'            => $data['pan_vat_number'] ?? null,
                'paid_business_tax'         => $data['paid_business_tax'] ?? null,
                'tax_amount'                => $data['tax_amount'] ?? null,
                'contact_number'            => $data['contact_number'] ?? null,
            ]);
        });

        return $business->fresh();
    }
}
