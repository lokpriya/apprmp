<?php

namespace App\Services;

use App\Models\Household;
use Illuminate\Support\Collection;

class DataQualityService
{
    // Flag definitions: key → [label_np, icon, severity(warning|danger)]
    public const FLAG_META = [
        'too_many_members'       => ['सदस्य धेरै (२५+)',         'fa-solid fa-users',              'warning'],
        'age_over_100'           => ['उमेर १०० भन्दा बढी',       'fa-solid fa-person-cane',        'danger'],
        'multiple_heads'         => ['दोहोरो घरमुली',             'fa-solid fa-user-group',         'danger'],
        'no_household_head'      => ['घरमुली तोकिएको छैन',       'fa-solid fa-user-slash',         'warning'],
        'member_count_mismatch'  => ['सदस्य संख्या मेल खाँदैन', 'fa-solid fa-triangle-exclamation','warning'],
        'missing_gps'            => ['GPS छैन',                   'fa-solid fa-location-dot',       'danger'],
        'no_members'             => ['सदस्य छैन',                 'fa-solid fa-users',              'danger'],
    ];

    /**
     * Compute all quality flags for a household whose 'members' relation is already loaded.
     * Returns an assoc array of flag_key => bool.
     */
    public static function flags(Household $hh): array
    {
        $members = $hh->relationLoaded('members') ? $hh->members : collect();
        $living  = $members->where('is_deceased', false);

        return [
            'too_many_members'      => $living->count() > 25,
            'age_over_100'          => $living->contains(fn ($m) => (int) $m->age > 100),
            'multiple_heads'        => $living->where('is_household_head', true)->count() > 1,
            'no_household_head'     => $living->isNotEmpty()
                                        && $living->where('is_household_head', true)->isEmpty(),
            'member_count_mismatch' => $hh->total_members > 0
                                        && abs($hh->total_members - $living->count()) > 2,
            'missing_gps'           => !filled($hh->gps_lat) || !filled($hh->gps_lng),
            'no_members'            => $members->isEmpty(),
        ];
    }

    /**
     * True when any flag is raised.
     */
    public static function hasAnyFlag(Household $hh): bool
    {
        return in_array(true, self::flags($hh), true);
    }

    /**
     * Apply a "has at least one data quality issue" scope to an already-ward-filtered builder.
     * Uses denormalised columns and subqueries that MariaDB can use indexes on.
     */
    public static function applyFlaggedScope(\Illuminate\Database\Eloquent\Builder $q): void
    {
        $q->where(function ($outer) {
            // Too many members (denormalised column — fast)
            $outer->where('total_members', '>', 25)
                  // No GPS
                  ->orWhere(fn ($q) => $q->whereNull('gps_lat')->orWhereNull('gps_lng'))
                  // No members at all
                  ->orWhereDoesntHave('members')
                  // Member age > 100
                  ->orWhereHas('members', fn ($m) => $m->where('is_deceased', false)->where('age', '>', 100))
                  // Multiple household heads
                  ->orWhere(fn ($q) => $q->whereHas(
                      'members',
                      fn ($m) => $m->where('is_household_head', true)->where('is_deceased', false),
                      '>',
                      1
                  ))
                  // No household head, despite having living members
                  ->orWhere(fn ($q) => $q
                      ->whereHas('members', fn ($m) => $m->where('is_deceased', false))
                      ->whereDoesntHave('members', fn ($m) => $m
                          ->where('is_deceased', false)
                          ->where('is_household_head', true)))
                  // Declared member count disagrees with the actual living members by > 2
                  ->orWhere(fn ($q) => $q
                      ->where('total_members', '>', 0)
                      ->whereRaw('ABS(total_members - (
                          SELECT COUNT(*) FROM household_members hm
                          WHERE hm.household_id = households.id AND hm.is_deceased = 0
                      )) > 2'));
        });
    }
}
