<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;

class CompletenessScoreService
{
    /**
     * Returns ['score' => int (0-100), 'filled' => int, 'total' => int]
     * Household must be loaded with: housing, utilities, economic, members
     */
    public static function household(Household $hh): array
    {
        $filled = 0;
        $total  = 0;

        // ── Core (7) ─────────────────────────────────────────────────────────
        foreach ([
            filled($hh->gps_lat) && filled($hh->gps_lng),
            filled($hh->photo_number),
            filled($hh->respondent_name),
            filled($hh->respondent_contact),
            filled($hh->survey_date),
            (int)$hh->total_members > 0,
            filled($hh->settlement_id),
        ] as $c) { $total++; if ($c) $filled++; }

        // ── Housing (6) ──────────────────────────────────────────────────────
        $h = $hh->relationLoaded('housing') ? $hh->housing : null;
        foreach ([
            filled($h?->house_type),
            filled($h?->roof_type),
            filled($h?->wall_type),
            filled($h?->ownership_type),
            (int)($h?->total_rooms) > 0,
            filled($h?->house_condition),
        ] as $c) { $total++; if ($c) $filled++; }

        // ── Utilities (5) ────────────────────────────────────────────────────
        $u = $hh->relationLoaded('utilities') ? $hh->utilities : null;
        foreach ([
            filled($u?->water_source),
            filled($u?->toilet_type),
            filled($u?->electricity_source),
            filled($u?->cooking_fuel),
            filled($u?->solid_waste_management),
        ] as $c) { $total++; if ($c) $filled++; }

        // ── Economic (2) ─────────────────────────────────────────────────────
        $e = $hh->relationLoaded('economic') ? $hh->economic : null;
        foreach ([
            filled($e?->primary_income_source),
            filled($e?->annual_income_range),
        ] as $c) { $total++; if ($c) $filled++; }

        // ── Members (2) ──────────────────────────────────────────────────────
        $members = $hh->relationLoaded('members') ? $hh->members : collect();
        $total += 2;
        if ($members->isNotEmpty()) $filled++;
        if ((int)$hh->total_members > 0 && $members->count() >= (int)$hh->total_members) $filled++;

        return self::result($filled, $total);
    }

    /**
     * Institution must be loaded with: infrastructure
     */
    public static function institution(Institution $inst): array
    {
        $filled = 0;
        $total  = 0;

        // ── Core (7) ─────────────────────────────────────────────────────────
        foreach ([
            filled($inst->gps_lat) && filled($inst->gps_lng),
            filled($inst->photo_number),
            filled($inst->name_np) || filled($inst->name_en),
            filled($inst->institution_type),
            filled($inst->ward_id),
            filled($inst->settlement_id),
            filled($inst->survey_date),
        ] as $c) { $total++; if ($c) $filled++; }

        // ── Identity (4) ─────────────────────────────────────────────────────
        foreach ([
            filled($inst->established_date),
            filled($inst->ownership_type),
            filled($inst->registration_number),
            filled($inst->respondent_name),
        ] as $c) { $total++; if ($c) $filled++; }

        // ── Infrastructure (6) ───────────────────────────────────────────────
        $infra = $inst->relationLoaded('infrastructure') ? $inst->infrastructure : null;
        foreach ([
            filled($infra?->condition),
            (int)($infra?->total_rooms) > 0,
            !is_null($infra?->getRawOriginal('has_electricity')),
            !is_null($infra?->getRawOriginal('has_water')),
            !is_null($infra?->getRawOriginal('has_toilet')),
            filled($infra?->total_area),
        ] as $c) { $total++; if ($c) $filled++; }

        return self::result($filled, $total);
    }

    /**
     * MemberBusiness — no extra relations needed
     */
    public static function business(MemberBusiness $biz): array
    {
        $filled = 0;
        $total  = 0;

        // ── Core (5) ─────────────────────────────────────────────────────────
        foreach ([
            filled($biz->business_name),
            filled($biz->ward_id),
            filled($biz->settlement_id),
            filled($biz->business_type),
            filled($biz->business_field),
        ] as $c) { $total++; if ($c) $filled++; }

        // ── Details (5) ──────────────────────────────────────────────────────
        foreach ([
            filled($biz->business_location),
            filled($biz->contact_number),
            !is_null($biz->getRawOriginal('is_registered')),
            !is_null($biz->getRawOriginal('has_pan_vat')),
            filled($biz->business_sub_category),
        ] as $c) { $total++; if ($c) $filled++; }

        return self::result($filled, $total);
    }

    /** @deprecated use ::household() */
    public static function score(Household $hh): array
    {
        return self::household($hh);
    }

    private static function result(int $filled, int $total): array
    {
        $pct = $total > 0 ? (int)round($filled / $total * 100) : 0;
        return ['score' => $pct, 'filled' => $filled, 'total' => $total];
    }
}
