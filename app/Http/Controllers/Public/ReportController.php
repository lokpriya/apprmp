<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdEconomic;
use App\Models\HouseholdHousing;
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
        return LocalGovernment::with(['district.province'])->where('is_active', true)->first()
            ?? LocalGovernment::with(['district.province'])->first();
    }

    private function lgProps(LocalGovernment $lg): array
    {
        return [
            'id'       => $lg->id,
            'name_en'  => $lg->name_en,
            'name_np'  => $lg->name_np,
            'logo_url' => $lg->logo && file_exists(storage_path('app/public/' . $lg->logo))
                            ? Storage::url($lg->logo)
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
        $q = HouseholdMember::whereHas('household', fn ($hq) =>
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
    // 1. Index — overview / landing
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return Inertia::render('Public/Reports/Index', ['lg' => null]);

        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('idx', $lg->id, $request->only(['ward_id', 'settlement_id']));

        $summary = [
            'total_households'   => Household::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
            'total_population'   => (clone $mq)->count(),
            'total_male'         => (clone $mq)->where('gender', 'male')->count(),
            'total_female'       => (clone $mq)->where('gender', 'female')->count(),
            'total_other'        => (clone $mq)->where('gender', 'other')->count(),
            'total_institutions' => Institution::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
            'total_wards'        => count($wardIds),
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

        return Inertia::render('Public/Reports/Index', [
            'lg'         => $this->lgProps($lg),
            'summary'    => $summary,
            'genderData' => $genderData,
            'ageData'    => $ageData,
            ...$this->filterProps($lg, $request),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Demographics — gender × age, ethnicity, religion, mother tongue
    // ─────────────────────────────────────────────────────────────────────────

    public function demographics(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('demo', $lg->id, $request->only(['ward_id','settlement_id']));

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
            ],
            'frequencies'      => [
                [
                    'id'      => 'ethnicity',
                    'title'   => 'जातीय विवरण',
                    'title_en'=> 'Ethnicity Distribution',
                    'data'    => $ethnicityFreq,
                ],
                [
                    'id'      => 'religion',
                    'title'   => 'धर्म अनुसार जनसंख्या',
                    'title_en'=> 'Religion Distribution',
                    'data'    => $religionFreq,
                ],
                [
                    'id'      => 'mother_tongue',
                    'title'   => 'मातृभाषा अनुसार जनसंख्या',
                    'title_en'=> 'Mother Tongue Distribution',
                    'data'    => $motherTongueFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Education
    // ─────────────────────────────────────────────────────────────────────────

    public function education(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('edu', $lg->id, $request->only(['ward_id','settlement_id','gender']));

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
            'is_literate',
            'gender',
            ['1' => 'साक्षर', '0' => 'निरक्षर'],
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
            'vocational_training',
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
                    'id'      => 'edu_status',
                    'title'   => 'शिक्षा अवस्था',
                    'title_en'=> 'Education Status',
                    'data'    => $eduStatusFreq,
                ],
                [
                    'id'      => 'school_type',
                    'title'   => 'विद्यालयको प्रकार',
                    'title_en'=> 'School Type',
                    'data'    => $schoolTypeFreq,
                ],
                [
                    'id'      => 'not_in_school',
                    'title'   => 'विद्यालय नजाने कारण',
                    'title_en'=> 'Reason Not in School',
                    'data'    => $notInSchoolFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Health & Disability
    // ─────────────────────────────────────────────────────────────────────────

    public function health(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('health', $lg->id, $request->only(['ward_id','settlement_id','gender']));

        $disabilityByGender = CrossTabService::tab(
            clone $mq,
            'disability_status',
            'gender',
            ['yes' => 'अपाङ्गता छ', 'no' => 'अपाङ्गता छैन'],
            CrossTabService::genderLabels(),
            $ck . '_disxgender'
        );

        $disabilityTypeFreq = CrossTabService::freq(
            clone $mq, 'disability_type', CrossTabService::optionLabels('disability_type'), $ck . '_dis_type'
        );

        $chronicByGender = CrossTabService::tab(
            clone $mq,
            'chronic_illness',
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
            'smoking_drinking',
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
            'child_labor',
            'gender',
            ['yes' => 'बाल श्रम', 'no' => 'बाल श्रम छैन'],
            CrossTabService::genderLabels(),
            $ck . '_childlaborxgender'
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
                    'id'      => 'disability_type',
                    'title'   => 'अपाङ्गताको प्रकार',
                    'title_en'=> 'Type of Disability',
                    'data'    => $disabilityTypeFreq,
                ],
                [
                    'id'      => 'ss_allowance',
                    'title'   => 'सामाजिक सुरक्षा भत्ता',
                    'title_en'=> 'Social Security Allowance',
                    'data'    => $ssAllowanceFreq,
                ],
                [
                    'id'      => 'vaccine',
                    'title'   => 'खोप स्थिति (उमेर ०–३)',
                    'title_en'=> 'Vaccination Status (age 0–3)',
                    'data'    => $vaccineFreq,
                ],
                [
                    'id'      => 'vitamin_a',
                    'title'   => 'भिटामिन A (उमेर ४–५)',
                    'title_en'=> 'Vitamin A (age 4–5)',
                    'data'    => $vitAFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Employment & Occupation
    // ─────────────────────────────────────────────────────────────────────────

    public function employment(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $mq      = $this->memberQuery($wardIds, $request);
        $ck      = CrossTabService::cacheKey('emp', $lg->id, $request->only(['ward_id','settlement_id','gender']));

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
                    'id'      => 'occupation',
                    'title'   => 'पेसाको वितरण',
                    'title_en'=> 'Occupation Distribution',
                    'data'    => $occupationFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Housing
    // ─────────────────────────────────────────────────────────────────────────

    public function housing(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('housing', $lg->id, $request->only(['ward_id']));

        // Base housing query (published HH)
        $hq = fn () => HouseholdHousing::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $roofFreq        = CrossTabService::freq($hq(), 'roof_type',       CrossTabService::optionLabels('roof_type'),       $ck . '_roof');
        $wallFreq        = CrossTabService::freq($hq(), 'wall_type',       CrossTabService::optionLabels('wall_type'),       $ck . '_wall');
        $foundationFreq  = CrossTabService::freq($hq(), 'foundation_type', CrossTabService::optionLabels('foundation_type'), $ck . '_foundation');
        $ownershipFreq   = CrossTabService::freq($hq(), 'ownership_type',  CrossTabService::optionLabels('ownership_type'),  $ck . '_ownership');
        $conditionFreq   = CrossTabService::freq($hq(), 'house_condition', CrossTabService::optionLabels('house_condition'), $ck . '_condition');
        $houseTypeFreq   = CrossTabService::freq($hq(), 'house_type',      CrossTabService::optionLabels('house_type'),      $ck . '_house_type');

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
                ['id' => 'roof',        'title' => 'छानाको प्रकार',    'title_en' => 'Roof Type',        'data' => $roofFreq],
                ['id' => 'wall',        'title' => 'गारोको प्रकार',    'title_en' => 'Wall Type',        'data' => $wallFreq],
                ['id' => 'foundation',  'title' => 'जगको प्रकार',      'title_en' => 'Foundation Type',  'data' => $foundationFreq],
                ['id' => 'ownership',   'title' => 'घर स्वामित्व',     'title_en' => 'Ownership Type',   'data' => $ownershipFreq],
                ['id' => 'condition',   'title' => 'घरको अवस्था',      'title_en' => 'House Condition',  'data' => $conditionFreq],
                ['id' => 'house_type',  'title' => 'घरको किसिम',       'title_en' => 'House Type',       'data' => $houseTypeFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Utilities & Facilities
    // ─────────────────────────────────────────────────────────────────────────

    public function utilities(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('util', $lg->id, $request->only(['ward_id']));

        $uq = fn () => HouseholdUtility::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $waterFreq    = CrossTabService::freq($uq(), 'water_source',           CrossTabService::optionLabels('water_source'),            $ck . '_water');
        $toiletFreq   = CrossTabService::freq($uq(), 'toilet_type',            CrossTabService::optionLabels('toilet_type'),             $ck . '_toilet');
        $electricFreq = CrossTabService::freq($uq(), 'electricity_source',     CrossTabService::optionLabels('electricity_source'),      $ck . '_electric');
        $fuelFreq     = CrossTabService::freq($uq(), 'cooking_fuel',           CrossTabService::optionLabels('cooking_fuel'),            $ck . '_fuel');
        $wasteFreq    = CrossTabService::freq($uq(), 'solid_waste_management', CrossTabService::optionLabels('solid_waste_management'),  $ck . '_waste');
        $internetFreq = CrossTabService::freq($uq(), 'internet_type',          CrossTabService::optionLabels('internet_type'),           $ck . '_internet');

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
                ['id' => 'water',    'title' => 'खानेपानीको स्रोत',    'title_en' => 'Water Source',         'data' => $waterFreq],
                ['id' => 'toilet',   'title' => 'शौचालयको प्रकार',     'title_en' => 'Toilet Type',          'data' => $toiletFreq],
                ['id' => 'electric', 'title' => 'विद्युत स्रोत',        'title_en' => 'Electricity Source',   'data' => $electricFreq],
                ['id' => 'fuel',     'title' => 'खाना पकाउने इन्धन',   'title_en' => 'Cooking Fuel',         'data' => $fuelFreq],
                ['id' => 'waste',    'title' => 'फोहर व्यवस्थापन',      'title_en' => 'Solid Waste Mgmt',     'data' => $wasteFreq],
                ['id' => 'internet', 'title' => 'इन्टरनेट सेवा',        'title_en' => 'Internet Type',        'data' => $internetFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Economics
    // ─────────────────────────────────────────────────────────────────────────

    public function economics(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('econ', $lg->id, $request->only(['ward_id']));

        $eq = fn () => HouseholdEconomic::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')->where('status', 'published')
        );

        $incomeFreq     = CrossTabService::freq($eq(), 'primary_income_source',   CrossTabService::optionLabels('primary_income_source'), $ck . '_income_src');
        $incomeRangeFreq= CrossTabService::freq($eq(), 'annual_income_range',      CrossTabService::optionLabels('annual_income_range'),    $ck . '_income_range');
        $expendFreq     = CrossTabService::freq($eq(), 'annual_expenditure_range', CrossTabService::optionLabels('annual_expenditure_range'), $ck . '_expend');
        $bankFreq       = CrossTabService::freq($eq(), 'has_bank_account',         ['1'=>'बैंक खाता छ','0'=>'बैंक खाता छैन'], $ck . '_bank');
        $povertyFreq    = CrossTabService::freq($eq(), 'poverty_card',             ['1'=>'परिचयपत्र छ','0'=>'परिचयपत्र छैन'], $ck . '_poverty');

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
                ['id' => 'bank',         'title' => 'बैंक खाता',              'title_en' => 'Bank Account',           'data' => $bankFreq],
                ['id' => 'poverty',      'title' => 'गरिबी परिचयपत्र',        'title_en' => 'Poverty Card',           'data' => $povertyFreq],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Age Groups (detailed population pyramid data)
    // ─────────────────────────────────────────────────────────────────────────

    public function ageGroups(Request $request)
    {
        $lg      = $this->defaultLg();
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
}
