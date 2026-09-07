<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocalGovernment;
use App\Models\Ward;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class PublicDataController extends Controller
{
    private const TTL = 1800; // 30 min cache

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function lg(): ?LocalGovernment
    {
        return LocalGovernment::getFirstCached();
    }

    private function wardIds(LocalGovernment $lg): array
    {
        return Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
    }

    private function respond(array $data, LocalGovernment $lg, int $total = 0): JsonResponse
    {
        return response()
            ->json([
                'meta' => [
                    'lg'               => [
                        'name_np' => $lg->name_np,
                        'name_en' => $lg->name_en,
                        'type'    => $lg->type ?? 'municipality',
                    ],
                    'generated_at'     => now()->toIso8601String(),
                    'total_records'    => $total,
                    'cache_ttl_seconds'=> self::TTL,
                ],
                'data' => $data,
            ])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET')
            ->header('Cache-Control', 'public, max-age=' . self::TTL);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => 'Local government not configured.'], 404)
            ->header('Access-Control-Allow-Origin', '*');
    }

    // ── GET /api/v1/summary ──────────────────────────────────────────────────

    public function summary(): JsonResponse
    {
        $lg = $this->lg();
        if (!$lg) return $this->notFound();

        $data = Cache::remember("api.v1.summary.{$lg->id}", self::TTL, function () use ($lg) {
            $wardIds = $this->wardIds($lg);

            $statusCounts = DB::table('households')
                ->whereIn('ward_id', $wardIds)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $pubHH = (int) ($statusCounts['published'] ?? 0);

            $pop = DB::table('household_members')
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)
                ->where('households.status', 'published')
                ->where('household_members.is_deceased', false)
                ->selectRaw('COUNT(*) as total, SUM(gender="male") as male, SUM(gender="female") as female')
                ->first();

            return [
                // Published records only, matching the institution and business counts
                // below. Draft/submitted counts expose in-progress survey state that
                // every other public surface withholds until publication.
                'households'   => [
                    'total' => $pubHH,
                ],
                'population'   => [
                    'total'  => (int) ($pop->total  ?? 0),
                    'male'   => (int) ($pop->male   ?? 0),
                    'female' => (int) ($pop->female ?? 0),
                    'avg_per_household' => $pubHH > 0
                        ? round($pop->total / $pubHH, 2)
                        : 0,
                ],
                'institutions' => DB::table('institutions')
                    ->whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
                'businesses'   => DB::table('member_businesses')
                    ->whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
                'wards'        => count($wardIds),
            ];
        });

        return $this->respond($data, $lg, $data['households']['total']);
    }

    // ── GET /api/v1/population ───────────────────────────────────────────────

    public function population(): JsonResponse
    {
        $lg = $this->lg();
        if (!$lg) return $this->notFound();

        $data = Cache::remember("api.v1.population.{$lg->id}", self::TTL, function () use ($lg) {
            $wardIds = $this->wardIds($lg);

            $base = DB::table('household_members')
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)
                ->where('households.status', 'published')
                ->where('household_members.is_deceased', false);

            $gender = (clone $base)
                ->selectRaw('gender, COUNT(*) as count')
                ->groupBy('gender')
                ->pluck('count', 'gender');

            $ageGroups = (clone $base)
                ->selectRaw("
                    SUM(age < 5)                      as age_0_4,
                    SUM(age BETWEEN 5 AND 14)         as age_5_14,
                    SUM(age BETWEEN 15 AND 24)        as age_15_24,
                    SUM(age BETWEEN 25 AND 39)        as age_25_39,
                    SUM(age BETWEEN 40 AND 59)        as age_40_59,
                    SUM(age >= 60)                    as age_60_plus,
                    COUNT(*)                          as total
                ")->first();

            $ethnicity = (clone $base)
                ->whereNotNull('ethnicity')->where('ethnicity', '!=', '')
                ->selectRaw('ethnicity, COUNT(*) as count')
                ->groupBy('ethnicity')
                ->orderByDesc('count')
                ->limit(15)
                ->get()->map(fn($r) => ['key' => $r->ethnicity, 'count' => (int) $r->count]);

            $religion = (clone $base)
                ->whereNotNull('religion')->where('religion', '!=', '')
                ->selectRaw('religion, COUNT(*) as count')
                ->groupBy('religion')
                ->orderByDesc('count')
                ->get()->map(fn($r) => ['key' => $r->religion, 'count' => (int) $r->count]);

            $literacy = (clone $base)
                ->whereNotNull('education_level')->where('education_level', '!=', '')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(LOWER(education_level) NOT IN ('illitrate','illiterate')) as literate
                ")->first();

            $total = (int) ($ageGroups->total ?? 0);

            return [
                'total'      => $total,
                'by_gender'  => [
                    'male'   => (int) ($gender['male']   ?? 0),
                    'female' => (int) ($gender['female'] ?? 0),
                    'other'  => (int) ($gender['other']  ?? 0),
                ],
                'age_groups' => [
                    '0-4'   => (int) ($ageGroups->age_0_4   ?? 0),
                    '5-14'  => (int) ($ageGroups->age_5_14  ?? 0),
                    '15-24' => (int) ($ageGroups->age_15_24 ?? 0),
                    '25-39' => (int) ($ageGroups->age_25_39 ?? 0),
                    '40-59' => (int) ($ageGroups->age_40_59 ?? 0),
                    '60+'   => (int) ($ageGroups->age_60_plus ?? 0),
                ],
                'ethnicity'        => $ethnicity,
                'religion'         => $religion,
                'literacy_pct'     => $literacy && $literacy->total > 0
                    ? round($literacy->literate / $literacy->total * 100, 1)
                    : 0,
            ];
        });

        return $this->respond($data, $lg, $data['total']);
    }

    // ── GET /api/v1/wards ────────────────────────────────────────────────────

    public function wards(): JsonResponse
    {
        $lg = $this->lg();
        if (!$lg) return $this->notFound();

        $data = Cache::remember("api.v1.wards.{$lg->id}", self::TTL, function () use ($lg) {
            $wardIds = $this->wardIds($lg);
            $wards   = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

            $hhCount = DB::table('households')
                ->whereIn('ward_id', $wardIds)->where('status', 'published')
                ->selectRaw('ward_id, COUNT(*) as n')->groupBy('ward_id')->pluck('n', 'ward_id');

            $pop = DB::table('household_members')
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
                ->where('household_members.is_deceased', false)
                ->selectRaw('households.ward_id, COUNT(*) as total, SUM(gender="male") as male, SUM(gender="female") as female')
                ->groupBy('households.ward_id')->get()->keyBy('ward_id');

            $lit = DB::table('household_members')
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
                ->where('household_members.is_deceased', false)
                ->whereNotNull('household_members.education_level')
                ->where('household_members.education_level', '!=', '')
                ->selectRaw("households.ward_id,
                    SUM(LOWER(household_members.education_level) NOT IN ('illitrate','illiterate')) as literate,
                    COUNT(*) as total")
                ->groupBy('households.ward_id')->get()->keyBy('ward_id');

            $utils = DB::table('household_utilities')
                ->join('households', 'household_utilities.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
                ->selectRaw("households.ward_id,
                    SUM(water_source IN ('Tap_public','Tap_house','covered_well')) as safe_water,
                    SUM(CAST(has_internet AS UNSIGNED)) as internet,
                    SUM(toilet_type IS NOT NULL AND toilet_type NOT IN ('no_toilet','open_field','openfield','')) as toilet,
                    COUNT(*) as total")
                ->groupBy('households.ward_id')->get()->keyBy('ward_id');

            $econ = DB::table('household_economic')
                ->join('households', 'household_economic.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)->where('households.status', 'published')
                ->selectRaw("households.ward_id, SUM(CAST(has_bank_account AS UNSIGNED)) as bank, COUNT(*) as total")
                ->groupBy('households.ward_id')->get()->keyBy('ward_id');

            return $wards->map(function ($w) use ($hhCount, $pop, $lit, $utils, $econ) {
                $p = $pop->get($w->id);
                $l = $lit->get($w->id);
                $u = $utils->get($w->id);
                $e = $econ->get($w->id);
                $hh = (int) ($hhCount->get($w->id, 0));

                return [
                    'ward_number'     => $w->ward_number,
                    'households'      => $hh,
                    'population'      => (int) ($p?->total  ?? 0),
                    'male'            => (int) ($p?->male   ?? 0),
                    'female'          => (int) ($p?->female ?? 0),
                    'literacy_pct'    => $l && $l->total > 0 ? round($l->literate / $l->total * 100, 1) : 0,
                    'safe_water_pct'  => $u && $u->total > 0 ? round($u->safe_water / $u->total * 100, 1) : 0,
                    'toilet_pct'      => $u && $u->total > 0 ? round($u->toilet    / $u->total * 100, 1) : 0,
                    'internet_pct'    => $u && $u->total > 0 ? round($u->internet  / $u->total * 100, 1) : 0,
                    'bank_account_pct'=> $e && $e->total > 0 ? round($e->bank      / $e->total * 100, 1) : 0,
                ];
            })->values();
        });

        return $this->respond(['wards' => $data], $lg, $data->count());
    }

    // ── GET /api/v1/institutions ─────────────────────────────────────────────

    public function institutions(): JsonResponse
    {
        $lg = $this->lg();
        if (!$lg) return $this->notFound();

        $data = Cache::remember("api.v1.institutions.{$lg->id}", self::TTL, function () use ($lg) {
            $wardIds = $this->wardIds($lg);

            $instTypeMap = \App\Models\SurveyOption::where('category', 'institution_type')
                ->pluck('label_ne', 'value');
            $byType = DB::table('institutions')
                ->whereIn('institutions.ward_id', $wardIds)
                ->where('institutions.status', 'published')
                ->selectRaw('institution_type as type, COUNT(*) as count')
                ->groupBy('institution_type')
                ->orderByDesc('count')
                ->get()->map(fn($r) => ['type' => $r->type, 'type_np' => $instTypeMap[$r->type] ?? $r->type, 'count' => (int) $r->count]);

            $byWard = DB::table('institutions')
                ->join('wards', 'institutions.ward_id', '=', 'wards.id')
                ->whereIn('institutions.ward_id', $wardIds)
                ->where('institutions.status', 'published')
                ->selectRaw('wards.ward_number, COUNT(*) as count')
                ->groupBy('wards.ward_number')
                ->orderBy('wards.ward_number')
                ->get()->map(fn($r) => ['ward_number' => $r->ward_number, 'count' => (int) $r->count]);

            return [
                'total'   => $byType->sum('count'),
                'by_type' => $byType,
                'by_ward' => $byWard,
            ];
        });

        return $this->respond($data, $lg, $data['total']);
    }

    // ── GET /api/v1/economics ────────────────────────────────────────────────

    public function economics(): JsonResponse
    {
        $lg = $this->lg();
        if (!$lg) return $this->notFound();

        $data = Cache::remember("api.v1.economics.{$lg->id}", self::TTL, function () use ($lg) {
            $wardIds = $this->wardIds($lg);

            $base = DB::table('household_economic')
                ->join('households', 'household_economic.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)
                ->where('households.status', 'published');

            $income = (clone $base)
                ->whereNotNull('annual_income_range')->where('annual_income_range', '!=', '')
                ->selectRaw('annual_income_range as range, COUNT(*) as count')
                ->groupBy('annual_income_range')
                ->orderByDesc('count')
                ->get()->map(fn($r) => ['range' => $r->range, 'count' => (int) $r->count]);

            $totals = (clone $base)
                ->selectRaw('COUNT(*) as total, SUM(CAST(has_bank_account AS UNSIGNED)) as bank, SUM(CAST(has_insurance AS UNSIGNED)) as insurance')
                ->first();

            $occupation = DB::table('household_members')
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->whereIn('households.ward_id', $wardIds)
                ->where('households.status', 'published')
                ->where('household_members.is_deceased', false)
                ->whereNotNull('household_members.occupation_type')
                ->where('household_members.occupation_type', '!=', '')
                ->selectRaw('occupation_type, COUNT(*) as count')
                ->groupBy('occupation_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get()->map(fn($r) => ['type' => $r->occupation_type, 'count' => (int) $r->count]);

            $total = (int) ($totals->total ?? 0);

            return [
                'total_households'    => $total,
                'bank_account_pct'    => $total > 0 ? round($totals->bank     / $total * 100, 1) : 0,
                'insurance_pct'       => $total > 0 ? round($totals->insurance/ $total * 100, 1) : 0,
                'income_distribution' => $income,
                'occupation_types'    => $occupation,
            ];
        });

        return $this->respond($data, $lg, $data['total_households']);
    }
}
