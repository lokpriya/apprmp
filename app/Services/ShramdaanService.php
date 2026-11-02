<?php

namespace App\Services;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ShramdaanEntry;
use App\Models\ShramdaanPassbook;
use App\Models\SurveyOption;
use App\Support\BsDate;
use App\Support\Modules;
use Illuminate\Support\Collection;

/**
 * Builds a family's passbook from two sources: who the family is, read live
 * from the survey, and what the tole coordinator has added. Nothing here writes
 * to the survey tables.
 */
class ShramdaanService
{
    /** Age today: the survey recorded age only (no birth dates), so add the years since. */
    public function currentAge(?int $surveyAge, Household $household): ?int
    {
        if ($surveyAge === null) return null;
        $since = $household->survey_date ?? $household->created_at;

        return $surveyAge + ($since ? max(0, (int) $since->diffInYears(now())) : 0);
    }

    /**
     * Living members at or above the module's minimum age, with the
     * coordinator's details attached. A member already given details stays on
     * the list even if they would now be filtered out, so nothing typed is lost.
     */
    public function members(Household $household, ?ShramdaanPassbook $passbook, int $lgId): Collection
    {
        $minAge    = (int) Modules::settings(Modules::SHRAMDAAN, $lgId)['min_age'];
        $details   = $passbook ? $passbook->memberDetails()->get()->keyBy('household_member_id') : collect();
        $relations = SurveyOption::asArray('relation_to_head', $lgId);

        return $household->members()
            ->get(['id', 'household_id', 'full_name', 'relation_to_head', 'gender', 'age', 'is_household_head', 'is_deceased', 'sort_order', 'techtraining_subject'])
            ->map(function (HouseholdMember $m) use ($household, $details, $relations) {
                $d = $details->get($m->id);
                return [
                    'id'          => $m->id,
                    'name'        => $m->full_name,
                    'age'         => $this->currentAge($m->age !== null ? (int) $m->age : null, $household),
                    'gender'      => $m->gender,
                    'relation'    => $relations[$m->relation_to_head] ?? $m->relation_to_head,
                    'is_head'     => (bool) $m->is_household_head,
                    'is_deceased' => (bool) $m->is_deceased,
                    'has_detail'  => (bool) $d,
                    'citizenship' => $d?->citizenship,
                    // pre-filled from the survey's training subject until the coordinator types one
                    'skill'       => $d?->skill ?? ($m->techtraining_subject ?: null),
                    'excluded'    => (bool) ($d?->excluded),
                ];
            })
            ->filter(fn($m) => $m['has_detail'] || (!$m['is_deceased'] && ($m['age'] === null || $m['age'] >= $minAge)))
            ->values();
    }

    /** Verified and recorded totals for one fiscal year, plus the श्रेणी it earns. */
    public function totals(ShramdaanPassbook $passbook, string $fiscalYear, int $lgId): array
    {
        $rows = $passbook->entries()->where('fiscal_year', $fiscalYear)
            ->selectRaw('status, SUM(hours) as hours, SUM(days) as days, COUNT(*) as n')
            ->groupBy('status')->get()->keyBy('status');

        $verifiedDays = round((float) ($rows[ShramdaanEntry::VERIFIED]->days ?? 0), 2);

        return [
            'fiscal_year'    => $fiscalYear,
            'verified_days'  => $verifiedDays,
            'verified_hours' => round((float) ($rows[ShramdaanEntry::VERIFIED]->hours ?? 0), 2),
            'pending_days'   => round((float) ($rows[ShramdaanEntry::RECORDED]->days ?? 0), 2),
            'pending_count'  => (int) ($rows[ShramdaanEntry::RECORDED]->n ?? 0),
            'tier'           => $this->tier($verifiedDays, $lgId),
        ];
    }

    /** Only verified days count — an unchecked entry cannot earn an incentive. */
    public function tier(float $verifiedDays, int $lgId): string
    {
        $s = Modules::settings(Modules::SHRAMDAAN, $lgId);

        return match (true) {
            $verifiedDays >= (float) $s['tier_gold']   => 'gold',
            $verifiedDays >= (float) $s['tier_silver'] => 'silver',
            $verifiedDays >= (float) $s['tier_bronze'] => 'bronze',
            default                                    => 'participant',
        };
    }

    public function currentFiscalYear(): string
    {
        return BsDate::fiscalYear(now()->toDateString());
    }

    public function daysFor(float $hours, int $lgId): float
    {
        $perDay = (float) Modules::settings(Modules::SHRAMDAAN, $lgId)['hours_per_day'];

        return round($hours / max($perDay, 1), 2);
    }
}
