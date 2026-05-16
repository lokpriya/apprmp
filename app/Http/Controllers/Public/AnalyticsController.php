<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdHousing;
use App\Models\HouseholdMember;
use App\Models\MemberBusiness;
use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\LocalGovernment;
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    private function defaultLg(): ?LocalGovernment
    {
        // 10-minute cache — busted by SettingsController on general/map save
        return Cache::remember('active_lg_full', 600, fn () =>
            LocalGovernment::with(['district.province'])->where('is_active', true)->first()
                ?? LocalGovernment::with(['district.province'])->first()
        );
    }

    private function lgProps(LocalGovernment $lg): array
    {
        return [
            'id'               => $lg->id,
            'name_en'          => $lg->name_en,
            'name_np'          => $lg->name_np,
            'logo_url'         => $lg->logo && file_exists(storage_path('app/public/' . $lg->logo))
                                    ? Storage::url($lg->logo)
                                    : null,
            'province'         => $lg->district?->province?->name_en,
            'district'         => $lg->district?->name_en,
            'established_year' => $lg->established_date?->format('Y'),
            'map_config'       => $lg->map_config ?? [],
        ];
    }

    public function index(Request $request)
    {
        $lg = $this->defaultLg();

        if (!$lg) {
            return Inertia::render('Public/Dashboard', [
                'lg'                 => null,
                'wards'              => [],
                'settlements'        => [],
                'stats'              => ['total_households' => 0, 'total_members' => 0, 'total_institutions' => 0, 'total_wards' => 0],
                'genderData'         => [],
                'ageData'            => [],
                'pyramidData'        => [],
                'ethnicityData'      => [],
                'religionData'       => [],
                'motherTongueData'   => [],
                'institutionData'    => [],
                'wardSummary'        => [],
                'selectedWardId'     => null,
                'selectedSettlementId' => null,
                'profileFilledCount' => 0,
            ]);
        }

        $allWardIds           = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $selectedWardId       = $request->integer('ward_id') ?: null;
        $selectedSettlementId = $request->integer('settlement_id') ?: null;

        if ($selectedWardId && !in_array($selectedWardId, $allWardIds)) {
            $selectedWardId = null;
        }
        if ($selectedSettlementId && $selectedWardId) {
            $valid = Settlement::where('id', $selectedSettlementId)
                ->where('ward_id', $selectedWardId)->exists();
            if (!$valid) $selectedSettlementId = null;
        }

        $activeWardIds = $selectedWardId ? [$selectedWardId] : $allWardIds;

        $hhBase     = fn($q) => $q->whereIn('ward_id', $activeWardIds)
            ->where('status', 'published')
            ->when($selectedSettlementId, fn($q2) => $q2->where('settlement_id', $selectedSettlementId));

        $stats = [
            'total_households'   => Household::whereIn('ward_id', $activeWardIds)
                ->where('status', 'published')
                ->when($selectedSettlementId, fn($q) => $q->where('settlement_id', $selectedSettlementId))
                ->count(),
            'total_members'      => HouseholdMember::alive()->whereHas('household', $hhBase)->count(),
            'total_institutions' => Institution::whereIn('ward_id', $activeWardIds)
                ->where('status', 'published')
                ->when($selectedSettlementId, fn($q) => $q->where('settlement_id', $selectedSettlementId))
                ->count(),
            'total_wards'        => count($allWardIds),
        ];

        $genderData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('gender')
            ->select('gender', DB::raw('count(*) as count'))
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        $ageData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('age')
            ->select(DB::raw("
                CASE
                    WHEN age < 5  THEN '0-4'
                    WHEN age < 15 THEN '5-14'
                    WHEN age < 25 THEN '15-24'
                    WHEN age < 40 THEN '25-39'
                    WHEN age < 60 THEN '40-59'
                    ELSE '60+'
                END as age_group"), DB::raw('count(*) as count'))
            ->groupBy('age_group')
            ->orderBy('age_group')
            ->pluck('count', 'age_group')
            ->toArray();

        // Population pyramid — 5-year age bands × gender (oldest first for top-to-bottom chart display)
        $pyramidBands = ['80+','75-79','70-74','65-69','60-64','55-59','50-54','45-49','40-44','35-39','30-34','25-29','20-24','15-19','10-14','5-9','0-4'];
        $rawPyramid = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('age')->whereNotNull('gender')
            ->select(DB::raw("
                CASE
                    WHEN age BETWEEN 0  AND 4  THEN '0-4'
                    WHEN age BETWEEN 5  AND 9  THEN '5-9'
                    WHEN age BETWEEN 10 AND 14 THEN '10-14'
                    WHEN age BETWEEN 15 AND 19 THEN '15-19'
                    WHEN age BETWEEN 20 AND 24 THEN '20-24'
                    WHEN age BETWEEN 25 AND 29 THEN '25-29'
                    WHEN age BETWEEN 30 AND 34 THEN '30-34'
                    WHEN age BETWEEN 35 AND 39 THEN '35-39'
                    WHEN age BETWEEN 40 AND 44 THEN '40-44'
                    WHEN age BETWEEN 45 AND 49 THEN '45-49'
                    WHEN age BETWEEN 50 AND 54 THEN '50-54'
                    WHEN age BETWEEN 55 AND 59 THEN '55-59'
                    WHEN age BETWEEN 60 AND 64 THEN '60-64'
                    WHEN age BETWEEN 65 AND 69 THEN '65-69'
                    WHEN age BETWEEN 70 AND 74 THEN '70-74'
                    WHEN age BETWEEN 75 AND 79 THEN '75-79'
                    ELSE '80+'
                END as age_group
            "), 'gender', DB::raw('count(*) as count'))
            ->groupBy('age_group', 'gender')
            ->get()
            ->groupBy('age_group');

        $pyramidData = collect($pyramidBands)->map(fn($g) => [
            'age_group' => $g,
            'male'      => $rawPyramid->get($g, collect())->firstWhere('gender', 'male')?->count  ?? 0,
            'female'    => $rawPyramid->get($g, collect())->firstWhere('gender', 'female')?->count ?? 0,
            'other'     => $rawPyramid->get($g, collect())->firstWhere('gender', 'other')?->count  ?? 0,
        ])->values()->toArray();

        $ethnicityData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('ethnicity')->where('ethnicity', '!=', '')
            ->select('ethnicity', DB::raw('count(*) as count'))
            ->groupBy('ethnicity')
            ->orderByDesc('count')
            ->pluck('count', 'ethnicity')
            ->toArray();

        $religionData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('religion')->where('religion', '!=', '')
            ->select('religion', DB::raw('count(*) as count'))
            ->groupBy('religion')
            ->orderByDesc('count')
            ->pluck('count', 'religion')
            ->toArray();

        $motherTongueData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('mother_tongue')->where('mother_tongue', '!=', '')
            ->select('mother_tongue', DB::raw('count(*) as count'))
            ->groupBy('mother_tongue')
            ->orderByDesc('count')
            ->pluck('count', 'mother_tongue')
            ->toArray();

        $institutionData = Institution::whereIn('ward_id', $activeWardIds)
            ->where('status', 'published')
            ->when($selectedSettlementId, fn($q) => $q->where('settlement_id', $selectedSettlementId))
            ->join('institution_types', 'institutions.institution_type_id', '=', 'institution_types.id')
            ->select('institution_types.name_np as label_ne', 'institution_types.name_en as label_en', DB::raw('count(*) as count'))
            ->groupBy('institution_types.name_np', 'institution_types.name_en')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['label_ne' => $r->label_ne, 'label_en' => $r->label_en, 'count' => (int)$r->count])
            ->values()->toArray();

        $wards = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $settlements = $selectedWardId
            ? Settlement::where('ward_id', $selectedWardId)->orderBy('name_en')->get()
                ->map(fn($s) => ['id' => $s->id, 'name_en' => $s->name_en, 'name_np' => $s->name_np])
                ->values()
            : collect();

        // Ward summary (kept lightweight — used by choropleth map)
        $wardHouseholds = DB::table('households')
            ->whereIn('ward_id', $allWardIds)->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as hh'))->groupBy('ward_id')->pluck('hh', 'ward_id');
        $wardGender = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->select('households.ward_id', 'household_members.gender', DB::raw('count(*) as cnt'))
            ->groupBy('households.ward_id', 'household_members.gender')->get()->groupBy('ward_id');
        $wardSummary = $wards->map(fn($ward) => [
            'ward_id'     => $ward->id,
            'ward_number' => $ward->ward_number,
            'households'  => $wardHouseholds->get($ward->id, 0),
            'male'        => $wardGender->get($ward->id, collect())->firstWhere('gender', 'male')?->cnt ?? 0,
            'female'      => $wardGender->get($ward->id, collect())->firstWhere('gender', 'female')?->cnt ?? 0,
            'total'       => $wardGender->get($ward->id, collect())->sum('cnt'),
        ])->values();

        // ── Key metrics ────────────────────────────────────────────────
        $litRow = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('is_literate')
            ->select(DB::raw('SUM(CASE WHEN is_literate=1 THEN 1 ELSE 0 END) as lit'), DB::raw('count(*) as tot'))
            ->first();
        $litRate = ($litRow && $litRow->tot > 0) ? round($litRow->lit / $litRow->tot * 100, 1) : 0;

        $bankRow = DB::table('household_economic')
            ->join('households', 'household_economic.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $activeWardIds)->where('households.status', 'published')
            ->when($selectedSettlementId, fn($q) => $q->where('households.settlement_id', $selectedSettlementId))
            ->select(DB::raw('SUM(CASE WHEN has_bank_account=1 THEN 1 ELSE 0 END) as has_bank'), DB::raw('count(*) as tot'))
            ->first();
        $bankPct = ($bankRow && $bankRow->tot > 0) ? round($bankRow->has_bank / $bankRow->tot * 100, 1) : 0;

        $maleCount   = $genderData['male']   ?? 0;
        $femaleCount = $genderData['female'] ?? 0;
        $keyMetrics  = [
            'literacy_rate'    => $litRate,
            'avg_hh_size'      => $stats['total_households'] > 0 ? round($stats['total_members'] / $stats['total_households'], 1) : 0,
            'sex_ratio'        => $femaleCount > 0 ? round($maleCount / $femaleCount * 100) : 0,
            'bank_account_pct' => $bankPct,
        ];

        // ── Survey option labels (Nepali + English) for all chart datasets ─
        $surveyLabels = SurveyOption::whereIn('category', [
            'water_source', 'toilet_type', 'electricity_source', 'cooking_fuel',
            'income_source', 'occupation_type', 'education_level', 'business_type',
        ])->select('category', 'value', 'label_ne', 'label_en')
            ->get()->groupBy('category')->map(fn($g) => $g->keyBy('value'));

        $attachLabels = function (array $rows, string $cat) use ($surveyLabels): array {
            $map = $surveyLabels->get($cat, collect());
            return array_map(function ($row) use ($map) {
                $opt = $map->get($row['value'] ?? '');
                $row['label_ne'] = $opt?->label_ne ?? null;
                $row['label_en'] = $opt?->label_en ?? null;
                return $row;
            }, $rows);
        };

        // ── Basic services ─────────────────────────────────────────────
        $waterData       = $attachLabels($this->utilityBreakdown($activeWardIds, $selectedSettlementId, 'water_source'), 'water_source');
        $toiletData      = $attachLabels($this->utilityBreakdown($activeWardIds, $selectedSettlementId, 'toilet_type'), 'toilet_type');
        $electricityData = $attachLabels($this->utilityBreakdown($activeWardIds, $selectedSettlementId, 'electricity_source'), 'electricity_source');
        $cookingData     = $attachLabels($this->utilityBreakdown($activeWardIds, $selectedSettlementId, 'cooking_fuel'), 'cooking_fuel');

        // ── Economy ────────────────────────────────────────────────────
        $incomeData = $attachLabels(DB::table('household_economic')
            ->join('households', 'household_economic.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $activeWardIds)->where('households.status', 'published')
            ->when($selectedSettlementId, fn($q) => $q->where('households.settlement_id', $selectedSettlementId))
            ->whereNotNull('household_economic.primary_income_source')
            ->where('household_economic.primary_income_source', '!=', '')
            ->select('household_economic.primary_income_source as value', DB::raw('count(*) as count'))
            ->groupBy('value')->orderByDesc('count')->get()
            ->map(fn($r) => ['value' => $r->value, 'count' => (int)$r->count])->toArray(), 'income_source');

        $occupationData = $attachLabels(HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('occupation_type')->where('occupation_type', '!=', '')
            ->select('occupation_type as value', DB::raw('count(*) as count'))
            ->groupBy('value')->orderByDesc('count')->get()
            ->map(fn($r) => ['value' => $r->value, 'count' => (int)$r->count])->toArray(), 'occupation_type');

        // ── Education ──────────────────────────────────────────────────
        $educationData = $attachLabels(HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('education_level')->where('education_level', '!=', '')
            ->select('education_level as value', DB::raw('count(*) as count'))
            ->groupBy('value')->orderByDesc('count')->get()
            ->map(fn($r) => ['value' => $r->value, 'count' => (int)$r->count])->toArray(), 'education_level');

        $litByGender = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('gender')->whereNotNull('is_literate')
            ->select('gender', DB::raw('SUM(CASE WHEN is_literate=1 THEN 1 ELSE 0 END) as lit'), DB::raw('count(*) as tot'))
            ->groupBy('gender')->get()
            ->map(fn($r) => ['gender' => $r->gender, 'lit' => (int)$r->lit, 'tot' => (int)$r->tot])->toArray();

        // ── Businesses ─────────────────────────────────────────────────
        $businessData = $attachLabels(DB::table('member_businesses')
            ->join('households', 'member_businesses.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $activeWardIds)->where('households.status', 'published')
            ->when($selectedSettlementId, fn($q) => $q->where('households.settlement_id', $selectedSettlementId))
            ->whereNotNull('member_businesses.business_type')->where('member_businesses.business_type', '!=', '')
            ->select('member_businesses.business_type as value', DB::raw('count(*) as count'))
            ->groupBy('value')->orderByDesc('count')->limit(8)->get()
            ->map(fn($r) => ['value' => $r->value, 'count' => (int)$r->count])->toArray(), 'business_type');

        // ── Migration summary (abroad / internal) ──────────────────────
        $migCounts = DB::table('member_migration')
            ->join('household_members', 'member_migration.member_id', '=', 'household_members.id')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $activeWardIds)->where('households.status', 'published')
            ->when($selectedSettlementId, fn($q) => $q->where('households.settlement_id', $selectedSettlementId))
            ->where('household_members.is_deceased', false)
            ->whereIn('member_migration.migration_status', ['abroad', 'internal'])
            ->select('member_migration.migration_status', DB::raw('count(*) as cnt'))
            ->groupBy('member_migration.migration_status')
            ->pluck('cnt', 'migration_status');

        $migrationData = [
            'abroad'   => (int) ($migCounts->get('abroad',   0)),
            'internal' => (int) ($migCounts->get('internal', 0)),
        ];

        // ── Training participation ─────────────────────────────────────
        $trainRow = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->select(
                DB::raw('SUM(CASE WHEN vocational_training=1 THEN 1 ELSE 0 END) as voc'),
                DB::raw('SUM(CASE WHEN techtraining=1 THEN 1 ELSE 0 END) as tech'),
                DB::raw('count(*) as tot')
            )->first();

        $trainTot  = $trainRow?->tot ?? 0;
        $trainingData = [
            'vocational_count' => (int) ($trainRow?->voc  ?? 0),
            'technical_count'  => (int) ($trainRow?->tech ?? 0),
            'total'            => (int) $trainTot,
            'vocational_pct'   => $trainTot > 0 ? round($trainRow->voc  / $trainTot * 100, 1) : 0,
            'technical_pct'    => $trainTot > 0 ? round($trainRow->tech / $trainTot * 100, 1) : 0,
        ];

        // ── Decision-making breakdown (survey_data→decisions JSON) ─────
        $decisionKeys = [
            'decisioninHHWork'     => 'घरेलु काम',
            'decisioninProperty'   => 'सम्पत्ति',
            'decisioninCCommittee' => 'उपभोक्ता समिति',
            'decisioninSMC'        => 'विद्यालय व्यवस्थापन',
            'decisioninBusiness'   => 'व्यवसाय',
            'bank_account_owner'   => 'बैंक खाता',
        ];
        $decisionData = [];
        foreach ($decisionKeys as $key => $label) {
            $path = "$.decisions.{$key}";
            $rows = DB::table('households')
                ->whereIn('ward_id', $activeWardIds)->where('status', 'published')
                ->when($selectedSettlementId, fn($q) => $q->where('settlement_id', $selectedSettlementId))
                ->selectRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?))) as owner, count(*) as count', [$path])
                ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) IS NOT NULL', [$path])
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) NOT IN ('null', '')", [$path])
                ->groupBy('owner')
                ->pluck('count', 'owner');
            if ($rows->sum() > 0) {
                $decisionData[] = [
                    'key'    => $key,
                    'label'  => $label,
                    'male'   => (int) ($rows->get('male',   0)),
                    'female' => (int) ($rows->get('female', 0)),
                    'both'   => (int) ($rows->get('both',   0)),
                    'other'  => (int) ($rows->get('other',  0)),
                ];
            }
        }

        $profileContent  = $lg->profile_content ?? [];
        $profileSections = LocalGovernment::profileSections();
        $profileFilledCount = collect($profileSections)->filter(fn($l, $k) => !empty($profileContent[$k]))->count();

        return Inertia::render('Public/Dashboard', [
            'lg'                  => $this->lgProps($lg),
            'wards'               => $wards->map(fn($w) => ['id' => $w->id, 'ward_number' => $w->ward_number])->values(),
            'settlements'         => $settlements,
            'stats'               => $stats,
            'genderData'          => $genderData,
            'ageData'             => $ageData,
            'pyramidData'         => $pyramidData,
            'ethnicityData'       => $ethnicityData,
            'religionData'        => $religionData,
            'motherTongueData'    => $motherTongueData,
            'institutionData'     => $institutionData,
            'wardSummary'         => $wardSummary,
            'keyMetrics'          => $keyMetrics,
            'waterData'           => $waterData,
            'toiletData'          => $toiletData,
            'electricityData'     => $electricityData,
            'cookingData'         => $cookingData,
            'incomeData'          => $incomeData,
            'occupationData'      => $occupationData,
            'educationData'       => $educationData,
            'litByGender'         => $litByGender,
            'businessData'        => $businessData,
            'migrationData'       => $migrationData,
            'trainingData'        => $trainingData,
            'decisionData'        => $decisionData,
            'selectedWardId'      => $selectedWardId,
            'selectedSettlementId' => $selectedSettlementId,
            'profileFilledCount'  => $profileFilledCount,
            'welcomeConfig'       => $lg->welcome_config ?? [],
        ]);
    }

    // ── Utility breakdown helper ──────────────────────────────────────────
    private static array $allowedUtilityColumns = [
        'water_source', 'toilet_type', 'sanitation_type', 'electricity_source',
        'cooking_fuel', 'solid_waste_management', 'internet_type', 'vehicle', 'handwashing',
    ];

    private function utilityBreakdown(array $wardIds, ?int $settlementId, string $column): array
    {
        if (!in_array($column, self::$allowedUtilityColumns, true)) {
            return [];
        }

        return DB::table('household_utilities')
            ->join('households', 'household_utilities.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wardIds)
            ->where('households.status', 'published')
            ->when($settlementId, fn($q) => $q->where('households.settlement_id', $settlementId))
            ->whereNotNull("household_utilities.{$column}")
            ->where("household_utilities.{$column}", '!=', '')
            ->select("household_utilities.{$column} as value", DB::raw('count(*) as count'))
            ->groupBy('value')->orderByDesc('count')->get()
            ->map(fn($r) => ['value' => $r->value, 'count' => (int)$r->count])->toArray();
    }

    // ── Population Pyramid page ───────────────────────────────────────────
    public function populationPyramid(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return redirect()->route('public.dashboard');

        $allWardIds     = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $selectedWardId = $request->integer('ward_id') ?: null;
        if ($selectedWardId && !in_array($selectedWardId, $allWardIds)) $selectedWardId = null;
        $activeWardIds  = $selectedWardId ? [$selectedWardId] : $allWardIds;
        $wards          = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $hhBase = fn($q) => $q->whereIn('ward_id', $activeWardIds)->where('status', 'published');

        $bands = ['80+','75-79','70-74','65-69','60-64','55-59','50-54','45-49','40-44','35-39','30-34','25-29','20-24','15-19','10-14','5-9','0-4'];
        $raw   = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('age')->whereNotNull('gender')
            ->select(DB::raw("
                CASE
                    WHEN age BETWEEN 0  AND 4  THEN '0-4'
                    WHEN age BETWEEN 5  AND 9  THEN '5-9'
                    WHEN age BETWEEN 10 AND 14 THEN '10-14'
                    WHEN age BETWEEN 15 AND 19 THEN '15-19'
                    WHEN age BETWEEN 20 AND 24 THEN '20-24'
                    WHEN age BETWEEN 25 AND 29 THEN '25-29'
                    WHEN age BETWEEN 30 AND 34 THEN '30-34'
                    WHEN age BETWEEN 35 AND 39 THEN '35-39'
                    WHEN age BETWEEN 40 AND 44 THEN '40-44'
                    WHEN age BETWEEN 45 AND 49 THEN '45-49'
                    WHEN age BETWEEN 50 AND 54 THEN '50-54'
                    WHEN age BETWEEN 55 AND 59 THEN '55-59'
                    WHEN age BETWEEN 60 AND 64 THEN '60-64'
                    WHEN age BETWEEN 65 AND 69 THEN '65-69'
                    WHEN age BETWEEN 70 AND 74 THEN '70-74'
                    WHEN age BETWEEN 75 AND 79 THEN '75-79'
                    ELSE '80+'
                END as age_group
            "), 'gender', DB::raw('count(*) as count'))
            ->groupBy('age_group', 'gender')->get()->groupBy('age_group');

        $pyramidData = collect($bands)->map(fn($g) => [
            'age_group' => $g,
            'male'      => $raw->get($g, collect())->firstWhere('gender', 'male')?->count  ?? 0,
            'female'    => $raw->get($g, collect())->firstWhere('gender', 'female')?->count ?? 0,
            'other'     => $raw->get($g, collect())->firstWhere('gender', 'other')?->count  ?? 0,
        ])->values()->toArray();

        $totalMembers = HouseholdMember::alive()->whereHas('household', $hhBase)->count();
        $genderData   = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('gender')->select('gender', DB::raw('count(*) as count'))
            ->groupBy('gender')->pluck('count', 'gender')->toArray();

        return Inertia::render('Public/Reports/PopulationPyramid', [
            'lg'             => $this->lgProps($lg),
            'wards'          => $wards->map(fn($w) => ['id' => $w->id, 'ward_number' => $w->ward_number])->values(),
            'selectedWardId' => $selectedWardId,
            'pyramidData'    => $pyramidData,
            'totalMembers'   => $totalMembers,
            'genderData'     => $genderData,
        ]);
    }

    // ── Ward Summary page ─────────────────────────────────────────────────
    public function wardSummaryPage(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return redirect()->route('public.dashboard');

        $allWardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $wardHouseholds = DB::table('households')
            ->whereIn('ward_id', $allWardIds)->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as hh'))->groupBy('ward_id')->pluck('hh', 'ward_id');

        $wardGender = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->select('households.ward_id', 'household_members.gender', DB::raw('count(*) as cnt'))
            ->groupBy('households.ward_id', 'household_members.gender')->get()->groupBy('ward_id');

        $wardInstitutions = DB::table('institutions')
            ->whereIn('ward_id', $allWardIds)->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as cnt'))->groupBy('ward_id')->pluck('cnt', 'ward_id');

        $wardBusinesses = DB::table('member_businesses')
            ->whereIn('ward_id', $allWardIds)->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as cnt'))->groupBy('ward_id')->pluck('cnt', 'ward_id');

        $wardLiteracy = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->select('households.ward_id',
                DB::raw('SUM(CASE WHEN household_members.is_literate = 1 THEN 1 ELSE 0 END) as literate'),
                DB::raw('count(*) as total'))
            ->groupBy('households.ward_id')->get()->keyBy('ward_id');

        $wardSummary = $wards->map(function ($ward) use (
            $wardHouseholds, $wardGender, $wardInstitutions, $wardBusinesses, $wardLiteracy
        ) {
            $lit = $wardLiteracy->get($ward->id);
            return [
                'ward_id'        => $ward->id,
                'ward_number'    => $ward->ward_number,
                'households'     => $wardHouseholds->get($ward->id, 0),
                'male'           => $wardGender->get($ward->id, collect())->firstWhere('gender', 'male')?->cnt  ?? 0,
                'female'         => $wardGender->get($ward->id, collect())->firstWhere('gender', 'female')?->cnt ?? 0,
                'other'          => $wardGender->get($ward->id, collect())->firstWhere('gender', 'other')?->cnt  ?? 0,
                'total'          => $wardGender->get($ward->id, collect())->sum('cnt'),
                'institutions'   => $wardInstitutions->get($ward->id, 0),
                'businesses'     => $wardBusinesses->get($ward->id, 0),
                'literate'       => (int) ($lit?->literate ?? 0),
                'literate_total' => (int) ($lit?->total    ?? 0),
            ];
        })->values();

        return Inertia::render('Public/Reports/WardSummary', [
            'lg'          => $this->lgProps($lg),
            'wardSummary' => $wardSummary,
        ]);
    }

    // ── Ward Profile page (comprehensive per-ward printable report) ───────────
    public function wardProfile(Request $request, $wardId)
    {
        $lg = $this->defaultLg();
        if (!$lg) return redirect()->route('public.dashboard');

        $ward = Ward::findOrFail($wardId);
        if ($ward->local_government_id !== $lg->id) abort(404);

        $wid    = [$ward->id];
        $hhBase = fn($q) => $q->whereIn('ward_id', $wid)->where('status', 'published');

        // ── 1. Overview ─────────────────────────────────────────────────────
        $hhCount = DB::table('households')->whereIn('ward_id', $wid)->where('status', 'published')->count();

        $genderCounts = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->select('household_members.gender', DB::raw('count(*) as cnt'))
            ->groupBy('household_members.gender')->pluck('cnt', 'gender');

        $totalPop  = $genderCounts->sum();
        $maleCount = (int) ($genderCounts->get('male', 0));
        $femaleCount = (int) ($genderCounts->get('female', 0));

        $litRow = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->select(DB::raw('SUM(CASE WHEN is_literate=1 THEN 1 ELSE 0 END) as lit'), DB::raw('count(*) as tot'))
            ->first();
        $litRate = ($litRow && $litRow->tot > 0) ? round($litRow->lit / $litRow->tot * 100, 1) : 0;

        $elderlyCount   = HouseholdMember::alive()->whereHas('household', $hhBase)->where('age', '>=', 60)->count();
        $disabledCount  = HouseholdMember::alive()->whereHas('household', $hhBase)->where('disability_status', true)->count();
        $abroadCount    = DB::table('member_migration')
            ->join('household_members', 'member_migration.member_id', '=', 'household_members.id')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->where('member_migration.migration_status', 'abroad')->count();
        $institutionCount = DB::table('institutions')->whereIn('ward_id', $wid)->where('status', 'published')->count();
        $businessCount    = DB::table('member_businesses')->whereIn('ward_id', $wid)->where('status', 'published')->count();

        $overview = [
            'households'   => $hhCount,
            'population'   => (int) $totalPop,
            'male'         => $maleCount,
            'female'       => $femaleCount,
            'other'        => (int) ($genderCounts->get('other', 0)),
            'avg_hh_size'  => $hhCount > 0 ? round($totalPop / $hhCount, 1) : 0,
            'literacy_pct' => $litRate,
            'elderly'      => $elderlyCount,
            'disabled'     => $disabledCount,
            'abroad'       => $abroadCount,
            'institutions' => $institutionCount,
            'businesses'   => $businessCount,
            'sex_ratio'    => $femaleCount > 0 ? round($maleCount / $femaleCount * 100) : 0,
        ];

        // ── 2. Age groups ────────────────────────────────────────────────────
        $ageGroups = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('age')
            ->select(DB::raw("
                CASE
                    WHEN age < 5  THEN '0-4'
                    WHEN age < 15 THEN '5-14'
                    WHEN age < 25 THEN '15-24'
                    WHEN age < 40 THEN '25-39'
                    WHEN age < 60 THEN '40-59'
                    ELSE '60+'
                END as grp"), DB::raw('count(*) as cnt'))
            ->groupBy('grp')->orderBy('grp')->pluck('cnt', 'grp')->toArray();

        // ── 3. Ethnicity + Religion + Mother tongue ──────────────────────────
        $ethnicityData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('ethnicity')->where('ethnicity', '!=', '')
            ->select('ethnicity as val', DB::raw('count(*) as cnt'))
            ->groupBy('ethnicity')->orderByDesc('cnt')->get()
            ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();

        $religionData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('religion')->where('religion', '!=', '')
            ->select('religion as val', DB::raw('count(*) as cnt'))
            ->groupBy('religion')->orderByDesc('cnt')->get()
            ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();

        $motherTongueData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('mother_tongue')->where('mother_tongue', '!=', '')
            ->select('mother_tongue as val', DB::raw('count(*) as cnt'))
            ->groupBy('mother_tongue')->orderByDesc('cnt')->limit(10)->get()
            ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();

        // ── 4. Housing ───────────────────────────────────────────────────────
        $housingBase = fn() => DB::table('household_housing')
            ->join('households', 'household_housing.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published');

        $housingBreakdown = function (string $col) use ($housingBase): array {
            return $housingBase()->whereNotNull("household_housing.{$col}")->where("household_housing.{$col}", '!=', '')
                ->select("household_housing.{$col} as val", DB::raw('count(*) as cnt'))
                ->groupBy("household_housing.{$col}")->orderByDesc('cnt')->get()
                ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();
        };

        $permitRow = $housingBase()->select(
            DB::raw('SUM(CASE WHEN has_building_permit=1 THEN 1 ELSE 0 END) as has_permit'),
            DB::raw('count(*) as tot'))->first();

        $housing = [
            'ownership'     => $housingBreakdown('ownership_type'),
            'wall'          => $housingBreakdown('wall_type'),
            'roof'          => $housingBreakdown('roof_type'),
            'foundation'    => $housingBreakdown('foundation_type'),
            'permit_pct'    => ($permitRow && $permitRow->tot > 0) ? round($permitRow->has_permit / $permitRow->tot * 100, 1) : 0,
            'permit_count'  => (int) ($permitRow?->has_permit ?? 0),
            'total_hh'      => (int) ($permitRow?->tot ?? 0),
        ];

        // ── 5. Utilities ─────────────────────────────────────────────────────
        $utilBase = fn() => DB::table('household_utilities')
            ->join('households', 'household_utilities.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published');

        $utilBreakdown = function (string $col) use ($utilBase): array {
            return $utilBase()->whereNotNull("household_utilities.{$col}")->where("household_utilities.{$col}", '!=', '')
                ->select("household_utilities.{$col} as val", DB::raw('count(*) as cnt'))
                ->groupBy("household_utilities.{$col}")->orderByDesc('cnt')->get()
                ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();
        };

        $handwashRow = $utilBase()
            ->select(DB::raw("SUM(CASE WHEN handwashing IS NOT NULL AND handwashing != '' THEN 1 ELSE 0 END) as hw"), DB::raw('count(*) as tot'))
            ->first();

        $utilities = [
            'water'         => $utilBreakdown('water_source'),
            'electricity'   => $utilBreakdown('electricity_source'),
            'cooking'       => $utilBreakdown('cooking_fuel'),
            'toilet'        => $utilBreakdown('toilet_type'),
            'waste'         => $utilBreakdown('solid_waste_management'),
            'internet'      => $utilBreakdown('internet_type'),
            'handwash_pct'  => ($handwashRow && $handwashRow->tot > 0) ? round($handwashRow->hw / $handwashRow->tot * 100, 1) : 0,
        ];

        // ── 6. Education & Occupation ─────────────────────────────────────────
        $eduLevel = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('education_level')->where('education_level', '!=', '')
            ->select('education_level as val', DB::raw('count(*) as cnt'))
            ->groupBy('education_level')->orderByDesc('cnt')->get()
            ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();

        $occupationData = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('occupation_type')->where('occupation_type', '!=', '')
            ->select('occupation_type as val', DB::raw('count(*) as cnt'))
            ->groupBy('occupation_type')->orderByDesc('cnt')->get()
            ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();

        $litByGender = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->whereNotNull('gender')
            ->select('gender',
                DB::raw('SUM(CASE WHEN is_literate=1 THEN 1 ELSE 0 END) as lit'),
                DB::raw('count(*) as tot'))
            ->groupBy('gender')->get()
            ->map(fn($r) => ['gender' => $r->gender, 'lit' => (int)$r->lit, 'tot' => (int)$r->tot])->toArray();

        $education = ['edu_level' => $eduLevel, 'occupation' => $occupationData, 'lit_by_gender' => $litByGender, 'literacy_pct' => $litRate];

        // ── 7. Economic ──────────────────────────────────────────────────────
        $econBase = fn() => DB::table('household_economic')
            ->join('households', 'household_economic.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published');

        $econBreakdown = function (string $col) use ($econBase): array {
            return $econBase()->whereNotNull("household_economic.{$col}")->where("household_economic.{$col}", '!=', '')
                ->select("household_economic.{$col} as val", DB::raw('count(*) as cnt'))
                ->groupBy("household_economic.{$col}")->orderByDesc('cnt')->get()
                ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();
        };

        $econRow = $econBase()->select(
            DB::raw('SUM(CASE WHEN has_bank_account=1 THEN 1 ELSE 0 END) as bank'),
            DB::raw('SUM(CASE WHEN has_insurance=1 THEN 1 ELSE 0 END) as insurance'),
            DB::raw('SUM(CASE WHEN poverty_card=1 THEN 1 ELSE 0 END) as poverty'),
            DB::raw('SUM(CASE WHEN social_security_allowance=1 THEN 1 ELSE 0 END) as ssa'),
            DB::raw('count(*) as tot'))->first();

        $pct = fn($v, $tot) => ($tot > 0) ? round($v / $tot * 100, 1) : 0;

        $economic = [
            'income_source' => $econBreakdown('primary_income_source'),
            'income_range'  => $econBreakdown('annual_income_range'),
            'bank_pct'      => $pct($econRow?->bank ?? 0, $econRow?->tot ?? 0),
            'insurance_pct' => $pct($econRow?->insurance ?? 0, $econRow?->tot ?? 0),
            'poverty_count' => (int) ($econRow?->poverty ?? 0),
            'ssa_count'     => (int) ($econRow?->ssa ?? 0),
            'total_hh'      => (int) ($econRow?->tot ?? 0),
        ];

        // ── 8. Agriculture ───────────────────────────────────────────────────
        $agriBase = fn() => DB::table('household_agriculture')
            ->join('households', 'household_agriculture.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published');

        $agriRow = $agriBase()->select(
            DB::raw('SUM(CASE WHEN agriculture=1 THEN 1 ELSE 0 END) as agri'),
            DB::raw('SUM(CASE WHEN owns_land=1 THEN 1 ELSE 0 END) as owns'),
            DB::raw('SUM(CASE WHEN use_of_chem_fertilizer=1 THEN 1 ELSE 0 END) as chem_fert'),
            DB::raw('SUM(CASE WHEN use_of_chem_pesticide=1 THEN 1 ELSE 0 END) as chem_pest'),
            DB::raw('count(*) as tot'))->first();

        $agriculture = [
            'agri_pct'      => $pct($agriRow?->agri ?? 0, $agriRow?->tot ?? 0),
            'owns_land_pct' => $pct($agriRow?->owns ?? 0, $agriRow?->tot ?? 0),
            'chem_fert_pct' => $pct($agriRow?->chem_fert ?? 0, $agriRow?->agri ?? 1),
            'chem_pest_pct' => $pct($agriRow?->chem_pest ?? 0, $agriRow?->agri ?? 1),
            'agri_count'    => (int) ($agriRow?->agri ?? 0),
            'owns_count'    => (int) ($agriRow?->owns ?? 0),
            'total_hh'      => (int) ($agriRow?->tot ?? 0),
        ];

        // ── 9. Migration ─────────────────────────────────────────────────────
        $migBase = fn() => DB::table('member_migration')
            ->join('household_members', 'member_migration.member_id', '=', 'household_members.id')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published')
            ->where('household_members.is_deceased', false);

        $migCounts = $migBase()->where('member_migration.migration_status', '!=', 'not_migrated')
            ->select('member_migration.migration_status', DB::raw('count(*) as cnt'))
            ->groupBy('member_migration.migration_status')->pluck('cnt', 'migration_status');

        $destCountries = $migBase()->where('member_migration.migration_status', 'abroad')
            ->whereNotNull('member_migration.destination_country')->where('member_migration.destination_country', '!=', '')
            ->select('member_migration.destination_country as country', DB::raw('count(*) as cnt'))
            ->groupBy('member_migration.destination_country')->orderByDesc('cnt')->limit(8)->get()
            ->map(fn($r) => ['country' => $r->country, 'cnt' => (int)$r->cnt])->toArray();

        $migration = [
            'abroad'          => (int) ($migCounts->get('abroad', 0)),
            'internal'        => (int) ($migCounts->get('internal', 0)),
            'dest_countries'  => $destCountries,
        ];

        // ── 10. Health & Disability ───────────────────────────────────────────
        $disabilityTypes = HouseholdMember::alive()->whereHas('household', $hhBase)
            ->where('disability_status', true)
            ->whereNotNull('disability_type')->where('disability_type', '!=', '')
            ->select('disability_type as val', DB::raw('count(*) as cnt'))
            ->groupBy('disability_type')->orderByDesc('cnt')->get()
            ->map(fn($r) => ['val' => $r->val, 'cnt' => (int)$r->cnt])->toArray();

        $chronicCount = HouseholdMember::alive()->whereHas('household', $hhBase)->where('chronic_illness', true)->count();

        $health = [
            'disabled_count'  => $disabledCount,
            'disability_types'=> $disabilityTypes,
            'chronic_count'   => $chronicCount,
            'elderly_count'   => $elderlyCount,
        ];

        // ── 11. Disaster ──────────────────────────────────────────────────────
        $disBase = fn() => DB::table('household_disaster')
            ->join('households', 'household_disaster.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $wid)->where('households.status', 'published');

        $disRow = $disBase()->select(
            DB::raw('SUM(CASE WHEN is_flood_zone=1 THEN 1 ELSE 0 END) as flood'),
            DB::raw('SUM(CASE WHEN is_landslide_zone=1 THEN 1 ELSE 0 END) as landslide'),
            DB::raw('SUM(CASE WHEN is_drought_zone=1 THEN 1 ELSE 0 END) as drought'),
            DB::raw('SUM(CASE WHEN has_evacuation_plan=1 THEN 1 ELSE 0 END) as evac'),
            DB::raw('SUM(CASE WHEN disaster_training=1 THEN 1 ELSE 0 END) as training'),
            DB::raw('count(*) as tot'))->first();

        $disaster = [
            'flood_count'     => (int) ($disRow?->flood ?? 0),
            'landslide_count' => (int) ($disRow?->landslide ?? 0),
            'drought_count'   => (int) ($disRow?->drought ?? 0),
            'evac_count'      => (int) ($disRow?->evac ?? 0),
            'training_count'  => (int) ($disRow?->training ?? 0),
            'total_hh'        => (int) ($disRow?->tot ?? 0),
        ];

        // ── Other wards for navigation ────────────────────────────────────────
        $allWards = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get(['id', 'ward_number']);

        return Inertia::render('Public/Reports/WardProfile', [
            'lg'            => $this->lgProps($lg),
            'ward'          => ['id' => $ward->id, 'ward_number' => $ward->ward_number],
            'allWards'      => $allWards->map(fn($w) => ['id' => $w->id, 'ward_number' => $w->ward_number])->values(),
            'overview'      => $overview,
            'ageGroups'     => $ageGroups,
            'ethnicityData' => $ethnicityData,
            'religionData'  => $religionData,
            'motherTongue'  => $motherTongueData,
            'housing'       => $housing,
            'utilities'     => $utilities,
            'education'     => $education,
            'economic'      => $economic,
            'agriculture'   => $agriculture,
            'migration'     => $migration,
            'health'        => $health,
            'disaster'      => $disaster,
        ]);
    }

    public function profile()
    {
        $lg = $this->defaultLg();

        $profileSections = LocalGovernment::profileSections();
        $profileContent  = $lg?->profile_content ?? [];

        return Inertia::render('Public/Profile', [
            'lg'              => $lg ? $this->lgProps($lg) : null,
            'profileSections' => $profileSections,
            'profileContent'  => $profileContent,
        ]);
    }

    public function housing(Request $request)
    {
        $lg = $this->defaultLg();

        if (!$lg) {
            return redirect()->route('public.dashboard');
        }

        $allWardIds     = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $selectedWardId = $request->integer('ward_id') ?: null;
        if ($selectedWardId && !in_array($selectedWardId, $allWardIds)) {
            $selectedWardId = null;
        }
        $activeWardIds = $selectedWardId ? [$selectedWardId] : $allWardIds;

        $wards = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $base = fn() => HouseholdHousing::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $activeWardIds)->where('status', 'published')
        );

        $totalHouseholds = $base()->count();

        $labelMaps = [
            'foundation_type' => [
                'rcc'          => 'RCC / Concrete',
                'brick_cement' => 'Brick (Cement)',
                'stone_cement' => 'Stone (Cement)',
                'stone_mud'    => 'Stone (Mud)',
                'other'        => 'Other',
            ],
            'roof_type' => [
                'rcc'    => 'RCC / Concrete',
                'tin'    => 'Tin / Metal',
                'tile'   => 'Tile',
                'thatch' => 'Thatch / Straw',
                'slate'  => 'Slate',
                'other'  => 'Other',
            ],
            'wall_type' => [
                'rcc'          => 'RCC / Concrete',
                'brick_cement' => 'Brick (Cement)',
                'brick_mud'    => 'Brick (Mud)',
                'stone_cement' => 'Stone (Cement)',
                'stone_mud'    => 'Stone (Mud)',
                'bamboo'       => 'Bamboo',
                'wood'         => 'Wood',
                'other'        => 'Other',
            ],
            'ownership_type' => [
                'owned'         => 'Owned',
                'rented'        => 'Rented',
                'institutional' => 'Institutional',
                'squatter'      => 'Squatter',
                'other'         => 'Other',
            ],
        ];

        $breakdown = function (string $column) use ($base, $labelMaps): array {
            $rows  = $base()
                ->whereNotNull($column)->where($column, '!=', '')
                ->select($column, DB::raw('count(*) as count'))
                ->groupBy($column)->orderByDesc('count')->get();
            $total = $rows->sum('count');
            if (!$total) return [];
            return $rows->map(fn($r) => [
                'value'      => $r->$column,
                'label'      => $labelMaps[$column][$r->$column] ?? ucfirst(str_replace('_', ' ', $r->$column)),
                'count'      => $r->count,
                'percentage' => round($r->count / $total * 100, 1),
            ])->toArray();
        };

        $permitRows  = $base()->select('has_building_permit', DB::raw('count(*) as count'))->groupBy('has_building_permit')->get();
        $permitTotal = $permitRows->sum('count');
        $permitData  = $permitRows->map(fn($r) => [
            'value'      => $r->has_building_permit ? 'yes' : 'no',
            'label'      => $r->has_building_permit ? 'छ (Yes)' : 'छैन (No)',
            'count'      => $r->count,
            'percentage' => $permitTotal ? round($r->count / $permitTotal * 100, 1) : 0,
        ])->sortByDesc('count')->values()->toArray();

        return Inertia::render('Public/Housing', [
            'lg'              => $this->lgProps($lg),
            'wards'           => $wards->map(fn($w) => ['id' => $w->id, 'ward_number' => $w->ward_number])->values(),
            'selectedWardId'  => $selectedWardId,
            'totalHouseholds' => $totalHouseholds,
            'foundationData'  => $breakdown('foundation_type'),
            'roofData'        => $breakdown('roof_type'),
            'wallData'        => $breakdown('wall_type'),
            'ownershipData'   => $breakdown('ownership_type'),
            'permitData'      => $permitData,
        ]);
    }

    public function ward(Ward $ward)
    {
        $stats = [
            'households'   => Household::where('ward_id', $ward->id)->where('status', 'published')->count(),
            'members'      => HouseholdMember::alive()->whereHas('household', fn($q) => $q->where('ward_id', $ward->id)->where('status', 'published'))->count(),
            'institutions' => Institution::where('ward_id', $ward->id)->where('status', 'published')->count(),
        ];

        $genderData = HouseholdMember::alive()->whereHas('household', fn($q) => $q->where('ward_id', $ward->id)->where('status', 'published'))
            ->whereNotNull('gender')
            ->select('gender', DB::raw('count(*) as count'))
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        $institutions = Institution::where('ward_id', $ward->id)
            ->where('status', 'published')
            ->with('institutionType')
            ->get()
            ->map(fn($i) => [
                'id'      => $i->id,
                'name_en' => $i->name_en,
                'type'    => $i->institutionType?->name_en,
            ]);

        $lg = LocalGovernment::with(['district.province'])->where('is_active', true)->first()
            ?? LocalGovernment::with(['district.province'])->first();

        return Inertia::render('Public/Ward', [
            'lg'           => $lg ? $this->lgProps($lg) : null,
            'ward'         => ['id' => $ward->id, 'ward_number' => $ward->ward_number],
            'stats'        => $stats,
            'genderData'   => $genderData,
            'institutions' => $institutions,
        ]);
    }

    public function population(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return response()->json([]);
        $wardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();

        $data = HouseholdMember::alive()->whereHas('household', fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'published'))
            ->whereNotNull('gender')
            ->select('gender', DB::raw('count(*) as count'))
            ->groupBy('gender')
            ->pluck('count', 'gender');

        return response()->json($data);
    }

    public function institutions(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return response()->json([]);
        $wardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();

        $data = Institution::whereIn('ward_id', $wardIds)
            ->where('status', 'published')
            ->join('institution_types', 'institutions.institution_type_id', '=', 'institution_types.id')
            ->select('institution_types.name_en as label', DB::raw('count(*) as count'))
            ->groupBy('institution_types.name_en')
            ->get();

        return response()->json($data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Map — household GPS points
    // ─────────────────────────────────────────────────────────────────────────

    public function map(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return Inertia::render('Public/Map', ['lg' => null]);

        $wards   = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get(['id', 'ward_number']);
        $wardIds = $wards->pluck('id')->toArray();

        $selectedWardId = $request->integer('ward_id') ?: null;
        $filterWardIds  = $selectedWardId
            ? [$selectedWardId]
            : $wardIds;

        // Load GPS points (only rows with both lat and lng)
        $points = Household::whereIn('households.ward_id', $filterWardIds)
            ->where('households.status', 'published')
            ->whereNotNull('households.gps_lat')
            ->whereNotNull('households.gps_lng')
            ->where('households.gps_lat', '!=', 0)
            ->where('households.gps_lng', '!=', 0)
            ->join('wards', 'households.ward_id', '=', 'wards.id')
            ->select(
                'households.id',
                'households.unique_code',
                'households.respondent_name',
                'households.gps_lat',
                'households.gps_lng',
                'households.gps_altitude',
                'wards.ward_number'
            )
            ->get()
            ->map(fn ($h) => [
                'id'   => $h->id,
                'code' => $h->unique_code,
                'name' => $h->respondent_name,
                'lat'  => (float) $h->gps_lat,
                'lng'  => (float) $h->gps_lng,
                'alt'  => $h->gps_altitude ? (float) $h->gps_altitude : null,
                'ward' => $h->ward_number,
            ]);

        // Ward counts for filter summary
        $wardCounts = Household::whereIn('ward_id', $wardIds)
            ->where('status', 'published')
            ->whereNotNull('gps_lat')->whereNotNull('gps_lng')
            ->where('gps_lat', '!=', 0)->where('gps_lng', '!=', 0)
            ->selectRaw('ward_id, count(*) as cnt')
            ->groupBy('ward_id')
            ->pluck('cnt', 'ward_id');  // no join here, no ambiguity

        return Inertia::render('Public/Map', [
            'lg'          => $this->lgProps($lg),
            'wards'       => $wards,
            'points'      => $points,
            'wardCounts'  => $wardCounts,
            'filters'     => ['ward_id' => $selectedWardId],
            'totalPoints' => $points->count(),
        ]);
    }

    public function institutionMap(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return Inertia::render('Public/InstitutionMap', ['lg' => null]);

        $wards   = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get(['id', 'ward_number']);
        $wardIds = $wards->pluck('id')->toArray();

        $selectedWardId = $request->integer('ward_id') ?: null;
        $selectedTypeId = $request->integer('type_id') ?: null;

        $filterWardIds = $selectedWardId ? [$selectedWardId] : $wardIds;

        $baseQ = Institution::whereIn('institutions.ward_id', $filterWardIds)
            ->where('institutions.status', 'published')
            ->whereNotNull('institutions.gps_lat')
            ->whereNotNull('institutions.gps_lng')
            ->where('institutions.gps_lat', '!=', 0)
            ->where('institutions.gps_lng', '!=', 0)
            ->join('institution_types', 'institutions.institution_type_id', '=', 'institution_types.id')
            ->join('wards', 'institutions.ward_id', '=', 'wards.id');

        if ($selectedTypeId) {
            $baseQ->where('institutions.institution_type_id', $selectedTypeId);
        }

        $points = (clone $baseQ)
            ->select(
                'institutions.id',
                'institutions.unique_code',
                'institutions.name_np',
                'institutions.name_en',
                'institutions.gps_lat',
                'institutions.gps_lng',
                'institutions.gps_altitude',
                'institutions.institution_type_id',
                'institution_types.name_np as type_np',
                'institution_types.name_en as type_en',
                'wards.ward_number'
            )
            ->get()
            ->map(fn ($i) => [
                'id'      => $i->id,
                'code'    => $i->unique_code,
                'name_np' => $i->name_np,
                'name_en' => $i->name_en,
                'lat'     => (float) $i->gps_lat,
                'lng'     => (float) $i->gps_lng,
                'alt'     => $i->gps_altitude ? (float) $i->gps_altitude : null,
                'ward'    => $i->ward_number,
                'type_id' => $i->institution_type_id,
                'type_np' => $i->type_np,
                'type_en' => $i->type_en,
            ]);

        // Ward counts (no type filter for sidebar counts)
        $wardCounts = Institution::whereIn('ward_id', $wardIds)
            ->where('status', 'published')
            ->whereNotNull('gps_lat')->whereNotNull('gps_lng')
            ->where('gps_lat', '!=', 0)->where('gps_lng', '!=', 0)
            ->selectRaw('ward_id, count(*) as cnt')
            ->groupBy('ward_id')
            ->pluck('cnt', 'ward_id');

        // All institution types that have GPS data in this LG
        $types = InstitutionType::whereHas('institutions', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)
              ->where('status', 'published')
              ->whereNotNull('gps_lat')->where('gps_lat', '!=', 0)
        )->orderBy('name_en')->get(['id', 'name_np', 'name_en']);

        return Inertia::render('Public/InstitutionMap', [
            'lg'          => $this->lgProps($lg),
            'wards'       => $wards,
            'types'       => $types,
            'points'      => $points,
            'wardCounts'  => $wardCounts,
            'filters'     => ['ward_id' => $selectedWardId, 'type_id' => $selectedTypeId],
            'totalPoints' => $points->count(),
        ]);
    }

    public function representatives()
    {
        $lg = $this->defaultLg();

        $representatives = $lg
            ? \App\Models\LgRepresentative::where('local_government_id', $lg->id)
                ->with(['ward:id,ward_number', 'addressWard:id,ward_number', 'addressTole:id,name_np'])
                ->orderBy('sort_order')->orderBy('id')
                ->get()
            : collect();

        $surveyOptsForRep = \App\Models\SurveyOption::whereIn('category', ['ethnicity', 'education_level'])
            ->where('is_active', true)
            ->orderBy('category')->orderBy('sort_order')
            ->get(['category', 'value', 'label_ne'])
            ->groupBy('category');

        return Inertia::render('Public/Representatives', [
            'lg'              => $lg ? $this->lgProps($lg) : null,
            'representatives' => $representatives,
            'surveyOptsForRep' => $surveyOptsForRep,
        ]);
    }

    public function employees()
    {
        $lg = $this->defaultLg();

        $employees = $lg
            ? \App\Models\LgEmployee::where('local_government_id', $lg->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get()
            : collect();

        $surveyOptsForEmp = \App\Models\SurveyOption::whereIn('category', ['ethnicity', 'education_level'])
            ->where('is_active', true)
            ->orderBy('category')->orderBy('sort_order')
            ->get(['category', 'value', 'label_ne'])
            ->groupBy('category');

        return Inertia::render('Public/Employees', [
            'lg'               => $lg ? $this->lgProps($lg) : null,
            'employees'        => $employees,
            'surveyOptsForEmp' => $surveyOptsForEmp,
        ]);
    }

    public function gallery()
    {
        $lg = $this->defaultLg();

        $photos = $lg
            ? \App\Models\LgPhoto::where('local_government_id', $lg->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get()
            : collect();

        $galleryUrl = $lg ? rtrim($lg->photo_dir['gallery_url'] ?? '', '/') : null;

        // Distinct categories that actually have photos
        $categories = $photos->pluck('category')->filter()->unique()->values();

        return Inertia::render('Public/Gallery', [
            'lg'         => $lg ? $this->lgProps($lg) : null,
            'photos'     => $photos,
            'galleryUrl' => $galleryUrl,
            'categories' => $categories,
        ]);
    }

    public function downloads()
    {
        $lg = $this->defaultLg();

        $links = $lg
            ? \App\Models\LgDownloadLink::where('local_government_id', $lg->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get()
                ->map(fn($d) => [
                    'id'           => $d->id,
                    'title'        => $d->title,
                    'description'  => $d->description,
                    'url'          => $d->url,
                    'thumbnail'    => $d->thumbnail ? \Illuminate\Support\Facades\Storage::url($d->thumbnail) : null,
                    'created_date' => $d->created_date?->format('Y-m-d'),
                ])
            : collect();

        return Inertia::render('Public/Downloads', [
            'lg'    => $lg ? $this->lgProps($lg) : null,
            'links' => $links,
        ]);
    }
}
