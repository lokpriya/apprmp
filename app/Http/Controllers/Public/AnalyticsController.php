<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdHousing;
use App\Models\HouseholdMember;
use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\LocalGovernment;
use App\Models\Settlement;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    private function defaultLg(): ?LocalGovernment
    {
        return LocalGovernment::with(['district.province'])->first();
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
            'total_members'      => HouseholdMember::whereHas('household', $hhBase)->count(),
            'total_institutions' => Institution::whereIn('ward_id', $activeWardIds)
                ->where('status', 'published')
                ->when($selectedSettlementId, fn($q) => $q->where('settlement_id', $selectedSettlementId))
                ->count(),
            'total_wards'        => count($allWardIds),
        ];

        $genderData = HouseholdMember::whereHas('household', $hhBase)
            ->whereNotNull('gender')
            ->select('gender', DB::raw('count(*) as count'))
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        $ageData = HouseholdMember::whereHas('household', $hhBase)
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

        $ethnicityData = HouseholdMember::whereHas('household', $hhBase)
            ->whereNotNull('ethnicity')->where('ethnicity', '!=', '')
            ->select('ethnicity', DB::raw('count(*) as count'))
            ->groupBy('ethnicity')
            ->orderByDesc('count')
            ->pluck('count', 'ethnicity')
            ->toArray();

        $religionData = HouseholdMember::whereHas('household', $hhBase)
            ->whereNotNull('religion')->where('religion', '!=', '')
            ->select('religion', DB::raw('count(*) as count'))
            ->groupBy('religion')
            ->orderByDesc('count')
            ->pluck('count', 'religion')
            ->toArray();

        $motherTongueData = HouseholdMember::whereHas('household', $hhBase)
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
            ->select('institution_types.name_en', DB::raw('count(*) as count'))
            ->groupBy('institution_types.name_en')
            ->pluck('count', 'name_en')
            ->toArray();

        $wards = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $settlements = $selectedWardId
            ? Settlement::where('ward_id', $selectedWardId)->orderBy('name_en')->get()
                ->map(fn($s) => ['id' => $s->id, 'name_en' => $s->name_en, 'name_np' => $s->name_np])
                ->values()
            : collect();

        $wardHouseholds = DB::table('households')
            ->whereIn('ward_id', $allWardIds)
            ->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as hh'))
            ->groupBy('ward_id')->pluck('hh', 'ward_id');

        $wardGender = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allWardIds)
            ->where('households.status', 'published')
            ->select('households.ward_id', 'household_members.gender', DB::raw('count(*) as cnt'))
            ->groupBy('households.ward_id', 'household_members.gender')
            ->get()->groupBy('ward_id');

        $wardSummary = $wards->map(fn($ward) => [
            'ward_id'     => $ward->id,
            'ward_number' => $ward->ward_number,
            'households'  => $wardHouseholds->get($ward->id, 0),
            'male'        => $wardGender->get($ward->id, collect())->firstWhere('gender', 'male')?->cnt ?? 0,
            'female'      => $wardGender->get($ward->id, collect())->firstWhere('gender', 'female')?->cnt ?? 0,
            'total'       => $wardGender->get($ward->id, collect())->sum('cnt'),
        ])->values();

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
            'ethnicityData'       => $ethnicityData,
            'religionData'        => $religionData,
            'motherTongueData'    => $motherTongueData,
            'institutionData'     => $institutionData,
            'wardSummary'         => $wardSummary,
            'selectedWardId'      => $selectedWardId,
            'selectedSettlementId' => $selectedSettlementId,
            'profileFilledCount'  => $profileFilledCount,
            'welcomeConfig'       => $lg->welcome_config ?? [],
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
            'members'      => HouseholdMember::whereHas('household', fn($q) => $q->where('ward_id', $ward->id)->where('status', 'published'))->count(),
            'institutions' => Institution::where('ward_id', $ward->id)->where('status', 'published')->count(),
        ];

        $genderData = HouseholdMember::whereHas('household', fn($q) => $q->where('ward_id', $ward->id)->where('status', 'published'))
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

        $lg = LocalGovernment::with(['district.province'])->first();

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
        $lg      = $this->defaultLg();
        $wardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();

        $data = HouseholdMember::whereHas('household', fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'published'))
            ->whereNotNull('gender')
            ->select('gender', DB::raw('count(*) as count'))
            ->groupBy('gender')
            ->pluck('count', 'gender');

        return response()->json($data);
    }

    public function institutions(Request $request)
    {
        $lg      = $this->defaultLg();
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
