<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdAgriculture;
use App\Models\HouseholdDisaster;
use App\Models\HouseholdEconomic;
use App\Models\HouseholdHousing;
use App\Models\HouseholdLivestock;
use App\Models\HouseholdMember;
use App\Models\HouseholdUtility;
use App\Models\Institution;
use App\Models\LocalGovernment;
use App\Models\Ward;
use App\Services\CrossTabService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportController extends Controller
{
    // ── LG helpers ───────────────────────────────────────────────────────────

    private function defaultLg(): ?LocalGovernment
    {
        // Canonical, cached and deterministic. Resolving this per controller meant
        // an uncached joined query on every public request, and copies that could
        // disagree with one another after a settings change.
        return LocalGovernment::getActiveFull();
    }

    private function lgProps(LocalGovernment $lg): array
    {
        return [
            'id'       => $lg->id,
            'name_en'  => $lg->name_en,
            'name_np'  => $lg->name_np,
            'logo_url' => $lg->logo && Storage::disk('public')->exists($lg->logo)
                            ? Storage::disk('public')->url($lg->logo)
                            : null,
            'province' => $lg->district?->province?->name_en,
            'district' => $lg->district?->name_en,
        ];
    }

    /**
     * Returns ward IDs for the LG (filtered by ward_id if requested).
     */
    private function wardIds(LocalGovernment $lg, ?int $wardId = null): array
    {
        $q = Ward::where('local_government_id', $lg->id);
        if ($wardId) $q->where('id', $wardId);
        return $q->pluck('id')->toArray();
    }

    /**
     * Base member query: members of households in scope.
     */
    private function memberQuery(array $wardIds, Request $request)
    {
        $q = HouseholdMember::where('is_deceased', false)->whereHas('household', fn ($hq) =>
            $hq->whereIn('ward_id', $wardIds)->where('status', 'published')
        );

        if ($request->filled('age_min')) $q->where('age', '>=', (int)$request->age_min);
        if ($request->filled('age_max')) $q->where('age', '<=', (int)$request->age_max);
        if ($request->filled('gender'))  $q->where('gender', $request->gender);

        if ($request->filled('settlement_id')) {
            $q->whereHas('household', fn ($hq) =>
                $hq->where('settlement_id', (int)$request->settlement_id)
            );
        }

        return $q;
    }

    /**
     * All-LG member query for municipal average comparison.
     * Uses all LG ward IDs (ignores ward/settlement filter) but keeps age/gender filters.
     */
    private function lgMemberQuery(LocalGovernment $lg, Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $q = HouseholdMember::where('is_deceased', false)->whereHas('household', fn ($hq) =>
            $hq->whereIn('ward_id', $this->wardIds($lg))->where('status', 'published')
        );
        if ($request->filled('age_min')) $q->where('age', '>=', (int)$request->age_min);
        if ($request->filled('age_max')) $q->where('age', '<=', (int)$request->age_max);
        if ($request->filled('gender'))  $q->where('gender', $request->gender);
        return $q;
    }

    /**
     * Returns frequency comparison data (municipal scope). Returns [] when lgMq is null.
     */
    private function compFreq($lgMq, string $colExpr, array $labels, string $ck): array
    {
        if (!$lgMq) return [];
        return CrossTabService::freq(clone $lgMq, $colExpr, $labels, $ck);
    }

    /**
     * Shared filter props passed to every report page.
     */
    private function filterProps(LocalGovernment $lg, Request $request): array
    {
        $wards = Ward::where('local_government_id', $lg->id)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number']);

        $settlements = $request->filled('ward_id')
            ? \App\Models\Settlement::where('ward_id', (int)$request->ward_id)
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_np'])
            : collect();

        return [
            'wards'       => $wards,
            'settlements' => $settlements,
            'filters'     => [
                'ward_id'       => $request->input('ward_id'),
                'settlement_id' => $request->input('settlement_id'),
                'gender'        => $request->input('gender'),
                'age_min'       => $request->input('age_min'),
                'age_max'       => $request->input('age_max'),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Round Comparison — multi-round snapshot comparison
    // ─────────────────────────────────────────────────────────────────────────

    public function roundComparison(): \Inertia\Response
    {
        $lg = $this->defaultLg();
        if (!$lg) {
            return Inertia::render('Public/Reports/RoundComparison', [
                'lg'     => null,
                'rounds' => [],
            ]);
        }

        return Inertia::render('Public/Reports/RoundComparison', [
            'lg'     => $this->lgProps($lg),
            'rounds' => $lg->survey_config['rounds'] ?? [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Index — overview / landing
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return Inertia::render('Public/Reports/Index', ['lg' => null]);

        $wardIds      = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $allWardIds   = $this->wardIds($lg);
        $settlementId = $request->integer('settlement_id') ?: null;
        $mq           = $this->memberQuery($wardIds, $request);
        $ck           = CrossTabService::cacheKey('idx', $lg->id, $request->only(['ward_id', 'settlement_id']));

        $summary = [
            'total_households'   => Household::whereIn('ward_id', $wardIds)->where('status', 'published')
                ->when($settlementId, fn ($q) => $q->where('settlement_id', $settlementId))->count(),
            'total_population'   => (clone $mq)->count(),
            'total_male'         => (clone $mq)->where('gender', 'male')->count(),
            'total_female'       => (clone $mq)->where('gender', 'female')->count(),
            'total_other'        => (clone $mq)->where('gender', 'other')->count(),
            'total_institutions' => Institution::whereIn('ward_id', $wardIds)->where('status', 'published')
                ->when($settlementId, fn ($q) => $q->where('settlement_id', $settlementId))->count(),
            'total_wards'        => count($allWardIds),
        ];

        $genderData = CrossTabService::freq(
            clone $mq, 'gender',
            CrossTabService::genderLabels(),
            $ck . '_gender'
        );

        $ageData = CrossTabService::freq(
            clone $mq,
            CrossTabService::ageGroupExpr(),
            CrossTabService::ageGroupLabels(),
            $ck . '_age'
        );

        $occupationData = CrossTabService::freq(
            clone $mq,
            'occupation_type',
            CrossTabService::optionLabels('occupation'),
            $ck . '_occ'
        );

        $educationData = CrossTabService::freq(
            clone $mq,
            'education_level',
            CrossTabService::optionLabels('education_level'),
            $ck . '_edu'
        );

        // Per-ward population + household counts
        $allLgWards  = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $hhByWard    = DB::table('households')
            ->whereIn('ward_id', $allLgWards->pluck('id'))
            ->where('status', 'published')
            ->select('ward_id', DB::raw('count(*) as cnt'))
            ->groupBy('ward_id')->pluck('cnt', 'ward_id');
        $popByWard   = DB::table('household_members')
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->whereIn('households.ward_id', $allLgWards->pluck('id'))
            ->where('households.status', 'published')
            ->where('household_members.is_deceased', false)
            ->select('households.ward_id', DB::raw('count(*) as cnt'))
            ->groupBy('households.ward_id')->pluck('cnt', 'ward_id');

        $wardData = $allLgWards->map(fn($w) => [
            'ward_number' => $w->ward_number,
            'population'  => (int)($popByWard->get($w->id, 0)),
            'households'  => (int)($hhByWard->get($w->id, 0)),
        ])->values();

        // Key indicators — literacy: literate ÷ members with education data (same formula as AnalyticsController)
        $litRow = (clone $mq)
            ->whereNotNull('education_level')->where('education_level', '!=', '')
            ->selectRaw("SUM(CASE WHEN LOWER(education_level) NOT IN ('illitrate','illiterate') THEN 1 ELSE 0 END) as lit, COUNT(*) as tot")
            ->first();
        $litRate  = ($litRow && $litRow->tot > 0) ? round($litRow->lit / $litRow->tot * 100, 1) : 0;
        $totalPop = $summary['total_population'];
        $totalHH  = $summary['total_households'];

        $keyIndicators = [
            'sex_ratio'     => $summary['total_female'] > 0
                ? round($summary['total_male'] / $summary['total_female'] * 100) : 0,
            'literacy_rate' => $litRate,
            'avg_hh_size'   => $totalHH > 0 ? round($totalPop / $totalHH, 1) : 0,
        ];

        return Inertia::render('Public/Reports/Index', [
            'lg'             => $this->lgProps($lg),
            'summary'        => $summary,
            'genderData'     => $genderData,
            'ageData'        => $ageData,
            'occupationData' => $occupationData,
            'educationData'  => $educationData,
            'wardData'       => $wardData,
            'keyIndicators'  => $keyIndicators,
            ...$this->filterProps($lg, $request),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Demographics — gender × age, ethnicity, religion, mother tongue
    // ─────────────────────────────────────────────────────────────────────────

    public function demographics(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('demo', $lg->id, $request->only(['ward_id','settlement_id']));
        $hasWard = $request->filled('ward_id');
        $lgMq    = $hasWard ? $this->lgMemberQuery($lg, $request) : null;
        // Municipal-comparison series needs its own namespace: keyed on the plain prefix
        // with no filters it collides byte-for-byte with the unfiltered page's own key,
        // and it must carry the filters lgMemberQuery() applies or a gendered request
        // poisons the unfiltered chart.
        $lgCk    = CrossTabService::cacheKey('demo_lg', $lg->id, $request->only(['age_min','age_max','gender']));

        $genderByAge = CrossTabService::tab(
            clone $mq,
            'gender',
            CrossTabService::ageGroupExpr(),
            CrossTabService::genderLabels(),
            CrossTabService::ageGroupLabels(),
            $ck . '_gxage'
        );

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        // Ward cross-tab needs a JOIN to reach wards.ward_number
        $genderByWardQ = (clone $mq)
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->join('wards',      'households.ward_id',             '=', 'wards.id');

        $genderByWard = CrossTabService::tab(
            $genderByWardQ,
            'household_members.gender',
            'wards.ward_number',
            CrossTabService::genderLabels(),
            $wardColDef,
            $ck . '_gxward'
        );

        $ethnicityFreq = CrossTabService::freq(
            clone $mq, 'ethnicity', CrossTabService::optionLabels('ethnicity'), $ck . '_ethnicity'
        );

        $religionFreq = CrossTabService::freq(
            clone $mq, 'religion', CrossTabService::optionLabels('religion'), $ck . '_religion'
        );

        $motherTongueFreq = CrossTabService::freq(
            clone $mq, 'mother_tongue', CrossTabService::optionLabels('mother_tongue'), $ck . '_mother_tongue'
        );

        $ethnicityByGender = CrossTabService::tab(
            clone $mq,
            'ethnicity',
            'gender',
            CrossTabService::optionLabels('ethnicity'),
            CrossTabService::genderLabels(),
            $ck . '_ethxgender'
        );

        $maritalByGender = CrossTabService::tab(
            clone $mq,
            'marital_status',
            'gender',
            CrossTabService::optionLabels('marital_status'),
            CrossTabService::genderLabels(),
            $ck . '_maritalxgender'
        );

        $maritalFreq = CrossTabService::freq(
            clone $mq, 'marital_status', CrossTabService::optionLabels('marital_status'), $ck . '_marital'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'               => $this->lgProps($lg),
            'category'         => 'demographics',
            'title'            => 'जनसांख्यिकीय विवरण',
            'title_en'         => 'Demographics',
            ...$this->filterProps($lg, $request),
            'tables'           => [
                [
                    'id'      => 'gender_by_age',
                    'title'   => 'लिङ्ग अनुसार उमेर समूह',
                    'title_en'=> 'Gender by Age Group',
                    'data'    => $genderByAge,
                ],
                [
                    'id'      => 'gender_by_ward',
                    'title'   => 'लिङ्ग अनुसार वडा',
                    'title_en'=> 'Gender by Ward',
                    'data'    => $genderByWard,
                ],
                [
                    'id'      => 'ethnicity_by_gender',
                    'title'   => 'जाति/जनजाति अनुसार लिङ्ग',
                    'title_en'=> 'Ethnicity by Gender',
                    'data'    => $ethnicityByGender,
                ],
                [
                    'id'      => 'marital_by_gender',
                    'title'   => 'वैवाहिक अवस्था अनुसार लिङ्ग',
                    'title_en'=> 'Marital Status by Gender',
                    'data'    => $maritalByGender,
                ],
            ],
            'frequencies'      => [
                [
                    'id'         => 'ethnicity',
                    'title'      => 'जातीय विवरण',
                    'title_en'   => 'Ethnicity Distribution',
                    'data'       => $ethnicityFreq,
                    'comparison' => $this->compFreq($lgMq, 'ethnicity', CrossTabService::optionLabels('ethnicity'), $lgCk . '_ethnicity'),
                ],
                [
                    'id'         => 'religion',
                    'title'      => 'धर्म अनुसार जनसंख्या',
                    'title_en'   => 'Religion Distribution',
                    'data'       => $religionFreq,
                    'comparison' => $this->compFreq($lgMq, 'religion', CrossTabService::optionLabels('religion'), $lgCk . '_religion'),
                ],
                [
                    'id'         => 'mother_tongue',
                    'title'      => 'मातृभाषा अनुसार जनसंख्या',
                    'title_en'   => 'Mother Tongue Distribution',
                    'data'       => $motherTongueFreq,
                    'comparison' => $this->compFreq($lgMq, 'mother_tongue', CrossTabService::optionLabels('mother_tongue'), $lgCk . '_mother_tongue'),
                ],
                [
                    'id'         => 'marital',
                    'title'      => 'वैवाहिक अवस्था',
                    'title_en'   => 'Marital Status',
                    'data'       => $maritalFreq,
                    'comparison' => $this->compFreq($lgMq, 'marital_status', CrossTabService::optionLabels('marital_status'), $lgCk . '_marital'),
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Education
    // ─────────────────────────────────────────────────────────────────────────

    public function education(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('edu', $lg->id, $request->only(['ward_id','settlement_id','gender']));
        $hasWard = $request->filled('ward_id');
        $lgMq    = $hasWard ? $this->lgMemberQuery($lg, $request) : null;
        $lgCk    = CrossTabService::cacheKey('edu_lg', $lg->id, $request->only(['age_min','age_max','gender']));

        $eduByGender = CrossTabService::tab(
            clone $mq,
            'education_level',
            'gender',
            CrossTabService::optionLabels('education_level'),
            CrossTabService::genderLabels(),
            $ck . '_eduxgender'
        );

        $eduByAge = CrossTabService::tab(
            clone $mq,
            'education_level',
            CrossTabService::ageGroupExpr(),
            CrossTabService::optionLabels('education_level'),
            CrossTabService::ageGroupLabels(),
            $ck . '_eduxage'
        );

        $literacyByGender = CrossTabService::tab(
            clone $mq,
            "IF(is_literate = 1 OR LOWER(is_literate) = 'yes', 'yes', 'no')",
            'gender',
            ['yes' => 'साक्षर', 'no' => 'निरक्षर'],
            CrossTabService::genderLabels(),
            $ck . '_litxgender'
        );

        $schoolTypeFreq = CrossTabService::freq(
            clone $mq, 'school_type', CrossTabService::optionLabels('school_type'), $ck . '_school_type'
        );

        $notInSchoolFreq = CrossTabService::freq(
            clone $mq, 'cause_not_school', CrossTabService::optionLabels('cause_not_school'), $ck . '_not_school'
        );

        $eduStatusFreq = CrossTabService::freq(
            clone $mq, 'education_status', CrossTabService::optionLabels('education_status'), $ck . '_edu_status'
        );

        $vocTrainingByGender = CrossTabService::tab(
            clone $mq,
            "IF(vocational_training = 1 OR LOWER(vocational_training) = 'yes', 'yes', 'no')",
            'gender',
            ['yes' => 'छ', 'no' => 'छैन'],
            CrossTabService::genderLabels(),
            $ck . '_vocxgender'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'education',
            'title'       => 'शिक्षा सम्बन्धी विवरण',
            'title_en'    => 'Education',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'edu_by_gender',
                    'title'   => 'शैक्षिक स्तर अनुसार लिङ्ग',
                    'title_en'=> 'Education Level by Gender',
                    'data'    => $eduByGender,
                ],
                [
                    'id'      => 'edu_by_age',
                    'title'   => 'शैक्षिक स्तर अनुसार उमेर समूह',
                    'title_en'=> 'Education Level by Age Group',
                    'data'    => $eduByAge,
                ],
                [
                    'id'      => 'literacy_by_gender',
                    'title'   => 'साक्षरता अनुसार लिङ्ग',
                    'title_en'=> 'Literacy by Gender',
                    'data'    => $literacyByGender,
                ],
                [
                    'id'      => 'vocational_by_gender',
                    'title'   => 'व्यावसायिक तालिम अनुसार लिङ्ग',
                    'title_en'=> 'Vocational Training by Gender',
                    'data'    => $vocTrainingByGender,
                ],
            ],
            'frequencies' => [
                [
                    'id'         => 'edu_status',
                    'title'      => 'शिक्षा अवस्था',
                    'title_en'   => 'Education Status',
                    'data'       => $eduStatusFreq,
                    'comparison' => $this->compFreq($lgMq, 'education_status', CrossTabService::optionLabels('education_status'), $lgCk . '_edu_status'),
                ],
                [
                    'id'         => 'school_type',
                    'title'      => 'विद्यालयको प्रकार',
                    'title_en'   => 'School Type',
                    'data'       => $schoolTypeFreq,
                    'comparison' => $this->compFreq($lgMq, 'school_type', CrossTabService::optionLabels('school_type'), $lgCk . '_school_type'),
                ],
                [
                    'id'         => 'not_in_school',
                    'title'      => 'विद्यालय नजाने कारण',
                    'title_en'   => 'Reason Not in School',
                    'data'       => $notInSchoolFreq,
                    'comparison' => $this->compFreq($lgMq, 'cause_not_school', CrossTabService::optionLabels('cause_not_school'), $lgCk . '_not_school'),
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Health & Disability
    // ─────────────────────────────────────────────────────────────────────────

    public function health(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('health', $lg->id, $request->only(['ward_id','settlement_id','gender']));
        $hasWard = $request->filled('ward_id');
        $lgMq    = $hasWard ? $this->lgMemberQuery($lg, $request) : null;
        $lgCk    = CrossTabService::cacheKey('health_lg', $lg->id, $request->only(['age_min','age_max','gender']));

        // Boolean columns stored as 0/1 tinyint — normalise to 'yes'/'no' for rowDef matching
        $boolExpr = fn(string $col) => "IF({$col} = 1 OR LOWER({$col}) = 'yes', 'yes', 'no')";

        $disabilityByGender = CrossTabService::tab(
            clone $mq,
            $boolExpr('disability_status'),
            'gender',
            ['yes' => 'अपाङ्गता छ', 'no' => 'अपाङ्गता छैन'],
            CrossTabService::genderLabels(),
            $ck . '_disxgender'
        );

        $disabilityTypeFreq = CrossTabService::freq(
            (clone $mq)->where('disability_status', 1),
            'disability_type', CrossTabService::optionLabels('disability_type'), $ck . '_dis_type'
        );

        $chronicByGender = CrossTabService::tab(
            clone $mq,
            $boolExpr('chronic_illness'),
            'gender',
            ['yes' => 'दीर्घ रोग छ', 'no' => 'दीर्घ रोग छैन'],
            CrossTabService::genderLabels(),
            $ck . '_chronicxgender'
        );

        $ssAllowanceFreq = CrossTabService::freq(
            clone $mq, 'ss_allowance', CrossTabService::optionLabels('ss_allowance'), $ck . '_ss_allowance'
        );

        $smokingByGender = CrossTabService::tab(
            clone $mq,
            $boolExpr('smoking_drinking'),
            'gender',
            ['yes' => 'छ', 'no' => 'छैन'],
            CrossTabService::genderLabels(),
            $ck . '_smokingxgender'
        );

        // Child health (age 0-3)
        $childMq = (clone $mq)->where('age', '<=', 3);
        $vaccineFreq = CrossTabService::freq(
            $childMq, 'childvaccine', CrossTabService::optionLabels('childvaccine'), $ck . '_vaccine'
        );

        // Child (4-5) Vitamin A
        $child45Mq = (clone $mq)->whereBetween('age', [4, 5]);
        $vitAFreq  = CrossTabService::freq(
            $child45Mq, 'vitaminA', CrossTabService::optionLabels('vitaminA'), $ck . '_vita'
        );

        // Child labour (6-15)
        $childLabourMq = (clone $mq)->whereBetween('age', [6, 15]);
        $childLabourByGender = CrossTabService::tab(
            $childLabourMq,
            $boolExpr('child_labor'),
            'gender',
            ['yes' => 'बाल श्रम', 'no' => 'बाल श्रम छैन'],
            CrossTabService::genderLabels(),
            $ck . '_childlaborxgender'
        );

        $bloodGroupFreq = CrossTabService::freq(
            clone $mq, 'blood_group', CrossTabService::optionLabels('blood_group'), $ck . '_blood_group'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'health',
            'title'       => 'स्वास्थ्य तथा अपाङ्गता विवरण',
            'title_en'    => 'Health & Disability',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'disability_by_gender',
                    'title'   => 'अपाङ्गता अनुसार लिङ्ग',
                    'title_en'=> 'Disability by Gender',
                    'data'    => $disabilityByGender,
                ],
                [
                    'id'      => 'chronic_by_gender',
                    'title'   => 'दीर्घ रोग अनुसार लिङ्ग',
                    'title_en'=> 'Chronic Illness by Gender',
                    'data'    => $chronicByGender,
                ],
                [
                    'id'      => 'smoking_by_gender',
                    'title'   => 'धुम्रपान/मद्यपान अनुसार लिङ्ग',
                    'title_en'=> 'Smoking/Drinking by Gender',
                    'data'    => $smokingByGender,
                ],
                [
                    'id'      => 'child_labour_by_gender',
                    'title'   => 'बाल श्रम अनुसार लिङ्ग (उमेर ६–१५)',
                    'title_en'=> 'Child Labour by Gender (age 6–15)',
                    'data'    => $childLabourByGender,
                ],
            ],
            'frequencies' => [
                [
                    'id'         => 'disability_type',
                    'title'      => 'अपाङ्गताको प्रकार',
                    'title_en'   => 'Type of Disability',
                    'data'       => $disabilityTypeFreq,
                    'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->where('disability_status', 1) : null, 'disability_type', CrossTabService::optionLabels('disability_type'), $lgCk . '_dis_type'),
                ],
                [
                    'id'         => 'ss_allowance',
                    'title'      => 'सामाजिक सुरक्षा भत्ता',
                    'title_en'   => 'Social Security Allowance',
                    'data'       => $ssAllowanceFreq,
                    'comparison' => $this->compFreq($lgMq, 'ss_allowance', CrossTabService::optionLabels('ss_allowance'), $lgCk . '_ss_allowance'),
                ],
                [
                    'id'         => 'blood_group',
                    'title'      => 'रक्त समूह',
                    'title_en'   => 'Blood Group',
                    'data'       => $bloodGroupFreq,
                    'comparison' => $this->compFreq($lgMq, 'blood_group', CrossTabService::optionLabels('blood_group'), $lgCk . '_blood_group'),
                ],
                [
                    'id'         => 'vaccine',
                    'title'      => 'खोप स्थिति (उमेर ०–३)',
                    'title_en'   => 'Vaccination Status (age 0–3)',
                    'data'       => $vaccineFreq,
                    'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->where('age', '<=', 3) : null, 'childvaccine', CrossTabService::optionLabels('childvaccine'), $lgCk . '_vaccine'),
                ],
                [
                    'id'         => 'vitamin_a',
                    'title'      => 'भिटामिन A (उमेर ४–५)',
                    'title_en'   => 'Vitamin A (age 4–5)',
                    'data'       => $vitAFreq,
                    'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->whereBetween('age', [4, 5]) : null, 'vitaminA', CrossTabService::optionLabels('vitaminA'), $lgCk . '_vita'),
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Employment & Occupation
    // ─────────────────────────────────────────────────────────────────────────

    public function employment(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('emp', $lg->id, $request->only(['ward_id','settlement_id','gender']));
        $hasWard = $request->filled('ward_id');
        $lgMq    = $hasWard ? $this->lgMemberQuery($lg, $request) : null;
        $lgCk    = CrossTabService::cacheKey('emp_lg', $lg->id, $request->only(['age_min','age_max','gender']));

        $occupationByGender = CrossTabService::tab(
            clone $mq,
            'occupation_type',
            'gender',
            CrossTabService::optionLabels('occupation_type'),
            CrossTabService::genderLabels(),
            $ck . '_occxgender'
        );

        $occupationByAge = CrossTabService::tab(
            clone $mq,
            'occupation_type',
            CrossTabService::ageGroupExpr(),
            CrossTabService::optionLabels('occupation_type'),
            CrossTabService::ageGroupLabels(),
            $ck . '_occxage'
        );

        $employStatusByGender = CrossTabService::tab(
            clone $mq,
            'employment_status',
            'gender',
            CrossTabService::optionLabels('employment_status'),
            CrossTabService::genderLabels(),
            $ck . '_empstatxgender'
        );

        $occupationFreq = CrossTabService::freq(
            clone $mq, 'occupation_type', CrossTabService::optionLabels('occupation_type'), $ck . '_occ'
        );

        $vtSubjectFreq = CrossTabService::freq(
            (clone $mq)->where('vocational_training', 1),
            'vt_subject',
            CrossTabService::optionLabels('vt_subject'),
            $ck . '_vt_subject'
        );

        $boolExpr = fn(string $col) => "IF({$col} = 1 OR LOWER({$col}) = 'yes', 'yes', 'no')";

        $techTrainingFreq = CrossTabService::freq(
            clone $mq, $boolExpr('techtraining'),
            ['yes' => 'प्राविधिक तालिम लिएको', 'no' => 'प्राविधिक तालिम नलिएको'],
            $ck . '_techtraining'
        );

        $saTrainingFreq = CrossTabService::freq(
            clone $mq, $boolExpr('sa_training'),
            ['yes' => 'साक्षरता/अन्य तालिम लिएको', 'no' => 'नलिएको'],
            $ck . '_sa_training'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'employment',
            'title'       => 'पेसा तथा रोजगारी विवरण',
            'title_en'    => 'Employment & Occupation',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'occupation_by_gender',
                    'title'   => 'पेसा अनुसार लिङ्ग',
                    'title_en'=> 'Occupation by Gender',
                    'data'    => $occupationByGender,
                ],
                [
                    'id'      => 'occupation_by_age',
                    'title'   => 'पेसा अनुसार उमेर समूह',
                    'title_en'=> 'Occupation by Age Group',
                    'data'    => $occupationByAge,
                ],
                [
                    'id'      => 'employment_status_by_gender',
                    'title'   => 'रोजगारी अवस्था अनुसार लिङ्ग',
                    'title_en'=> 'Employment Status by Gender',
                    'data'    => $employStatusByGender,
                ],
            ],
            'frequencies' => [
                [
                    'id'         => 'occupation',
                    'title'      => 'पेसाको वितरण',
                    'title_en'   => 'Occupation Distribution',
                    'data'       => $occupationFreq,
                    'comparison' => $this->compFreq($lgMq, 'occupation_type', CrossTabService::optionLabels('occupation_type'), $lgCk . '_occ'),
                ],
                [
                    'id'         => 'vt_subject',
                    'title'      => 'व्यावसायिक तालिमको विषय',
                    'title_en'   => 'Vocational Training Subject',
                    'data'       => $vtSubjectFreq,
                    'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->where('vocational_training', 1) : null, 'vt_subject', CrossTabService::optionLabels('vt_subject'), $lgCk . '_vt_subject'),
                ],
                [
                    'id'         => 'techtraining',
                    'title'      => 'प्राविधिक तालिम',
                    'title_en'   => 'Technical Training',
                    'data'       => $techTrainingFreq,
                    'comparison' => $this->compFreq($lgMq, $boolExpr('techtraining'), ['yes' => 'प्राविधिक तालिम लिएको', 'no' => 'प्राविधिक तालिम नलिएको'], $lgCk . '_techtraining'),
                ],
                [
                    'id'         => 'sa_training',
                    'title'      => 'साक्षरता/अन्य तालिम',
                    'title_en'   => 'Literacy / Other Training',
                    'data'       => $saTrainingFreq,
                    'comparison' => $this->compFreq($lgMq, $boolExpr('sa_training'), ['yes' => 'साक्षरता/अन्य तालिम लिएको', 'no' => 'नलिएको'], $lgCk . '_sa_training'),
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Housing
    // ─────────────────────────────────────────────────────────────────────────

    public function housing(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('housing', $lg->id, $request->only(['ward_id']));

        // Base housing query (published HH)
        $hq = fn () => HouseholdHousing::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $roofFreq        = CrossTabService::freq($hq(), 'roof_type',           CrossTabService::optionLabels('roof_type'),           $ck . '_roof');
        $wallFreq        = CrossTabService::freq($hq(), 'wall_type',           CrossTabService::optionLabels('wall_type'),           $ck . '_wall');
        $foundationFreq  = CrossTabService::freq($hq(), 'foundation_type',     CrossTabService::optionLabels('foundation_type'),     $ck . '_foundation');
        $ownershipFreq   = CrossTabService::freq($hq(), 'ownership_type',      CrossTabService::optionLabels('ownership_type'),      $ck . '_ownership');
        $conditionFreq   = CrossTabService::freq($hq(), 'house_condition',     CrossTabService::optionLabels('house_condition'),     $ck . '_condition');
        $houseTypeFreq   = CrossTabService::freq($hq(), 'house_type',          CrossTabService::optionLabels('house_type'),          $ck . '_house_type');
        $housePurposeFreq= CrossTabService::freq($hq(), 'house_purpose',       CrossTabService::optionLabels('house_purpose'),       $ck . '_house_purpose');
        $buildPermitFreq = CrossTabService::freq($hq(), 'has_building_permit', CrossTabService::optionLabels('has_building_permit'), $ck . '_permit');
        $riskFreq        = CrossTabService::freq($hq(), 'is_at_risk',          CrossTabService::optionLabels('is_at_risk'),          $ck . '_risk');

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $roofByWard = CrossTabService::tab(
            HouseholdHousing::whereHas('household', fn ($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_housing.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'household_housing.roof_type',
            'wards.ward_number',
            CrossTabService::optionLabels('roof_type'),
            $wardColDef,
            $ck . '_roofxward'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'housing',
            'title'       => 'आवास सम्बन्धी विवरण',
            'title_en'    => 'Housing',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'roof_by_ward',
                    'title'   => 'छाना प्रकार अनुसार वडा',
                    'title_en'=> 'Roof Type by Ward',
                    'data'    => $roofByWard,
                ],
            ],
            'frequencies' => [
                ['id' => 'roof',         'title' => 'छानाको प्रकार',    'title_en' => 'Roof Type',           'data' => $roofFreq],
                ['id' => 'wall',         'title' => 'गारोको प्रकार',    'title_en' => 'Wall Type',           'data' => $wallFreq],
                ['id' => 'foundation',   'title' => 'जगको प्रकार',      'title_en' => 'Foundation Type',     'data' => $foundationFreq],
                ['id' => 'ownership',    'title' => 'घर स्वामित्व',     'title_en' => 'Ownership Type',      'data' => $ownershipFreq],
                ['id' => 'condition',      'title' => 'घरको अवस्था',      'title_en' => 'House Condition',     'data' => $conditionFreq],
                ['id' => 'house_type',    'title' => 'घरको किसिम',       'title_en' => 'House Type',          'data' => $houseTypeFreq],
                ['id' => 'house_purpose', 'title' => 'घरको उद्देश्य',    'title_en' => 'House Purpose',       'data' => $housePurposeFreq],
                ['id' => 'build_permit',  'title' => 'नक्शा पास',        'title_en' => 'Building Permit',     'data' => $buildPermitFreq],
                ['id' => 'risk',          'title' => 'जोखिम अवस्था',     'title_en' => 'Disaster Risk',       'data' => $riskFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Utilities & Facilities
    // ─────────────────────────────────────────────────────────────────────────

    public function utilities(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('util', $lg->id, $request->only(['ward_id']));

        $uq = fn () => HouseholdUtility::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $waterFreq      = CrossTabService::freq($uq(), 'water_source',           CrossTabService::optionLabels('water_source'),            $ck . '_water');
        $toiletFreq     = CrossTabService::freq($uq(), 'toilet_type',            CrossTabService::optionLabels('toilet_type'),             $ck . '_toilet');
        $sanitationFreq = CrossTabService::freq($uq(), 'sanitation_type',        CrossTabService::optionLabels('sanitation_type'),         $ck . '_sanitation');
        $electricFreq   = CrossTabService::freq($uq(), 'electricity_source',     CrossTabService::optionLabels('electricity_source'),      $ck . '_electric');
        $fuelFreq       = CrossTabService::freq($uq(), 'cooking_fuel',           CrossTabService::optionLabels('cooking_fuel'),            $ck . '_fuel');
        $wasteFreq      = CrossTabService::freq($uq(), 'solid_waste_management', CrossTabService::optionLabels('solid_waste_management'),  $ck . '_waste');
        $internetFreq   = CrossTabService::freq($uq(), 'internet_type',          CrossTabService::optionLabels('internet_type'),           $ck . '_internet');
        $vehicleFreq    = CrossTabService::freq($uq(), 'vehicle',                CrossTabService::optionLabels('vehicle'),                 $ck . '_vehicle');
        $handwashFreq   = CrossTabService::freq($uq(), 'handwashing',            CrossTabService::optionLabels('handwashing'),             $ck . '_handwash');

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $waterByWard = CrossTabService::tab(
            HouseholdUtility::whereHas('household', fn ($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_utilities.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'household_utilities.water_source',
            'wards.ward_number',
            CrossTabService::optionLabels('water_source'),
            $wardColDef,
            $ck . '_waterxward'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'utilities',
            'title'       => 'सेवा तथा सुविधा विवरण',
            'title_en'    => 'Utilities & Facilities',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'water_by_ward',
                    'title'   => 'खानेपानी स्रोत अनुसार वडा',
                    'title_en'=> 'Water Source by Ward',
                    'data'    => $waterByWard,
                ],
            ],
            'frequencies' => [
                ['id' => 'water',      'title' => 'खानेपानीको स्रोत',    'title_en' => 'Water Source',         'data' => $waterFreq],
                ['id' => 'toilet',     'title' => 'शौचालयको प्रकार',     'title_en' => 'Toilet Type',          'data' => $toiletFreq],
                ['id' => 'sanitation', 'title' => 'सरसफाइ व्यवस्था',     'title_en' => 'Sanitation Type',      'data' => $sanitationFreq],
                ['id' => 'handwash',   'title' => 'हात धुने अभ्यास',     'title_en' => 'Handwashing Practice', 'data' => $handwashFreq],
                ['id' => 'electric',   'title' => 'विद्युत स्रोत',        'title_en' => 'Electricity Source',   'data' => $electricFreq],
                ['id' => 'fuel',       'title' => 'खाना पकाउने इन्धन',   'title_en' => 'Cooking Fuel',         'data' => $fuelFreq],
                ['id' => 'waste',      'title' => 'फोहर व्यवस्थापन',      'title_en' => 'Solid Waste Mgmt',     'data' => $wasteFreq],
                ['id' => 'internet',   'title' => 'इन्टरनेट सेवा',        'title_en' => 'Internet Type',        'data' => $internetFreq],
                ['id' => 'vehicle',    'title' => 'सवारी साधन',           'title_en' => 'Vehicle Ownership',    'data' => $vehicleFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Economics
    // ─────────────────────────────────────────────────────────────────────────

    public function economics(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('econ', $lg->id, $request->only(['ward_id']));

        $eq = fn () => HouseholdEconomic::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $incomeFreq     = CrossTabService::freq($eq(), 'primary_income_source',   CrossTabService::optionLabels('primary_income_source'),   $ck . '_income_src');
        $incomeRangeFreq= CrossTabService::freq($eq(), 'annual_income_range',      CrossTabService::optionLabels('annual_income_range'),      $ck . '_income_range');
        $expendFreq     = CrossTabService::freq($eq(), 'annual_expenditure_range', CrossTabService::optionLabels('annual_expenditure_range'), $ck . '_expend');
        $bankFreq       = CrossTabService::freq($eq(), 'has_bank_account',         ['1'=>'बैंक खाता छ','0'=>'बैंक खाता छैन'],               $ck . '_bank');
        $povertyFreq    = CrossTabService::freq($eq(), 'poverty_card',             ['1'=>'परिचयपत्र छ','0'=>'परिचयपत्र छैन'],                $ck . '_poverty');
        $insuranceFreq  = CrossTabService::freq($eq(), 'has_insurance',            CrossTabService::optionLabels('has_insurance'),            $ck . '_insurance');
        $loanSourceFreq = $this->jsonArrayFreq($eq(), 'loan_sources', CrossTabService::optionLabels('loan_source'), $ck . '_loan_sources');

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $incomeByWard = CrossTabService::tab(
            HouseholdEconomic::whereHas('household', fn ($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_economic.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'household_economic.primary_income_source',
            'wards.ward_number',
            CrossTabService::optionLabels('primary_income_source'),
            $wardColDef,
            $ck . '_incomexward'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'economics',
            'title'       => 'आर्थिक विवरण',
            'title_en'    => 'Economics',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'income_by_ward',
                    'title'   => 'आय स्रोत अनुसार वडा',
                    'title_en'=> 'Income Source by Ward',
                    'data'    => $incomeByWard,
                ],
            ],
            'frequencies' => [
                ['id' => 'income_src',   'title' => 'मुख्य आय स्रोत',       'title_en' => 'Primary Income Source',  'data' => $incomeFreq],
                ['id' => 'income_range', 'title' => 'वार्षिक आय दायरा',      'title_en' => 'Annual Income Range',    'data' => $incomeRangeFreq],
                ['id' => 'expend',       'title' => 'वार्षिक खर्च दायरा',    'title_en' => 'Annual Expenditure',     'data' => $expendFreq],
                ['id' => 'loan_sources', 'title' => 'ऋणको स्रोत',              'title_en' => 'Loan Sources',           'data' => $loanSourceFreq],
                ['id' => 'bank',         'title' => 'बैंक खाता',              'title_en' => 'Bank Account',           'data' => $bankFreq],
                ['id' => 'insurance',    'title' => 'बीमा',                   'title_en' => 'Insurance Coverage',     'data' => $insuranceFreq],
                ['id' => 'poverty',      'title' => 'गरिबी परिचयपत्र',        'title_en' => 'Poverty Card',           'data' => $povertyFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Age Groups (detailed population pyramid data)
    // ─────────────────────────────────────────────────────────────────────────

    public function ageGroups(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('age', $lg->id, $request->only(['ward_id','settlement_id']));

        $ageByGender = CrossTabService::tab(
            clone $mq,
            CrossTabService::ageGroupExpr(),
            'gender',
            CrossTabService::ageGroupLabels(),
            CrossTabService::genderLabels(),
            $ck . '_agexgender'
        );

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $ageByWardQ = (clone $mq)
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->join('wards', 'households.ward_id', '=', 'wards.id');

        $ageByWard = CrossTabService::tab(
            $ageByWardQ,
            CrossTabService::ageGroupExpr('household_members.age'),
            'wards.ward_number',
            CrossTabService::ageGroupLabels(),
            $wardColDef,
            $ck . '_agexward'
        );

        $ageByEthnicity = CrossTabService::tab(
            clone $mq,
            CrossTabService::ageGroupExpr(),
            'ethnicity',
            CrossTabService::ageGroupLabels(),
            CrossTabService::optionLabels('ethnicity'),
            $ck . '_agexethnicity'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'age-groups',
            'title'       => 'उमेर समूह विवरण',
            'title_en'    => 'Age Groups',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'age_by_gender',
                    'title'   => 'उमेर समूह अनुसार लिङ्ग',
                    'title_en'=> 'Age Group by Gender',
                    'data'    => $ageByGender,
                    'chart'   => 'pyramid',
                ],
                [
                    'id'      => 'age_by_ward',
                    'title'   => 'उमेर समूह अनुसार वडा',
                    'title_en'=> 'Age Group by Ward',
                    'data'    => $ageByWard,
                ],
                [
                    'id'      => 'age_by_ethnicity',
                    'title'   => 'उमेर समूह अनुसार जाति/जनजाति',
                    'title_en'=> 'Age Group by Ethnicity',
                    'data'    => $ageByEthnicity,
                ],
            ],
            'frequencies' => [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. Agriculture & Livestock
    // ─────────────────────────────────────────────────────────────────────────

    public function agriculture(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('agri', $lg->id, $request->only(['ward_id']));

        $aq = fn () => HouseholdAgriculture::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $agriFreq           = CrossTabService::freq($aq(), 'agriculture',              CrossTabService::optionLabels('agriculture'),              $ck . '_agri');
        $landFreq           = CrossTabService::freq($aq(), 'owns_land',               CrossTabService::optionLabels('owns_land'),                $ck . '_land');
        $irrigationFreq     = CrossTabService::freq($aq(), 'irrigation_source',        CrossTabService::optionLabels('irrigation_source'),        $ck . '_irrigation');
        $chemFertFreq       = CrossTabService::freq($aq(), 'uses_chemical_fertilizer', CrossTabService::optionLabels('uses_chemical_fertilizer'), $ck . '_chem_fert');
        $orgFertFreq        = CrossTabService::freq($aq(), 'uses_organic_fertilizer',  CrossTabService::optionLabels('uses_organic_fertilizer'),  $ck . '_org_fert');
        $sellsFreq          = CrossTabService::freq($aq(), 'sells_produce',            CrossTabService::optionLabels('sells_produce'),            $ck . '_sells');
        $agriLoanFreq       = CrossTabService::freq($aq(), 'has_agri_loan',            CrossTabService::optionLabels('has_agri_loan'),            $ck . '_agri_loan');
        $agriCategoryFreq   = $this->jsonArrayFreq($aq(), 'agri_categories',  CrossTabService::optionLabels('agri_category'), $ck . '_agri_cat');
        $farmChallengeFreq  = $this->jsonArrayFreq($aq(), 'Farm_Challenges',  CrossTabService::optionLabels('farm_challenge'),        $ck . '_farm_challenge');

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $agriByWard = CrossTabService::tab(
            HouseholdAgriculture::whereHas('household', fn ($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_agriculture.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'household_agriculture.agriculture',
            'wards.ward_number',
            CrossTabService::optionLabels('agriculture'),
            $wardColDef,
            $ck . '_agrixward'
        );

        // Livestock: count households with each livestock type
        $lq = fn () => HouseholdLivestock::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );
        $livestockFreq = CrossTabService::freq(
            $lq(), 'livestock_type', CrossTabService::optionLabels('livestock_kind'), $ck . '_livestock'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'agriculture',
            'title'       => 'कृषि तथा पशुपालन विवरण',
            'title_en'    => 'Agriculture & Livestock',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'agri_by_ward',
                    'title'   => 'कृषि अभ्यास अनुसार वडा',
                    'title_en'=> 'Agriculture by Ward',
                    'data'    => $agriByWard,
                ],
            ],
            'frequencies' => array_values(array_filter([
                $agriFreq         ? ['id' => 'agri',          'title' => 'कृषि अभ्यास',           'title_en' => 'Agriculture Practice',        'data' => $agriFreq]         : null,
                $landFreq         ? ['id' => 'land',          'title' => 'जमिन स्वामित्व',         'title_en' => 'Land Ownership',              'data' => $landFreq]         : null,
                $irrigationFreq   ? ['id' => 'irrigation',    'title' => 'सिँचाइको स्रोत',         'title_en' => 'Irrigation Source',           'data' => $irrigationFreq]   : null,
                $agriCategoryFreq ? ['id' => 'agri_cat',      'title' => 'बाली/कृषि श्रेणी',       'title_en' => 'Crop / Agriculture Category', 'data' => $agriCategoryFreq] : null,
                $farmChallengeFreq? ['id' => 'farm_challenge','title' => 'कृषि समस्याहरू',          'title_en' => 'Farm Challenges',             'data' => $farmChallengeFreq]: null,
                $chemFertFreq     ? ['id' => 'chem_fert',     'title' => 'रासायनिक मल प्रयोग',   'title_en' => 'Chemical Fertilizer Use',     'data' => $chemFertFreq]     : null,
                $orgFertFreq      ? ['id' => 'org_fert',      'title' => 'जैविक मल प्रयोग',      'title_en' => 'Organic Fertilizer Use',      'data' => $orgFertFreq]      : null,
                $sellsFreq        ? ['id' => 'sells',         'title' => 'उपज बिक्री',            'title_en' => 'Sells Produce',               'data' => $sellsFreq]        : null,
                $agriLoanFreq     ? ['id' => 'agri_loan',     'title' => 'कृषि ऋण',               'title_en' => 'Agricultural Loan',           'data' => $agriLoanFreq]     : null,
                $livestockFreq    ? ['id' => 'livestock',     'title' => 'पशुपालनको प्रकार',      'title_en' => 'Livestock Type',              'data' => $livestockFreq]    : null,
            ])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. Migration & Abroad Employment
    // ─────────────────────────────────────────────────────────────────────────

    public function migration(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('migr', $lg->id, $request->only(['ward_id', 'settlement_id', 'gender']));
        $hasWard = $request->filled('ward_id');
        $lgMq    = $hasWard ? $this->lgMemberQuery($lg, $request) : null;
        $lgCk    = CrossTabService::cacheKey('migr_lg', $lg->id, $request->only(['age_min','age_max','gender']));

        // Kobo stores full Nepali text in addrslocation — map to shorter display labels
        $addrLabels = [
            'गणना गरेको ठाउँमा बसेको'                         => 'गणनास्थलमा उपस्थित',
            'स्वदेशमा अन्यत्र (६ महिना भन्दा बढी समय देखि)'   => 'स्वदेशमा अन्यत्र',
            'विदेशमा बसेको (६ महिना भन्दा बढी समय देखि)'      => 'विदेशमा',
        ];

        // 1. Overall residence distribution
        $residenceFreq = CrossTabService::freq(
            clone $mq, 'addrslocation', $addrLabels, $ck . '_residence'
        );

        // 2. Residence × gender
        $residenceByGender = CrossTabService::tab(
            clone $mq,
            'addrslocation',
            'gender',
            $addrLabels,
            CrossTabService::genderLabels(),
            $ck . '_resxgender'
        );

        // 3. Residence × ward
        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $residenceByWard = CrossTabService::tab(
            (clone $mq)
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'household_members.addrslocation',
            'wards.ward_number',
            $addrLabels,
            $wardColDef,
            $ck . '_resxward'
        );

        // 4. Internal migration — district-wise breakdown
        $internalKey  = 'स्वदेशमा अन्यत्र (६ महिना भन्दा बढी समय देखि)';
        $internalMq   = (clone $mq)->where('addrslocation', $internalKey)->whereNotNull('residing_district')->where('residing_district', '!=', '');
        $districtFreq = CrossTabService::freq(
            $internalMq, 'residing_district', [], $ck . '_district'
        );

        // 5. Abroad — job type & status (may be empty if not captured in this sync version)
        $abroadKey    = 'विदेशमा बसेको (६ महिना भन्दा बढी समय देखि)';
        $abroadMq     = (clone $mq)->where('addrslocation', $abroadKey);
        $abroadCount  = (clone $abroadMq)->count();

        $abroadJobFreq    = $abroadCount ? CrossTabService::freq(clone $abroadMq, 'typeofabroadjob', CrossTabService::optionLabels('typeofabroadjob'), $ck . '_abroad_job')    : [];
        $abroadStatusFreq = $abroadCount ? CrossTabService::freq(clone $abroadMq, 'abroadjobstatus', CrossTabService::optionLabels('abroadjobstatus'), $ck . '_abroad_status') : [];
        // residing_country is JSON-encoded in DB (array cast); decode with JSON_UNQUOTE for proper grouping
        $countryExpr  = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(residing_country, '$[0]')), JSON_UNQUOTE(residing_country))";
        $countryFreq  = $abroadCount ? CrossTabService::freq(clone $abroadMq, $countryExpr, [], $ck . '_country') : [];

        // Build tables — only include abroad cross-tabs if there's data
        $tables = [
            [
                'id'       => 'residence_by_gender',
                'title'    => 'बसोबास अवस्था अनुसार लिङ्ग',
                'title_en' => 'Residence Status by Gender',
                'data'     => $residenceByGender,
            ],
            [
                'id'       => 'residence_by_ward',
                'title'    => 'बसोबास अवस्था अनुसार वडा',
                'title_en' => 'Residence Status by Ward',
                'data'     => $residenceByWard,
            ],
        ];

        // Build frequencies — filter out empty ones
        $frequencies = array_values(array_filter([
            $residenceFreq  ? ['id' => 'residence',     'title' => 'बसोबास अवस्था',              'title_en' => 'Residence Status',            'data' => $residenceFreq,    'comparison' => $this->compFreq($lgMq, 'addrslocation', $addrLabels, $lgCk . '_residence')] : null,
            $districtFreq   ? ['id' => 'district',      'title' => 'स्वदेश बसाइँसरीको जिल्ला',   'title_en' => 'Internal Migration District', 'data' => $districtFreq]   : null,
            $countryFreq    ? ['id' => 'country',       'title' => 'विदेशमा बसेको देश',           'title_en' => 'Country of Residence Abroad', 'data' => $countryFreq]    : null,
            $abroadJobFreq  ? ['id' => 'abroad_job',    'title' => 'विदेशी रोजगारको प्रकार',     'title_en' => 'Type of Abroad Job',          'data' => $abroadJobFreq]  : null,
            $abroadStatusFreq ? ['id' => 'abroad_status','title' => 'विदेशी रोजगार अवस्था',      'title_en' => 'Abroad Job Status',           'data' => $abroadStatusFreq] : null,
        ]));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'migration',
            'title'       => 'बसाइँसराइ तथा वैदेशिक रोजगार',
            'title_en'    => 'Migration & Abroad Employment',
            ...$this->filterProps($lg, $request),
            'tables'      => $tables,
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. Child Development & Health
    // ─────────────────────────────────────────────────────────────────────────

    public function childDevelopment(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('child', $lg->id, $request->only(['ward_id', 'settlement_id']));

        // Scope queries to each child age bracket
        $childMq  = (clone $mq)->whereNotNull('age')->where('age', '>=', 0)->where('age', '<=', 15);
        $mq03     = (clone $childMq)->where('age', '<=', 3);
        $mq45     = (clone $childMq)->where('age', '>=', 4)->where('age', '<=', 5);
        $mq615    = (clone $childMq)->where('age', '>=', 6)->where('age', '<=', 15);

        $total03  = (clone $mq03)->count();
        $total45  = (clone $mq45)->count();
        $total615 = (clone $mq615)->count();

        // ── Age group distribution ────────────────────────────────────────────
        $ageGroupFreq = array_values(array_filter([
            $total03  ? ['key' => '0-3',  'label' => 'शिशु (०–३ वर्ष)',           'count' => $total03]  : null,
            $total45  ? ['key' => '4-5',  'label' => 'पूर्व-विद्यालय (४–५ वर्ष)', 'count' => $total45]  : null,
            $total615 ? ['key' => '6-15', 'label' => 'विद्यालय उमेर (६–१५ वर्ष)', 'count' => $total615] : null,
        ]));

        // ── Age group × ward cross-tab ────────────────────────────────────────
        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);
        $ageExpr    = "CASE WHEN age <= 3 THEN '0-3' WHEN age <= 5 THEN '4-5' WHEN age <= 15 THEN '6-15' ELSE NULL END";
        $ageRowDef  = ['0-3' => 'शिशु (०–३)', '4-5' => 'पूर्व-विद्यालय (४–५)', '6-15' => 'विद्यालय (६–१५)'];

        $childByWard = CrossTabService::tab(
            (clone $childMq)
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->join('wards', 'households.ward_id', '=', 'wards.id'),
            $ageExpr,
            'wards.ward_number',
            $ageRowDef,
            $wardColDef,
            $ck . '_child_ward'
        );

        $childByGender = CrossTabService::tab(
            clone $childMq,
            $ageExpr,
            'gender',
            $ageRowDef,
            CrossTabService::genderLabels(),
            $ck . '_child_gender'
        );

        // ── Boolean indicator helper ──────────────────────────────────────────
        $boolFreq = function ($query, string $col, int $total, string $yesLabel, string $noLabel): array {
            if (!$total) return [];
            $yes = (clone $query)->where($col, true)->count();
            $no  = $total - $yes;
            return array_values(array_filter([
                $yes ? ['key' => 'yes', 'label' => $yesLabel, 'count' => $yes] : null,
                $no  ? ['key' => 'no',  'label' => $noLabel,  'count' => $no]  : null,
            ]));
        };

        // ── 0–3 year indicators ───────────────────────────────────────────────
        $frequencies = [];

        if ($ageGroupFreq) {
            $frequencies[] = ['id' => 'age_group', 'title' => 'उमेर समूह वितरण', 'title_en' => 'Children by Age Group', 'data' => $ageGroupFreq];
        }

        foreach ([
            ['colostrum',               'कोलोस्ट्रम/पहिलो दूध दिएको',     'Colostrum / First Milk',       'दिएको', 'दिएको छैन'],
            ['vit_a_dewormin',          'भिटामिन A र जुका औषधि',          'Vitamin A & Deworming',        'दिएको', 'दिएको छैन'],
            ['fullmilkcourse',          'पूर्ण स्तनपान गराएको',            'Full Breastfeeding Course',    'गराएको', 'गराएको छैन'],
            ['childhealthcheckup',      'स्वास्थ्य जाँच नियमित गराएको',   'Regular Health Checkup',      'गराएको', 'गराएको छैन'],
            ['diarrhea_before_two_weeks','पखाला (२ हप्तामा)',              'Diarrhea (last 2 weeks)',      'भएको',   'भएको छैन'],
            ['fever_resp',              'ज्वरो/सास समस्या',               'Fever or Respiratory Issue',   'भएको',   'भएको छैन'],
        ] as [$col, $title, $titleEn, $yes, $no]) {
            $data = $boolFreq($mq03, $col, $total03, $yes, $no);
            if ($data) $frequencies[] = ['id' => $col, 'title' => $title . ' (०–३ वर्ष)', 'title_en' => $titleEn . ' (0–3 yrs)', 'data' => $data];
        }

        // ── 4–5 year indicators ───────────────────────────────────────────────
        foreach ([
            ['vitaminA',         'भिटामिन A दिएको',          'Vitamin A (4–5 yrs)',        'दिएको', 'दिएको छैन'],
            ['childhealthcheckup','स्वास्थ्य जाँच गराएको',    'Health Checkup (4–5 yrs)',   'गराएको', 'गराएको छैन'],
        ] as [$col, $title, $titleEn, $yes, $no]) {
            $data = $boolFreq($mq45, $col, $total45, $yes, $no);
            if ($data) $frequencies[] = ['id' => $col . '_45', 'title' => $title . ' (४–५ वर्ष)', 'title_en' => $titleEn, 'data' => $data];
        }

        // ── 6–15 year indicators ──────────────────────────────────────────────
        foreach ([
            ['phy_dev_6_15',          'शारीरिक विकास सामान्य',   'Normal Physical Development (6–15)', 'सामान्य', 'समस्या छ'],
            ['allergy_infection_6_15', 'एलर्जी/संक्रमण समस्या',  'Allergy or Infection (6–15)',        'छ',       'छैन'],
            ['watch_mobile_tv_6_15',   'मोबाइल/टिभी हेर्ने बानी', 'Screen Time Habit (6–15)',           'छ',       'छैन'],
        ] as [$col, $title, $titleEn, $yes, $no]) {
            $data = $boolFreq($mq615, $col, $total615, $yes, $no);
            if ($data) $frequencies[] = ['id' => $col, 'title' => $title . ' (६–१५ वर्ष)', 'title_en' => $titleEn, 'data' => $data];
        }

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'child-development',
            'title'       => 'बाल विकास तथा स्वास्थ्य',
            'title_en'    => 'Child Development & Health',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'       => 'child_by_ward',
                    'title'    => 'उमेर समूह अनुसार वडागत बाल वितरण',
                    'title_en' => 'Children by Age Group & Ward',
                    'data'     => $childByWard,
                ],
                [
                    'id'       => 'child_by_gender',
                    'title'    => 'उमेर समूह अनुसार लिङ्ग',
                    'title_en' => 'Children by Age Group & Gender',
                    'data'     => $childByGender,
                ],
            ],
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 13. Identity Documents
    // ─────────────────────────────────────────────────────────────────────────

    public function documents(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('docs', $lg->id, $request->only(['ward_id', 'settlement_id', 'gender']));

        $boolFreq = function ($query, string $col, int $total, string $yes, string $no): array {
            if (!$total) return [];
            $yesCount = (clone $query)->where($col, true)->count();
            $noCount  = $total - $yesCount;
            return array_values(array_filter([
                $yesCount ? ['key' => 'yes', 'label' => $yes, 'count' => $yesCount] : null,
                $noCount  ? ['key' => 'no',  'label' => $no,  'count' => $noCount]  : null,
            ]));
        };

        // Members aged 16+ for citizenship/voters/passport
        $adults   = (clone $mq)->where('age', '>=', 16);
        $voters   = (clone $mq)->where('age', '>=', 18);
        $youth    = (clone $mq)->where('age', '<=', 18);
        $totalAll = (clone $mq)->count();
        $totalAdults  = $adults->count();
        $totalVoters  = $voters->count();
        $totalYouth   = $youth->count();

        $citizenshipByGender = CrossTabService::tab(
            clone $adults,
            'has_citizenship',
            'gender',
            ['1' => 'नागरिकता छ', '0' => 'नागरिकता छैन'],
            CrossTabService::genderLabels(),
            $ck . '_citxgender'
        );

        $votersCardByGender = CrossTabService::tab(
            clone $voters,
            'has_voters_card',
            'gender',
            ['1' => 'मतदाता परिचयपत्र छ', '0' => 'छैन'],
            CrossTabService::genderLabels(),
            $ck . '_voterxgender'
        );

        $nationalIdByGender = CrossTabService::tab(
            clone $mq,
            'has_national_id_card',
            'gender',
            ['1' => 'राष्ट्रिय परिचयपत्र छ', '0' => 'छैन'],
            CrossTabService::genderLabels(),
            $ck . '_nidxgender'
        );

        $frequencies = [
            ['id' => 'citizenship',    'title' => 'नागरिकता (१६+ वर्ष)',          'title_en' => 'Citizenship (age 16+)',        'data' => $boolFreq($adults, 'has_citizenship',    $totalAdults, 'नागरिकता छ',          'नागरिकता छैन')],
            ['id' => 'voters_card',    'title' => 'मतदाता परिचयपत्र (१८+ वर्ष)', 'title_en' => 'Voters Card (age 18+)',        'data' => $boolFreq($voters, 'has_voters_card',    $totalVoters, 'मतदाता परिचयपत्र छ',  'छैन')],
            ['id' => 'national_id',    'title' => 'राष्ट्रिय परिचयपत्र',          'title_en' => 'National ID Card',             'data' => $boolFreq($mq,     'has_national_id_card',$totalAll,    'राष्ट्रिय परिचयपत्र छ','छैन')],
            ['id' => 'passport',       'title' => 'राहदानी (Passport)',           'title_en' => 'Passport',                     'data' => $boolFreq($mq,     'has_passport',        $totalAll,    'राहदानी छ',            'राहदानी छैन')],
            ['id' => 'pan',            'title' => 'प्यान (PAN)',                   'title_en' => 'PAN Card',                     'data' => $boolFreq($mq,     'has_pan',             $totalAll,    'प्यान छ',              'प्यान छैन')],
            ['id' => 'drivers_license','title' => 'सवारी चालक अनुमतिपत्र',       'title_en' => 'Driving Licence',              'data' => $boolFreq($mq,     'has_drivers_license', $totalAll,    'चालक अनुमतिपत्र छ',   'छैन')],
            ['id' => 'birth_cert',     'title' => 'जन्मदर्ता (१८ वर्षमुनि)',      'title_en' => 'Birth Certificate (under 18)', 'data' => $boolFreq($youth,  'has_birth_certificate',$totalYouth, 'जन्मदर्ता छ',          'छैन')],
        ];

        // Remove empty frequency arrays
        $frequencies = array_values(array_filter($frequencies, fn ($f) => !empty($f['data'])));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'documents',
            'title'       => 'परिचयपत्र तथा कागजात विवरण',
            'title_en'    => 'Identity Documents',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'citizenship_by_gender',
                    'title'   => 'नागरिकता अनुसार लिङ्ग (१६+ वर्ष)',
                    'title_en'=> 'Citizenship by Gender (age 16+)',
                    'data'    => $citizenshipByGender,
                ],
                [
                    'id'      => 'voters_by_gender',
                    'title'   => 'मतदाता परिचयपत्र अनुसार लिङ्ग (१८+ वर्ष)',
                    'title_en'=> 'Voters Card by Gender (age 18+)',
                    'data'    => $votersCardByGender,
                ],
                [
                    'id'      => 'nid_by_gender',
                    'title'   => 'राष्ट्रिय परिचयपत्र अनुसार लिङ्ग',
                    'title_en'=> 'National ID by Gender',
                    'data'    => $nationalIdByGender,
                ],
            ],
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 14. Disaster & Risk
    // ─────────────────────────────────────────────────────────────────────────

    public function disaster(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('disaster', $lg->id, $request->only(['ward_id']));

        $dq = fn () => HouseholdDisaster::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $total = $dq()->count();

        $boolFreq = function ($col, string $yes, string $no) use ($dq, $total, $ck): array {
            if (!$total) return [];
            $yesCount = $dq()->where($col, true)->count();
            $noCount  = $total - $yesCount;
            return array_values(array_filter([
                $yesCount ? ['key' => 'yes', 'label' => $yes, 'count' => $yesCount] : null,
                $noCount  ? ['key' => 'no',  'label' => $no,  'count' => $noCount]  : null,
            ]));
        };

        // Risk zone frequencies
        $riskZones = [];
        foreach ([
            ['is_flood_zone',     'बाढी जोखिम क्षेत्र',   'बाढी जोखिम छ',    'बाढी जोखिम छैन'],
            ['is_landslide_zone', 'पहिरो जोखिम क्षेत्र',  'पहिरो जोखिम छ',   'पहिरो जोखिम छैन'],
            ['is_drought_zone',   'खडेरी जोखिम क्षेत्र',  'खडेरी जोखिम छ',   'खडेरी जोखिम छैन'],
        ] as [$col, , $yes, $no]) {
            $count = $dq()->where($col, true)->count();
            if ($count) $riskZones[] = ['key' => $col, 'label' => match($col) {
                'is_flood_zone'     => 'बाढी',
                'is_landslide_zone' => 'पहिरो',
                default             => 'खडेरी',
            }, 'count' => $count];
        }

        $riskTypeFreq     = $this->jsonArrayFreq($dq(), 'risk_types', CrossTabService::optionLabels('disaster_type'), $ck . '_risk_types');
        $evacuationFreq   = $boolFreq('has_evacuation_plan',  'निकासी योजना छ', 'निकासी योजना छैन');
        $trainingFreq     = $boolFreq('disaster_training',    'तालिम लिएको',    'तालिम नलिएको');
        $affectedFreq     = $boolFreq('affected_last_3years', 'प्रभावित भएको',  'प्रभावित भएको छैन');
        $reliefFreq       = $boolFreq('received_relief',      'राहत पाएको',     'राहत पाएको छैन');
        $riskPlanFreq     = $boolFreq('risk_reduction_plan',  'जोखिम न्यूनीकरण योजना छ', 'योजना छैन');

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $riskByWard = CrossTabService::tab(
            HouseholdDisaster::whereHas('household', fn ($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_disaster.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            "CASE WHEN is_flood_zone=1 AND is_landslide_zone=1 THEN 'बाढी/पहिरो दुवै' WHEN is_flood_zone=1 THEN 'बाढी' WHEN is_landslide_zone=1 THEN 'पहिरो' WHEN is_drought_zone=1 THEN 'खडेरी' ELSE 'जोखिम छैन' END",
            'wards.ward_number',
            ['बाढी' => 'बाढी', 'पहिरो' => 'पहिरो', 'बाढी/पहिरो दुवै' => 'बाढी/पहिरो दुवै', 'खडेरी' => 'खडेरी', 'जोखिम छैन' => 'जोखिम छैन'],
            $wardColDef,
            $ck . '_riskxward'
        );

        $frequencies = array_values(array_filter([
            $riskZones       ? ['id' => 'risk_zones',   'title' => 'जोखिम क्षेत्र',            'title_en' => 'Risk Zones',              'data' => $riskZones]     : null,
            $riskTypeFreq    ? ['id' => 'risk_types',   'title' => 'जोखिमका प्रकार',           'title_en' => 'Types of Risk',           'data' => $riskTypeFreq]  : null,
            $evacuationFreq  ? ['id' => 'evacuation',   'title' => 'निकासी योजना',             'title_en' => 'Evacuation Plan',         'data' => $evacuationFreq]: null,
            $riskPlanFreq    ? ['id' => 'risk_plan',    'title' => 'जोखिम न्यूनीकरण योजना',    'title_en' => 'Risk Reduction Plan',     'data' => $riskPlanFreq]  : null,
            $trainingFreq    ? ['id' => 'training',     'title' => 'विपद् तालिम',              'title_en' => 'Disaster Training',       'data' => $trainingFreq]  : null,
            $affectedFreq    ? ['id' => 'affected',     'title' => 'विपदबाट प्रभावित (३ वर्ष)', 'title_en' => 'Disaster Affected (3 yrs)','data' => $affectedFreq] : null,
            $reliefFreq      ? ['id' => 'relief',       'title' => 'राहत प्राप्त',             'title_en' => 'Relief Received',         'data' => $reliefFreq]    : null,
        ]));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'disaster',
            'title'       => 'विपद् तथा जोखिम विवरण',
            'title_en'    => 'Disaster & Risk',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'risk_by_ward',
                    'title'   => 'जोखिम क्षेत्र अनुसार वडा',
                    'title_en'=> 'Risk Zone by Ward',
                    'data'    => $riskByWard,
                ],
            ],
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 15. Women Empowerment
    // ─────────────────────────────────────────────────────────────────────────

    public function womenEmpowerment(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('women', $lg->id, $request->only(['ward_id', 'settlement_id']));
        $hasWard = $request->filled('ward_id');
        $lgMq    = $hasWard ? $this->lgMemberQuery($lg, $request) : null;
        $lgCk    = CrossTabService::cacheKey('women_lg', $lg->id, $request->only(['age_min','age_max','gender']));

        $litExpr = "CASE WHEN LOWER(education_level) NOT IN ('illitrate','illiterate') AND education_level IS NOT NULL AND education_level != '' THEN 'साक्षर' ELSE 'निरक्षर' END";

        $litByGender = CrossTabService::tab(
            clone $mq,
            $litExpr,
            'gender',
            ['साक्षर' => 'साक्षर', 'निरक्षर' => 'निरक्षर'],
            CrossTabService::genderLabels(),
            $ck . '_lit_gender'
        );

        $citizenshipByGender = CrossTabService::tab(
            clone($mq)->where('age', '>=', 16),
            'has_citizenship',
            'gender',
            ['1' => 'नागरिकता छ', '0' => 'नागरिकता छैन'],
            CrossTabService::genderLabels(),
            $ck . '_cit_gender'
        );

        $femaleOccupation = CrossTabService::freq(
            clone($mq)->where('gender', 'female'),
            'occupation_type',
            CrossTabService::optionLabels('occupation'),
            $ck . '_f_occ'
        );

        $femaleEmployment = CrossTabService::freq(
            clone($mq)->where('gender', 'female'),
            'employment_status',
            CrossTabService::optionLabels('employment_status'),
            $ck . '_f_emp'
        );

        $maritalFemale = CrossTabService::freq(
            clone($mq)->where('gender', 'female')->where('age', '>=', 15),
            'marital_status',
            CrossTabService::optionLabels('marital_status'),
            $ck . '_marital_f'
        );

        // ss_allowance is boolean 0/1 — use boolFreq pattern
        $femaleSsYes = (clone $mq)->where('gender', 'female')->where('ss_allowance', true)->count();
        $femaleSsNo  = (clone $mq)->where('gender', 'female')->where('ss_allowance', false)->count();
        $ssAllowanceFemale = array_values(array_filter([
            $femaleSsYes ? ['key' => 'yes', 'label' => 'सामाजिक सुरक्षा भत्ता पाउँछ', 'count' => $femaleSsYes] : null,
            $femaleSsNo  ? ['key' => 'no',  'label' => 'पाउँदैनन्',                     'count' => $femaleSsNo]  : null,
        ]));

        $femaleDisability = CrossTabService::freq(
            clone($mq)->where('gender', 'female')->where('disability_status', true),
            'disability_type',
            CrossTabService::optionLabels('disability_type'),
            $ck . '_f_dis'
        );

        $frequencies = array_values(array_filter([
            ['id' => 'female_occupation',   'title' => 'महिला — पेसाको प्रकार',         'title_en' => 'Women by Occupation',     'data' => $femaleOccupation,   'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->where('gender', 'female') : null, 'occupation_type', CrossTabService::optionLabels('occupation'), $lgCk . '_f_occ')],
            ['id' => 'female_employment',   'title' => 'महिला — रोजगारी अवस्था',         'title_en' => 'Women Employment Status', 'data' => $femaleEmployment,   'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->where('gender', 'female') : null, 'employment_status', CrossTabService::optionLabels('employment_status'), $lgCk . '_f_emp')],
            ['id' => 'marital_female',      'title' => 'महिला (१५+) — वैवाहिक अवस्था',  'title_en' => 'Marital Status of Women', 'data' => $maritalFemale,      'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->where('gender', 'female')->where('age', '>=', 15) : null, 'marital_status', CrossTabService::optionLabels('marital_status'), $lgCk . '_marital_f')],
            ['id' => 'ss_allowance_female', 'title' => 'महिला — सामाजिक सुरक्षा भत्ता', 'title_en' => 'Women Social Security',  'data' => $ssAllowanceFemale],
            ['id' => 'female_disability',   'title' => 'महिला — अपाङ्गताको प्रकार',     'title_en' => 'Female Disability Types', 'data' => $femaleDisability,   'comparison' => $this->compFreq($lgMq ? (clone $lgMq)->where('gender', 'female')->where('disability_status', true) : null, 'disability_type', CrossTabService::optionLabels('disability_type'), $lgCk . '_f_dis')],
        ], fn($f) => !empty($f['data'])));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'women-empowerment',
            'title'       => 'महिला सशक्तीकरण',
            'title_en'    => 'Women Empowerment',
            ...$this->filterProps($lg, $request),
            'tables'      => array_values(array_filter([
                ['id' => 'lit_by_gender', 'title' => 'साक्षरता — लिङ्गअनुसार',           'title_en' => 'Literacy by Gender',          'data' => $litByGender],
                ['id' => 'cit_by_gender', 'title' => 'नागरिकता — लिङ्गअनुसार (१६+)',     'title_en' => 'Citizenship by Gender (16+)', 'data' => $citizenshipByGender],
            ], fn($t) => !empty($t['data']['grand_total']))),
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 16. Digital & Connectivity
    // ─────────────────────────────────────────────────────────────────────────

    public function digitalConnectivity(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $uq      = fn() => HouseholdUtility::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );
        $ck      = CrossTabService::cacheKey('digital', $lg->id, $request->only(['ward_id', 'settlement_id']));

        $phoneByGender = CrossTabService::tab(
            clone $mq,
            'has_phone',
            'gender',
            ['1' => 'फोन छ', '0' => 'फोन छैन'],
            CrossTabService::genderLabels(),
            $ck . '_phone_gender'
        );

        $emailByGender = CrossTabService::tab(
            clone $mq,
            'has_email',
            'gender',
            ['1' => 'इमेल छ', '0' => 'इमेल छैन'],
            CrossTabService::genderLabels(),
            $ck . '_email_gender'
        );

        $phoneByAge = CrossTabService::tab(
            clone $mq,
            'has_phone',
            CrossTabService::ageGroupExpr(),
            ['1' => 'फोन छ', '0' => 'फोन छैन'],
            CrossTabService::ageGroupLabels(),
            $ck . '_phone_age'
        );

        $internetFreq = CrossTabService::freq($uq(), 'internet_type', CrossTabService::optionLabels('internet_type'), $ck . '_internet');

        $total    = (clone $mq)->count();
        $boolFreq = function (string $col, string $yes, string $no) use ($mq, $total): array {
            $yesCount = (clone $mq)->where($col, true)->count();
            $noCount  = $total - $yesCount;
            return array_values(array_filter([
                $yesCount ? ['key' => 'yes', 'label' => $yes, 'count' => $yesCount] : null,
                $noCount  ? ['key' => 'no',  'label' => $no,  'count' => $noCount]  : null,
            ]));
        };

        $phoneFreq = $boolFreq('has_phone', 'फोन/मोबाइल छ', 'फोन/मोबाइल छैन');
        $emailFreq = $boolFreq('has_email', 'इमेल खाता छ',   'इमेल खाता छैन');

        $frequencies = array_values(array_filter([
            ['id' => 'phone',    'title' => 'फोन/मोबाइल स्वामित्व',         'title_en' => 'Phone Ownership',          'data' => $phoneFreq],
            ['id' => 'email',    'title' => 'इमेल खाता स्थिति',              'title_en' => 'Email Account',            'data' => $emailFreq],
            ['id' => 'internet', 'title' => 'इन्टरनेट सेवा (परिवारस्तर)',   'title_en' => 'Internet Type (Household)', 'data' => $internetFreq],
        ], fn($f) => !empty($f['data'])));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'digital-connectivity',
            'title'       => 'डिजिटल तथा सञ्चार पहुँच',
            'title_en'    => 'Digital & Connectivity',
            ...$this->filterProps($lg, $request),
            'tables'      => array_values(array_filter([
                ['id' => 'phone_gender', 'title' => 'फोन — लिङ्गअनुसार',      'title_en' => 'Phone by Gender',    'data' => $phoneByGender],
                ['id' => 'email_gender', 'title' => 'इमेल — लिङ्गअनुसार',     'title_en' => 'Email by Gender',    'data' => $emailByGender],
                ['id' => 'phone_age',    'title' => 'फोन — उमेर समूहअनुसार',   'title_en' => 'Phone by Age Group', 'data' => $phoneByAge],
            ], fn($t) => !empty($t['data']['grand_total']))),
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 17. Social Security & Economic Access
    // ─────────────────────────────────────────────────────────────────────────

    public function socialSecurity(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $eq      = fn() => HouseholdEconomic::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );
        $ck      = CrossTabService::cacheKey('social_sec', $lg->id, $request->only(['ward_id', 'settlement_id']));

        // ss_allowance is boolean 0/1
        $ssTotalCount  = (clone $mq)->count();
        $ssRecipients  = (clone $mq)->where('ss_allowance', true)->count();
        $ssNonRecip    = $ssTotalCount - $ssRecipients;
        $ssFreq = array_values(array_filter([
            $ssRecipients ? ['key' => 'yes', 'label' => 'सामाजिक सुरक्षा भत्ता पाउँछ', 'count' => $ssRecipients] : null,
            $ssNonRecip   ? ['key' => 'no',  'label' => 'भत्ता पाउँदैनन्',               'count' => $ssNonRecip]   : null,
        ]));

        $ssByGender = CrossTabService::tab(
            clone $mq,
            'ss_allowance',
            'gender',
            ['1' => 'सामाजिक सुरक्षा भत्ता छ', '0' => 'भत्ता छैन'],
            CrossTabService::genderLabels(),
            $ck . '_ss_gender'
        );

        $eqTotal  = $eq()->count();
        $boolFreq = function (string $col, string $yes, string $no) use ($eq, $eqTotal): array {
            $yesCount = (clone $eq())->where($col, true)->count();
            $noCount  = $eqTotal - $yesCount;
            return array_values(array_filter([
                $yesCount ? ['key' => 'yes', 'label' => $yes, 'count' => $yesCount] : null,
                $noCount  ? ['key' => 'no',  'label' => $no,  'count' => $noCount]  : null,
            ]));
        };

        $bankFreq    = $boolFreq('has_bank_account', 'बैंक खाता छ',         'बैंक खाता छैन');
        $povertyFreq = $boolFreq('poverty_card',     'लक्षित वर्ग कार्ड छ', 'कार्ड छैन');

        $insuranceFreq = CrossTabService::freq($eq(), 'has_insurance', CrossTabService::optionLabels('has_insurance'), $ck . '_insurance');

        $disabilityCardFreq = CrossTabService::freq(
            clone($mq)->where('disability_status', true)->whereNotNull('disability_card')->where('disability_card', '!=', ''),
            'disability_card',
            CrossTabService::optionLabels('disability_card'),
            $ck . '_dis_card'
        );

        $wards = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $bankByWard = CrossTabService::tab(
            HouseholdEconomic::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_economic.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'has_bank_account',
            'wards.ward_number',
            ['1' => 'बैंक खाता छ', '0' => 'बैंक खाता छैन'],
            $wardColDef,
            $ck . '_bank_ward'
        );

        $frequencies = array_values(array_filter([
            ['id' => 'ss_allowance',    'title' => 'सामाजिक सुरक्षा भत्ताका प्रकार', 'title_en' => 'Social Security Allowance Types', 'data' => $ssFreq],
            ['id' => 'bank_account',    'title' => 'बैंक खाता स्थिति',                'title_en' => 'Bank Account Coverage',           'data' => $bankFreq],
            ['id' => 'poverty_card',    'title' => 'लक्षित वर्ग परिचयपत्र',            'title_en' => 'Poverty / Target Card',           'data' => $povertyFreq],
            ['id' => 'insurance',       'title' => 'बीमा स्थिति',                      'title_en' => 'Insurance Status',                'data' => $insuranceFreq],
            ['id' => 'disability_card', 'title' => 'अपाङ्गता परिचयपत्र',               'title_en' => 'Disability Card (by Color)',      'data' => $disabilityCardFreq],
        ], fn($f) => !empty($f['data'])));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'social-security',
            'title'       => 'सामाजिक सुरक्षा तथा आर्थिक पहुँच',
            'title_en'    => 'Social Security & Economic Access',
            ...$this->filterProps($lg, $request),
            'tables'      => array_values(array_filter([
                ['id' => 'ss_gender', 'title' => 'सामाजिक सुरक्षा — लिङ्गअनुसार', 'title_en' => 'Social Security by Gender', 'data' => $ssByGender],
                ['id' => 'bank_ward', 'title' => 'बैंक खाता — वडाअनुसार',           'title_en' => 'Bank Account by Ward',      'data' => $bankByWard],
            ], fn($t) => !empty($t['data']['grand_total']))),
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 18. Livestock Detail
    // ─────────────────────────────────────────────────────────────────────────

    public function livestockDetail(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('livestock_det', $lg->id, $request->only(['ward_id']));

        $lq = fn() => HouseholdLivestock::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $aq = fn() => HouseholdAgriculture::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $livestockFreq = CrossTabService::freq($lq(), 'livestock_type', CrossTabService::optionLabels('livestock_kind'), $ck . '_type');
        $agriLoanFreq  = CrossTabService::freq($aq(), 'has_agri_loan',  CrossTabService::optionLabels('has_agri_loan'),  $ck . '_agri_loan');
        $farmChallFreq = $this->jsonArrayFreq($aq(), 'Farm_Challenges', CrossTabService::optionLabels('farm_challenge'),  $ck . '_farm_ch');

        $wards = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $livestockByWard = CrossTabService::tab(
            HouseholdLivestock::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_livestock.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'livestock_type',
            'wards.ward_number',
            CrossTabService::optionLabels('livestock_kind'),
            $wardColDef,
            $ck . '_type_ward'
        );

        $frequencies = array_values(array_filter([
            ['id' => 'livestock_type', 'title' => 'पशुपालनको प्रकार',    'title_en' => 'Livestock Type',       'data' => $livestockFreq],
            ['id' => 'agri_loan',      'title' => 'कृषि ऋण',              'title_en' => 'Agricultural Loan',    'data' => $agriLoanFreq],
            ['id' => 'farm_challenge', 'title' => 'कृषि चुनौतीहरू',       'title_en' => 'Farming Challenges',   'data' => $farmChallFreq],
        ], fn($f) => !empty($f['data'])));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'livestock-detail',
            'title'       => 'पशुधन विवरण',
            'title_en'    => 'Livestock Detail',
            ...$this->filterProps($lg, $request),
            'tables'      => array_values(array_filter([
                ['id' => 'livestock_ward', 'title' => 'पशुपालन — वडाअनुसार', 'title_en' => 'Livestock by Ward', 'data' => $livestockByWard],
            ], fn($t) => !empty($t['data']['grand_total']))),
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 19. Land & Agricultural Income
    // ─────────────────────────────────────────────────────────────────────────

    public function landAgriculture(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('land_agri', $lg->id, $request->only(['ward_id']));

        $aq = fn() => HouseholdAgriculture::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );
        $eq = fn() => HouseholdEconomic::whereHas('household', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $ownsLandFreq   = CrossTabService::freq($aq(), 'owns_land',            CrossTabService::optionLabels('owns_land'),         $ck . '_land');
        $irrigationFreq = CrossTabService::freq($aq(), 'irrigation_source',    CrossTabService::optionLabels('irrigation_source'), $ck . '_irr');
        $sellsFreq      = CrossTabService::freq($aq(), 'sells_produce',        CrossTabService::optionLabels('sells_produce'),     $ck . '_sells');
        $agriCatFreq    = $this->jsonArrayFreq($aq(), 'agri_categories',       CrossTabService::optionLabels('agri_category'),     $ck . '_agri_cat');
        $incomeFreq     = CrossTabService::freq($eq(), 'primary_income_source', CrossTabService::optionLabels('income_source'),    $ck . '_income');

        $wards = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $landByWard = CrossTabService::tab(
            HouseholdAgriculture::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
            )->join('households', 'household_agriculture.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'owns_land',
            'wards.ward_number',
            CrossTabService::optionLabels('owns_land'),
            $wardColDef,
            $ck . '_land_ward'
        );

        $frequencies = array_values(array_filter([
            ['id' => 'owns_land',     'title' => 'जमिन स्वामित्व',      'title_en' => 'Land Ownership',        'data' => $ownsLandFreq],
            ['id' => 'irrigation',    'title' => 'सिंचाइ स्रोत',         'title_en' => 'Irrigation Source',     'data' => $irrigationFreq],
            ['id' => 'sells_produce', 'title' => 'उपज बिक्री',           'title_en' => 'Produce Sales',         'data' => $sellsFreq],
            ['id' => 'agri_category', 'title' => 'बाली/कृषि श्रेणी',     'title_en' => 'Crop / Agri Category',  'data' => $agriCatFreq],
            ['id' => 'income_source', 'title' => 'प्राथमिक आय स्रोत',    'title_en' => 'Primary Income Source', 'data' => $incomeFreq],
        ], fn($f) => !empty($f['data'])));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'land-agriculture',
            'title'       => 'भूमि तथा कृषि आय',
            'title_en'    => 'Land & Agricultural Income',
            ...$this->filterProps($lg, $request),
            'tables'      => array_values(array_filter([
                ['id' => 'land_ward', 'title' => 'जमिन स्वामित्व — वडाअनुसार', 'title_en' => 'Land Ownership by Ward', 'data' => $landByWard],
            ], fn($t) => !empty($t['data']['grand_total']))),
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 20. Development Index (Ward-level comparison)
    // ─────────────────────────────────────────────────────────────────────────

    public function developmentIndex(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $allWardIds = $this->wardIds($lg, null);
        $ck         = CrossTabService::cacheKey('dev_index', $lg->id, []);

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $litExpr = "CASE WHEN LOWER(education_level) NOT IN ('illitrate','illiterate') AND education_level IS NOT NULL AND education_level != '' THEN 'साक्षर' ELSE 'निरक्षर' END";

        $litByWard = CrossTabService::tab(
            HouseholdMember::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $allWardIds)->where('status', 'published')
            )->whereNotNull('education_level')->where('education_level', '!=', '')
             ->join('households', 'household_members.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            $litExpr,
            'wards.ward_number',
            ['साक्षर' => 'साक्षर', 'निरक्षर' => 'निरक्षर'],
            $wardColDef,
            $ck . '_lit_ward'
        );

        $empByWard = CrossTabService::tab(
            HouseholdMember::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $allWardIds)->where('status', 'published')
            )->whereNotNull('employment_status')->where('employment_status', '!=', '')
             ->join('households', 'household_members.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'employment_status',
            'wards.ward_number',
            CrossTabService::optionLabels('employment_status'),
            $wardColDef,
            $ck . '_emp_ward'
        );

        $bankByWard = CrossTabService::tab(
            HouseholdEconomic::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $allWardIds)->where('status', 'published')
            )->join('households', 'household_economic.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'has_bank_account',
            'wards.ward_number',
            ['1' => 'बैंक खाता छ', '0' => 'बैंक खाता छैन'],
            $wardColDef,
            $ck . '_bank_ward'
        );

        $toiletByWard = CrossTabService::tab(
            HouseholdUtility::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $allWardIds)->where('status', 'published')
            )->join('households', 'household_utilities.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'toilet_type',
            'wards.ward_number',
            CrossTabService::optionLabels('toilet_type'),
            $wardColDef,
            $ck . '_toilet_ward'
        );

        $electricByWard = CrossTabService::tab(
            HouseholdUtility::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $allWardIds)->where('status', 'published')
            )->join('households', 'household_utilities.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'electricity_source',
            'wards.ward_number',
            CrossTabService::optionLabels('electricity_source'),
            $wardColDef,
            $ck . '_elec_ward'
        );

        $waterByWard = CrossTabService::tab(
            HouseholdUtility::whereHas('household', fn($q) =>
                $q->whereIn('ward_id', $allWardIds)->where('status', 'published')
            )->join('households', 'household_utilities.household_id', '=', 'households.id')
             ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'water_source',
            'wards.ward_number',
            CrossTabService::optionLabels('water_source'),
            $wardColDef,
            $ck . '_water_ward'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'development-index',
            'title'       => 'विकास सूचक — वडागत तुलना',
            'title_en'    => 'Development Index — Ward Comparison',
            ...$this->filterProps($lg, $request),
            'tables'      => array_values(array_filter([
                ['id' => 'lit_ward',    'title' => 'साक्षरता — वडाअनुसार',       'title_en' => 'Literacy by Ward',       'data' => $litByWard],
                ['id' => 'emp_ward',    'title' => 'रोजगारी — वडाअनुसार',        'title_en' => 'Employment by Ward',     'data' => $empByWard],
                ['id' => 'bank_ward',   'title' => 'बैंक खाता — वडाअनुसार',      'title_en' => 'Bank Account by Ward',   'data' => $bankByWard],
                ['id' => 'toilet_ward', 'title' => 'शौचालय — वडाअनुसार',         'title_en' => 'Toilet Type by Ward',    'data' => $toiletByWard],
                ['id' => 'elec_ward',   'title' => 'विद्युत स्रोत — वडाअनुसार',  'title_en' => 'Electricity by Ward',    'data' => $electricByWard],
                ['id' => 'water_ward',  'title' => 'पानी स्रोत — वडाअनुसार',     'title_en' => 'Water Source by Ward',   'data' => $waterByWard],
            ], fn($t) => !empty($t['data']['grand_total']))),
            'frequencies' => [],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. Crop Production
    // ─────────────────────────────────────────────────────────────────────────

    public function cropProduction(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('crop_prod', $lg->id, $request->only(['ward_id']));

        $categories = [
            'food_crops'  => 'अन्न बाली',
            'pulses'      => 'दलहन बाली',
            'oil_seeds'   => 'तेलहन बाली',
            'vegetables'  => 'तरकारी बाली',
            'cash_crops'  => 'नगदे बाली',
            'fruits'      => 'फलफूल',
            'other_crops' => 'अन्य बाली',
        ];

        $categoryFreq = \Illuminate\Support\Facades\Cache::remember($ck . '_cat_freq', 3600, function () use ($wardIds, $categories) {
            $rows = Household::whereIn('ward_id', $wardIds)
                ->where('status', 'published')
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.cropProduction')) as cp_json")
                ->pluck('cp_json');

            $counts = array_fill_keys(array_keys($categories), 0);

            foreach ($rows as $cpRaw) {
                $cp = $cpRaw ? json_decode($cpRaw, true) : null;
                if (!is_array($cp)) continue;
                foreach (array_keys($categories) as $catKey) {
                    $catRows = $cp[$catKey] ?? [];
                    if (!is_array($catRows)) continue;
                    foreach ($catRows as $row) {
                        if (!empty($row['crop']) || !empty($row['land_area'])) {
                            $counts[$catKey]++;
                            break;
                        }
                    }
                }
            }

            $result = [];
            foreach ($categories as $catKey => $catLabel) {
                if ($counts[$catKey] > 0) {
                    $result[] = ['key' => $catKey, 'label' => $catLabel, 'count' => $counts[$catKey]];
                }
            }
            usort($result, fn($a, $b) => $b['count'] - $a['count']);
            return $result;
        });

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'crop-production',
            'title'       => 'बाली उत्पादन विवरण',
            'title_en'    => 'Crop Production',
            ...$this->filterProps($lg, $request),
            'tables'      => [],
            'frequencies' => [
                ['id' => 'crop_cat', 'title' => 'बाली उत्पादन गर्ने घरधुरी (श्रेणी अनुसार)', 'title_en' => 'Households by Crop Category', 'data' => $categoryFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 12. Health Awareness
    // ─────────────────────────────────────────────────────────────────────────

    public function healthAwareness(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('health_aw', $lg->id, $request->only(['ward_id']));

        $yn = ['yes' => 'छ', 'no' => 'छैन'];

        $treatmentFreq = $this->surveyDataFreq($wardIds, '$.health.treatment_place', [
            'govt_hospital'    => 'सरकारी अस्पताल',
            'hospital'         => 'अस्पताल (निजी तथा सरकारी)',
            'health_post'      => 'स्वास्थ्य चौकी / प्रा.स्वा.के.',
            'community_health' => 'सामुदायिक स्वास्थ्य कक्ष',
            'private_clinic'   => 'निजी क्लिनिक / नर्सिङ होम',
            'pharmacy'         => 'दवाई पसल / फार्मेसी',
            'traditional'      => 'वैद्य / झारफुक',
            'home_remedy'      => 'घरेलु उपचार',
            'other'            => 'अन्य',
        ], $ck . '_treatment');

        $illnessFreq = $this->surveyDataFreq($wardIds, '$.health.illness_frequency', [
            'rarely' => 'विरलै (वर्षमा १ पटक भन्दा कम)',
            '1_2'    => '१–२ पटक',
            '3_5'    => '३–५ पटक',
            '6_plus' => '६ पटक वा बढी',
        ], $ck . '_illness');

        $handwashFreq   = $this->surveyDataFreq($wardIds, '$.health.aware_handwashing',        $yn, $ck . '_handwash');
        $nutritionFreq  = $this->surveyDataFreq($wardIds, '$.health.aware_nutrition',           $yn, $ck . '_nutrition');
        $pregNutrFreq   = $this->surveyDataFreq($wardIds, '$.health.aware_pregnant_nutrition',  $yn, $ck . '_preg_nutr');
        $ironFreq       = $this->surveyDataFreq($wardIds, '$.health.aware_iron_capsule',        $yn, $ck . '_iron');
        $insuranceFreq  = $this->surveyDataFreq($wardIds, '$.health.has_health_insurance',      $yn + ['dont_know' => 'थाहा छैन'], $ck . '_insurance');
        $pregnantFreq   = $this->surveyDataFreq($wardIds, '$.health.has_pregnant_woman',        $yn, $ck . '_pregnant');

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'health-awareness',
            'title'       => 'स्वास्थ्य चेतना तथा सेवा',
            'title_en'    => 'Health Awareness & Services',
            ...$this->filterProps($lg, $request),
            'tables'      => [],
            'frequencies' => [
                ['id' => 'treatment',   'title' => 'उपचार स्थान',                    'title_en' => 'Treatment Place',             'data' => $treatmentFreq],
                ['id' => 'illness',     'title' => 'वार्षिक बिमारी दर',              'title_en' => 'Annual Illness Frequency',    'data' => $illnessFreq],
                ['id' => 'handwash',    'title' => 'हात धुने चेतना',                 'title_en' => 'Handwashing Awareness',       'data' => $handwashFreq],
                ['id' => 'nutrition',   'title' => 'पोषण चेतना',                     'title_en' => 'Nutrition Awareness',         'data' => $nutritionFreq],
                ['id' => 'preg_nutr',   'title' => 'गर्भवती पोषण चेतना',             'title_en' => 'Pregnant Woman Nutrition',    'data' => $pregNutrFreq],
                ['id' => 'iron',        'title' => 'आइरन क्याप्सुल चेतना',           'title_en' => 'Iron Capsule Awareness',      'data' => $ironFreq],
                ['id' => 'insurance',   'title' => 'स्वास्थ्य बीमा',                 'title_en' => 'Health Insurance',            'data' => $insuranceFreq],
                ['id' => 'pregnant',    'title' => 'हाल गर्भवती महिला भएका घरधुरी', 'title_en' => 'Households with Pregnant Woman','data'=> $pregnantFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 13. Wellbeing
    // ─────────────────────────────────────────────────────────────────────────

    public function wellbeing(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('wellbeing', $lg->id, $request->only(['ward_id']));

        $yn = ['yes' => 'खुसी छौं', 'no' => 'खुसी छैनौं'];

        $happyFreq = $this->surveyDataFreq($wardIds, '$.wellbeing.happy_with_location', $yn, $ck . '_happy');

        $perceptionFreq = $this->surveyDataFreq($wardIds, '$.wellbeing.residence_perception', [
            'very_good' => 'धेरै राम्रो',
            'good'      => 'राम्रो',
            'normal'    => 'सामान्य',
            'not_good'  => 'राम्रो छैन',
        ], $ck . '_perception');

        $gatherFreq = $this->surveyDataFreq($wardIds, '$.wellbeing.family_gathering_freq', [
            'once'        => 'एक पटक',
            'twice'       => 'दुई पटक',
            'three_times' => 'तीन पटक',
            'four_plus'   => 'चार पटक भन्दा बढी',
            'never'       => 'बैठक गर्ने अवसर छैन',
        ], $ck . '_gather');

        $neighborsFreq = $this->surveyDataFreq($wardIds, '$.wellbeing.neighbors_nice', [
            'yes'     => 'हुन्',
            'no'      => 'होइनन्',
            'neutral' => 'त्यस्तो केही लाग्दैन',
        ], $ck . '_neighbors');

        $cooperationFreq = $this->surveyDataFreq($wardIds, '$.wellbeing.neighbor_cooperation', [
            'often'       => 'प्रायः गर्छन्',
            'sometimes'   => 'कहिलेकाहीं गर्छन्',
            'when_needed' => 'आवश्यक पर्दा मात्र',
            'never'       => 'कहिले पनि गर्दैनन्',
        ], $ck . '_cooperation');

        $activitiesFreq = \Illuminate\Support\Facades\Cache::remember($ck . '_activities', 3600, function () use ($wardIds) {
            $actLabels = [
                'mobile_tv' => 'मोबाइल तथा टेलिभिजन हेर्ने',
                'outing'    => 'बाहिर भ्रमण गर्ने',
                'festivals' => 'चाडपर्व मनाउने',
                'yoga'      => 'योग-व्यायाम गर्ने',
                'nothing'   => 'केही गर्ने अवसर छैन',
            ];
            $raw = DB::table('households')
                ->whereIn('ward_id', $wardIds)->where('status', 'published')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.wellbeing.happiness_activities')) IS NOT NULL")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.wellbeing.happiness_activities')) NOT IN ('null', '', '[]')")
                ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.wellbeing.happiness_activities'))"));
            $counts = [];
            foreach ($raw as $json) {
                $vals = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
                if (!is_array($vals)) continue;
                foreach ($vals as $v) {
                    $v = trim((string)$v);
                    if ($v !== '') $counts[$v] = ($counts[$v] ?? 0) + 1;
                }
            }
            arsort($counts);
            return array_map(fn($v, $c) => ['key' => $v, 'label' => $actLabels[$v] ?? $v, 'count' => $c], array_keys($counts), $counts);
        });

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'wellbeing',
            'title'       => 'जीवन सन्तुष्टि तथा सामाजिक सम्बन्ध',
            'title_en'    => 'Wellbeing & Social Relations',
            ...$this->filterProps($lg, $request),
            'tables'      => [],
            'frequencies' => [
                ['id' => 'happy',       'title' => 'बसोबास स्थानसँग खुसी',      'title_en' => 'Happy with Location',           'data' => $happyFreq],
                ['id' => 'perception',  'title' => 'बसोबास प्रति दृष्टिकोण',    'title_en' => 'Residence Perception',          'data' => $perceptionFreq],
                ['id' => 'gather',      'title' => 'पारिवारिक भेटघाट (मासिक)',   'title_en' => 'Family Gathering Frequency',    'data' => $gatherFreq],
                ['id' => 'activities',  'title' => 'खुसीका क्रियाकलाप',          'title_en' => 'Happiness Activities',          'data' => $activitiesFreq],
                ['id' => 'neighbors',   'title' => 'छिमेकी सम्बन्ध',             'title_en' => 'Neighbour Relations',           'data' => $neighborsFreq],
                ['id' => 'cooperation', 'title' => 'छिमेकी सहकार्य',             'title_en' => 'Neighbour Cooperation',         'data' => $cooperationFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 14. Income Breakdown
    // ─────────────────────────────────────────────────────────────────────────

    public function incomeBreakdown(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('income_bk', $lg->id, $request->only(['ward_id']));

        $eq = fn () => HouseholdEconomic::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $incomeRangeFreq = CrossTabService::freq($eq(), 'annual_income_range',      CrossTabService::optionLabels('annual_income_range'),      $ck . '_range');
        $expendFreq      = CrossTabService::freq($eq(), 'annual_expenditure_range', CrossTabService::optionLabels('annual_expenditure_range'), $ck . '_expend');
        $srcFreq         = CrossTabService::freq($eq(), 'primary_income_source',    CrossTabService::optionLabels('primary_income_source'),    $ck . '_src');

        $sums = \Illuminate\Support\Facades\Cache::remember($ck . '_sums', 3600, function () use ($eq) {
            return $eq()->selectRaw('
                SUM(COALESCE(agri_livestock_income, 0))       as agri,
                SUM(COALESCE(business_industry_income, 0))    as business,
                SUM(COALESCE(employment_service_income, 0))   as employment,
                SUM(COALESCE(abroad_job_income, 0))           as abroad,
                SUM(COALESCE(food_expense, 0))                as food,
                SUM(COALESCE(clothes_expense, 0))             as clothes,
                SUM(COALESCE(education_expense, 0))           as education,
                SUM(COALESCE(treatment_expense, 0))           as treatment,
                SUM(COALESCE(other_expense, 0))               as other_exp,
                SUM(COALESCE(remittance_amount, 0))           as remittance,
                COUNT(*)                                       as total
            ')->first();
        });

        $incomeAmounts = array_filter([
            ['key' => 'agri_livestock',  'label' => 'कृषि / पशुपालन',   'count' => (int)($sums->agri ?? 0)],
            ['key' => 'business',        'label' => 'व्यापार / उद्योग', 'count' => (int)($sums->business ?? 0)],
            ['key' => 'employment',      'label' => 'नोकरी / सेवा',     'count' => (int)($sums->employment ?? 0)],
            ['key' => 'abroad_job',      'label' => 'वैदेशिक रोजगार',   'count' => (int)($sums->abroad ?? 0)],
        ], fn($r) => $r['count'] > 0);

        $expenseAmounts = array_filter([
            ['key' => 'food',       'label' => 'खाद्य सामग्री',    'count' => (int)($sums->food ?? 0)],
            ['key' => 'education',  'label' => 'शिक्षा',           'count' => (int)($sums->education ?? 0)],
            ['key' => 'treatment',  'label' => 'स्वास्थ्य उपचार',  'count' => (int)($sums->treatment ?? 0)],
            ['key' => 'clothes',    'label' => 'लुगाफाटा',         'count' => (int)($sums->clothes ?? 0)],
            ['key' => 'other_exp',  'label' => 'अन्य खर्च',        'count' => (int)($sums->other_exp ?? 0)],
        ], fn($r) => $r['count'] > 0);

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'income-breakdown',
            'title'       => 'आय तथा खर्च विवरण',
            'title_en'    => 'Income & Expenditure Breakdown',
            ...$this->filterProps($lg, $request),
            'tables'      => [],
            'frequencies' => array_values(array_filter([
                ['id' => 'income_src',    'title' => 'मुख्य आय स्रोत',              'title_en' => 'Primary Income Source',       'data' => $srcFreq],
                ['id' => 'income_range',  'title' => 'वार्षिक आय दायरा',            'title_en' => 'Annual Income Range',         'data' => $incomeRangeFreq],
                ['id' => 'expend_range',  'title' => 'वार्षिक खर्च दायरा',          'title_en' => 'Annual Expenditure Range',    'data' => $expendFreq],
                count($incomeAmounts) ? ['id' => 'income_amounts',  'title' => 'आय स्रोत अनुसार कुल रकम (रु)', 'title_en' => 'Total Income by Source (NPR)', 'data' => array_values($incomeAmounts)] : null,
                count($expenseAmounts) ? ['id' => 'expense_amounts', 'title' => 'खर्च प्रकार अनुसार कुल रकम (रु)', 'title_en' => 'Total Expense by Category (NPR)', 'data' => array_values($expenseAmounts)] : null,
            ])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 15. Social Empowerment (Decision-Making + Org Participation)
    // ─────────────────────────────────────────────────────────────────────────

    public function socialEmpowerment(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('soc_emp', $lg->id, $request->only(['ward_id']));

        $ownerLabels = [
            'male'   => 'पुरुष',   'Male'   => 'पुरुष',
            'female' => 'महिला',   'Female' => 'महिला',
            'both'   => 'दुबै',    'Both'   => 'दुबै',
            'none'   => 'कोही छैन','None'   => 'कोही छैन',
            'other'  => 'अन्य',
        ];

        $decisionKeys = [
            'decisioninHHWork'     => ['path' => '$.decisions.decisioninHHWork',     'label' => 'घरेलु काम',         'id' => 'dec_hhwork'],
            'decisioninProperty'   => ['path' => '$.decisions.decisioninProperty',   'label' => 'सम्पत्ति',          'id' => 'dec_property'],
            'decisioninBusiness'   => ['path' => '$.decisions.decisioninBusiness',   'label' => 'व्यवसाय',           'id' => 'dec_business'],
            'decisioninCCommittee' => ['path' => '$.decisions.decisioninCCommittee', 'label' => 'उपभोक्ता समिति',   'id' => 'dec_committee'],
            'decisioninSMC'        => ['path' => '$.decisions.decisioninSMC',        'label' => 'विद्यालय समिति',    'id' => 'dec_smc'],
            'bank_account_owner'   => ['path' => '$.decisions.bank_account_owner',   'label' => 'बैंक खाता धारक',   'id' => 'dec_bank'],
        ];

        $frequencies = [];
        foreach ($decisionKeys as $key => $meta) {
            $data = $this->surveyDataFreqRaw($wardIds, $meta['path'], $ownerLabels, $ck . '_' . $key);
            if (!empty($data)) {
                $frequencies[] = ['id' => $meta['id'], 'title' => $meta['label'] . ' — निर्णयकर्ता', 'title_en' => 'Decision Maker: ' . $meta['label'], 'data' => $data];
            }
        }

        $yn = ['yes' => 'सहभागी छ', 'no' => 'सहभागी छैन'];
        $orgFreq  = $this->surveyDataFreq($wardIds, '$.decisions.org_participation', $yn, $ck . '_org');
        $orgTypes = $this->surveyDataJsonArrayFreq($wardIds, '$.decisions.org_participation_types', [
            'WomenGroup'     => 'महिला समूह',
            'YouthClub'      => 'युवा क्लब',
            'Cooperative'    => 'सहकारी',
            'PoliticalParty' => 'राजनीतिक दल',
            'MotherGroup'    => 'आमा समूह',
            'FarmerGroup'    => 'कृषक समूह',
            'NGO'            => 'गैसस',
            'Other'          => 'अन्य',
        ], $ck . '_org_types');

        if (!empty($orgFreq))  $frequencies[] = ['id' => 'org_part',  'title' => 'संगठन सहभागिता',          'title_en' => 'Organisation Participation',  'data' => $orgFreq];
        if (!empty($orgTypes)) $frequencies[] = ['id' => 'org_types', 'title' => 'संगठनका प्रकार',          'title_en' => 'Organisation Types',          'data' => $orgTypes];

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'social-empowerment',
            'title'       => 'सामाजिक सशक्तीकरण तथा निर्णय',
            'title_en'    => 'Social Empowerment & Decision-Making',
            ...$this->filterProps($lg, $request),
            'tables'      => [],
            'frequencies' => $frequencies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 16. Family Profile (Family Type, Settlement, Local Migration)
    // ─────────────────────────────────────────────────────────────────────────

    public function familyProfile(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('fam_prof', $lg->id, $request->only(['ward_id']));

        // family_type: normalize duplicates (single=nuclear)
        $familyTypeFreq = \Illuminate\Support\Facades\Cache::remember($ck . '_fam_type', 3600, function () use ($wardIds) {
            $rows = DB::table('households')
                ->whereIn('ward_id', $wardIds)->where('status', 'published')
                ->selectRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_type'))) as val, COUNT(*) as cnt")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_type')) IS NOT NULL")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_type')) NOT IN ('null', '')")
                ->groupBy(DB::raw('val'))->orderByDesc('cnt')->get();
            $labels = ['single' => 'एकल (Nuclear)', 'nuclear' => 'एकल (Nuclear)', 'joint' => 'संयुक्त (Joint)'];
            $merged = [];
            foreach ($rows as $r) {
                $label = $labels[$r->val] ?? $r->val;
                $merged[$label] = ($merged[$label] ?? 0) + $r->cnt;
            }
            arsort($merged);
            return array_map(fn($l, $c) => ['key' => $l, 'label' => $l, 'count' => $c], array_keys($merged), $merged);
        });

        // settlement_type: normalize duplicates (Permanent_resident=permanent, Temporary_resident=temporary)
        $settlementFreq = \Illuminate\Support\Facades\Cache::remember($ck . '_settlement', 3600, function () use ($wardIds) {
            $rows = DB::table('households')
                ->whereIn('ward_id', $wardIds)->where('status', 'published')
                ->selectRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.settlement_type'))) as val, COUNT(*) as cnt")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.settlement_type')) IS NOT NULL")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.settlement_type')) NOT IN ('null', '')")
                ->groupBy(DB::raw('val'))->orderByDesc('cnt')->get();
            $normalise = [
                'permanent_resident' => 'स्थायी',
                'permanent'          => 'स्थायी',
                'temporary_resident' => 'अस्थायी',
                'temporary'          => 'अस्थायी',
                'squatter'           => 'सुकुम्बासी',
            ];
            $merged = [];
            foreach ($rows as $r) {
                $label = $normalise[$r->val] ?? $r->val;
                $merged[$label] = ($merged[$label] ?? 0) + $r->cnt;
            }
            arsort($merged);
            return array_map(fn($l, $c) => ['key' => $l, 'label' => $l, 'count' => $c], array_keys($merged), $merged);
        });

        $yn = ['yes' => 'छ', 'no' => 'छैन'];
        $migrationFreq = $this->surveyDataFreq($wardIds, '$.social.migration_last_5years', $yn,       $ck . '_migr5');
        $migPurpFreq   = $this->surveyDataFreq($wardIds, '$.social.migration_purpose', [
            'employment' => 'रोजगार',
            'education'  => 'शिक्षा',
            'marriage'   => 'विवाह',
            'disaster'   => 'प्रकोप',
            'health'     => 'स्वास्थ्य',
            'other'      => 'अन्य',
        ], $ck . '_migr_purpose');
        $migProvFreq   = $this->surveyDataFreq($wardIds, '$.social.migration_from_province', [], $ck . '_migr_prov');

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        // Family type × Ward cross-tab (DB-side)
        $familyByWard = \Illuminate\Support\Facades\Cache::remember($ck . '_fam_ward', 3600, function () use ($wardIds, $wardColDef) {
            $normalise = [
                'single'  => 'एकल', 'nuclear'             => 'एकल',
                'joint'   => 'संयुक्त',
                'permanent_resident' => 'स्थायी', 'permanent' => 'स्थायी',
            ];
            $rawRows = DB::table('households')
                ->whereIn('households.ward_id', $wardIds)->where('status', 'published')
                ->join('wards', 'households.ward_id', '=', 'wards.id')
                ->selectRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_type'))) as ftype, wards.ward_number, COUNT(*) as cnt")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_type')) IS NOT NULL")
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.social.family_type')) NOT IN ('null', '')")
                ->groupBy('ftype', 'wards.ward_number')->get();

            $rowDef = ['एकल (Nuclear)' => 'एकल (Nuclear)', 'संयुक्त (Joint)' => 'संयुक्त (Joint)'];
            $matrix = []; $rowTotals = []; $colTotals = []; $grand = 0;
            foreach ($rawRows as $r) {
                $norm = ['single'=>'एकल (Nuclear)','nuclear'=>'एकल (Nuclear)','joint'=>'संयुक्त (Joint)'][$r->ftype] ?? $r->ftype;
                $w = (string)$r->ward_number;
                $matrix[$norm][$w] = ($matrix[$norm][$w] ?? 0) + $r->cnt;
                $rowTotals[$norm] = ($rowTotals[$norm] ?? 0) + $r->cnt;
                $colTotals[$w]    = ($colTotals[$w]    ?? 0) + $r->cnt;
                $grand += $r->cnt;
            }
            return [
                'rows' => array_keys($rowDef), 'row_labels' => $rowDef,
                'cols' => array_keys($wardColDef), 'col_labels' => $wardColDef,
                'matrix' => $matrix, 'row_totals' => $rowTotals,
                'col_totals' => $colTotals, 'grand_total' => $grand,
            ];
        });

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'family-profile',
            'title'       => 'पारिवारिक प्रोफाइल तथा बसोबास',
            'title_en'    => 'Family Profile & Settlement',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                ['id' => 'fam_by_ward', 'title' => 'परिवार प्रकार — वडाअनुसार', 'title_en' => 'Family Type by Ward', 'data' => $familyByWard],
            ],
            'frequencies' => array_values(array_filter([
                !empty($familyTypeFreq) ? ['id' => 'fam_type',     'title' => 'परिवार प्रकार',                    'title_en' => 'Family Type',              'data' => $familyTypeFreq]  : null,
                !empty($settlementFreq) ? ['id' => 'settlement',    'title' => 'बसोबास स्थिति',                    'title_en' => 'Settlement Status',        'data' => $settlementFreq]  : null,
                !empty($migrationFreq)  ? ['id' => 'migr_5yr',      'title' => '५ वर्षमा यहाँ आएका घरधुरी',       'title_en' => 'In-Migration (5 yrs)',      'data' => $migrationFreq]   : null,
                !empty($migPurpFreq)    ? ['id' => 'migr_purpose',  'title' => 'यहाँ आउनुको कारण',                'title_en' => 'Reason for Migration',     'data' => $migPurpFreq]     : null,
                !empty($migProvFreq)    ? ['id' => 'migr_province', 'title' => 'आउनुपूर्वको प्रदेश',              'title_en' => 'Previous Province',        'data' => $migProvFreq]     : null,
            ])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 17. Agri Enterprises (Bee/Fish/Silk)
    // ─────────────────────────────────────────────────────────────────────────

    public function agriEnterprises(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('agri_ent', $lg->id, $request->only(['ward_id']));

        $yn = ['yes' => 'छ', 'no' => 'छैन'];
        $hasFreq  = $this->surveyDataFreq($wardIds, '$.beeFishSilk.has_bee_fish_silk', $yn, $ck . '_has');
        $typeFreq = $this->surveyDataJsonArrayFreq($wardIds, '$.beeFishSilk.bee_fish_silk_select', [
            'bee'  => '🐝 मौरीपालन',
            'fish' => '🐟 माछापालन',
            'silk' => '🪱 रेशम',
        ], $ck . '_types');

        $sums = \Illuminate\Support\Facades\Cache::remember($ck . '_sums', 3600, function () use ($wardIds) {
            $rows = DB::table('households')
                ->whereIn('ward_id', $wardIds)->where('status', 'published')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.has_bee_fish_silk')) = 'yes'")
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.Mudhe_Hive'))+0, 0))  as mudhe_hive,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.Modern_Hive'))+0, 0)) as modern_hive,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.Honey_Production_Mudhe_Hive'))+0, 0)) as honey_mudhe,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.Honey_Production_Modern_Hive'))+0, 0)) as honey_modern,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.honey_sales'))+0, 0)) as honey_sales,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.Pond_area'))+0, 0))  as pond_area,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.fish_prod'))+0, 0))  as fish_prod,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.fish_sales'))+0, 0)) as fish_sales,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.silk_prod'))+0, 0))  as silk_prod,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(survey_data, '$.beeFishSilk.silk_sales'))+0, 0)) as silk_sales
                ")->first();
            return $rows;
        });

        $beeAmounts = array_values(array_filter([
            ($sums->mudhe_hive  > 0) ? ['key' => 'mudhe_hive',   'label' => 'मुढे घार (संख्या)',         'count' => (int)$sums->mudhe_hive]   : null,
            ($sums->modern_hive > 0) ? ['key' => 'modern_hive',  'label' => 'आधुनिक घार (संख्या)',       'count' => (int)$sums->modern_hive]  : null,
            ($sums->honey_mudhe > 0) ? ['key' => 'honey_mudhe',  'label' => 'मुढे मह उत्पादन (के.जी.)', 'count' => (int)round($sums->honey_mudhe)]  : null,
            ($sums->honey_modern> 0) ? ['key' => 'honey_modern', 'label' => 'आधुनिक मह उत्पादन (के.जी.)','count'=> (int)round($sums->honey_modern)] : null,
            ($sums->honey_sales > 0) ? ['key' => 'honey_sales',  'label' => 'मह बिक्री (के.जी.)',        'count' => (int)round($sums->honey_sales)]  : null,
        ]));

        $fishAmounts = array_values(array_filter([
            ($sums->fish_prod  > 0) ? ['key' => 'fish_prod',  'label' => 'माछा उत्पादन (के.जी.)', 'count' => (int)round($sums->fish_prod)]  : null,
            ($sums->fish_sales > 0) ? ['key' => 'fish_sales', 'label' => 'माछा बिक्री (के.जी.)',  'count' => (int)round($sums->fish_sales)] : null,
            ($sums->pond_area  > 0) ? ['key' => 'pond_area',  'label' => 'पोखरी क्षेत्रफल (वर्गमिटर)', 'count' => (int)round($sums->pond_area)] : null,
        ]));

        $silkAmounts = array_values(array_filter([
            ($sums->silk_prod  > 0) ? ['key' => 'silk_prod',  'label' => 'रेशम उत्पादन (के.जी.)', 'count' => (int)round($sums->silk_prod)]  : null,
            ($sums->silk_sales > 0) ? ['key' => 'silk_sales', 'label' => 'रेशम बिक्री (के.जी.)',  'count' => (int)round($sums->silk_sales)] : null,
        ]));

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'agri-enterprises',
            'title'       => 'कृषि उद्यम (मौरी, माछा, रेशम)',
            'title_en'    => 'Agri Enterprises (Bee, Fish, Silk)',
            ...$this->filterProps($lg, $request),
            'tables'      => [],
            'frequencies' => array_values(array_filter([
                !empty($hasFreq)    ? ['id' => 'has_ent',     'title' => 'मौरी/माछा/रेशम गर्ने घरधुरी',  'title_en' => 'Households with Enterprises', 'data' => $hasFreq]    : null,
                !empty($typeFreq)   ? ['id' => 'ent_types',   'title' => 'उद्यमका प्रकार',                'title_en' => 'Enterprise Types',            'data' => $typeFreq]   : null,
                !empty($beeAmounts) ? ['id' => 'bee_qty',     'title' => 'मौरीपालन — कुल मात्रा',        'title_en' => 'Beekeeping Totals',           'data' => $beeAmounts] : null,
                !empty($fishAmounts)? ['id' => 'fish_qty',    'title' => 'माछापालन — कुल मात्रा',        'title_en' => 'Fish Farming Totals',         'data' => $fishAmounts]: null,
                !empty($silkAmounts)? ['id' => 'silk_qty',    'title' => 'रेशम — कुल मात्रा',            'title_en' => 'Silk Farming Totals',         'data' => $silkAmounts]: null,
            ])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: frequency from a scalar JSON path in survey_data (households)
    // ─────────────────────────────────────────────────────────────────────────

    private function surveyDataFreq(array $wardIds, string $jsonPath, array $labelMap, string $cacheKey): array
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($wardIds, $jsonPath, $labelMap) {
                $rows = DB::table('households')
                    ->whereIn('ward_id', $wardIds)
                    ->where('status', 'published')
                    ->selectRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?))) as val, COUNT(*) as cnt", [$jsonPath])
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) IS NOT NULL", [$jsonPath])
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) NOT IN ('null', '', 'false')", [$jsonPath])
                    ->groupBy(DB::raw('val'))
                    ->orderByDesc('cnt')
                    ->get();

                return $rows->map(fn($r) => [
                    'key'   => $r->val,
                    'label' => $labelMap[$r->val] ?? $r->val,
                    'count' => $r->cnt,
                ])->values()->toArray();
            });
        } catch (\Throwable) {
            return [];
        }
    }

    // Like surveyDataFreq but does NOT lowercase the value (for mixed-case labels like Male/Female)
    private function surveyDataFreqRaw(array $wardIds, string $jsonPath, array $labelMap, string $cacheKey): array
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($wardIds, $jsonPath, $labelMap) {
                $rows = DB::table('households')
                    ->whereIn('ward_id', $wardIds)
                    ->where('status', 'published')
                    ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) as val, COUNT(*) as cnt", [$jsonPath])
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) IS NOT NULL", [$jsonPath])
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) NOT IN ('null', '', 'false')", [$jsonPath])
                    ->groupBy(DB::raw('val'))
                    ->orderByDesc('cnt')
                    ->get();

                $merged = [];
                foreach ($rows as $r) {
                    $label = $labelMap[$r->val] ?? $labelMap[strtolower($r->val)] ?? $r->val;
                    $merged[$label] = ($merged[$label] ?? 0) + $r->cnt;
                }
                arsort($merged);
                return array_map(fn($l, $c) => ['key' => $l, 'label' => $l, 'count' => $c], array_keys($merged), $merged);
            });
        } catch (\Throwable) {
            return [];
        }
    }

    // JSON array field in survey_data (PHP-side expansion)
    private function surveyDataJsonArrayFreq(array $wardIds, string $jsonPath, array $labelMap, string $cacheKey): array
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($wardIds, $jsonPath, $labelMap) {
                $raw = DB::table('households')
                    ->whereIn('ward_id', $wardIds)->where('status', 'published')
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) IS NOT NULL", [$jsonPath])
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, ?)) NOT IN ('null', '', '[]')", [$jsonPath])
                    ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(survey_data, " . DB::connection()->getPdo()->quote($jsonPath) . "))"));
                $counts = [];
                foreach ($raw as $json) {
                    $vals = is_string($json) ? json_decode($json, true) : (is_array($json) ? $json : []);
                    if (!is_array($vals)) continue;
                    foreach ($vals as $v) {
                        $v = trim((string)$v);
                        if ($v !== '') $counts[$v] = ($counts[$v] ?? 0) + 1;
                    }
                }
                arsort($counts);
                return array_map(fn($v, $c) => ['key' => $v, 'label' => $labelMap[$v] ?? $v, 'count' => $c], array_keys($counts), $counts);
            });
        } catch (\Throwable) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: frequency count from a JSON array column (PHP-side expansion)
    // ─────────────────────────────────────────────────────────────────────────

    private function jsonArrayFreq($query, string $column, array $labelMap = [], string $cacheKey = ''): array
    {
        if ($cacheKey) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cached !== null) return $cached;
        }

        $rows   = $query->whereNotNull($column)->pluck($column);
        $counts = [];

        foreach ($rows as $raw) {
            $values = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : null);
            if (!is_array($values)) continue;
            foreach ($values as $val) {
                $val = trim((string)$val);
                if ($val === '') continue;
                $counts[$val] = ($counts[$val] ?? 0) + 1;
            }
        }

        arsort($counts);

        $result = [];
        foreach ($counts as $key => $count) {
            $result[] = [
                'key'   => $key,
                'label' => $labelMap[$key] ?? $key,
                'count' => $count,
            ];
        }

        if ($cacheKey) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addMinutes(60));
        }

        return $result;
    }
}
