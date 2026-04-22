<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Models\Ward;

class UniqueCodeService
{
    public function generateHouseholdCode(Ward $ward): string
    {
        $lgCode = str_pad($ward->local_government_id, 4, '0', STR_PAD_LEFT);
        $wardNum = str_pad($ward->ward_number, 2, '0', STR_PAD_LEFT);
        $prefix = "HH-{$lgCode}-W{$wardNum}-";

        $lastCode = Household::withTrashed()
            ->where('unique_code', 'like', "{$prefix}%")
            ->orderByDesc('unique_code')
            ->value('unique_code');

        $nextNum = $lastCode ? (int) substr($lastCode, -5) + 1 : 1;
        return $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    public function generateInstitutionCode(Ward $ward): string
    {
        $lgCode = str_pad($ward->local_government_id, 4, '0', STR_PAD_LEFT);
        $wardNum = str_pad($ward->ward_number, 2, '0', STR_PAD_LEFT);
        $prefix = "IN-{$lgCode}-W{$wardNum}-";

        $lastCode = Institution::withTrashed()
            ->where('unique_code', 'like', "{$prefix}%")
            ->orderByDesc('unique_code')
            ->value('unique_code');

        $nextNum = $lastCode ? (int) substr($lastCode, -5) + 1 : 1;
        return $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    public function generateBusinessCode(Ward $ward): string
    {
        $lgCode  = str_pad($ward->local_government_id, 4, '0', STR_PAD_LEFT);
        $wardNum = str_pad($ward->ward_number, 2, '0', STR_PAD_LEFT);
        $prefix  = "BZ-{$lgCode}-W{$wardNum}-";

        $lastCode = MemberBusiness::withTrashed()
            ->where('unique_code', 'like', "{$prefix}%")
            ->orderByDesc('unique_code')
            ->value('unique_code');

        $nextNum = $lastCode ? (int) substr($lastCode, -5) + 1 : 1;
        return $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }
}
