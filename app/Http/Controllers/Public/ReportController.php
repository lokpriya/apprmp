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
        return LocalGovernment::with(['district.province'])->where('is_active', true)->first()
            ?? LocalGovernment::with(['district.province'])->first();
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
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
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
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
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
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
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

        $maritalByGender = CrossTabService::tab(
            clone $mq,
            'marital_status',
            'gender',
            CrossTabService::optionLabels('marital_status'),
            CrossTabService::genderLabels(),
            $ck . '_maritalxgender'
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
                    'id'      => 'marital_by_gender',
                    'title'   => 'वैवाहिक अवस्था अनुसार लिङ्ग',
                    'title_en'=> 'Marital Status by Gender',
                    'data'    => $maritalByGender,
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
                    'id'      => 'blood_group',
                    'title'   => 'रक्त समूह',
                    'title_en'=> 'Blood Group',
                    'data'    => $bloodGroupFreq,
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
        $lg = $this->defaultLg();
        if (!$lg) abort(404);
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

        $vtSubjectFreq = CrossTabService::freq(
            (clone $mq)->where('vocational_training', 'yes'),
            'vt_subject',
            CrossTabService::optionLabels('vt_subject'),
            $ck . '_vt_subject'
        );

        $techTrainingFreq = CrossTabService::freq(
            clone $mq, 'techtraining',
            ['1' => 'प्राविधिक तालिम लिएको', '0' => 'प्राविधिक तालिम नलिएको'],
            $ck . '_techtraining'
        );

        $saTrainingFreq = CrossTabService::freq(
            clone $mq, 'sa_training',
            ['1' => 'साक्षरता/अन्य तालिम लिएको', '0' => 'नलिएको'],
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
                    'id'      => 'occupation',
                    'title'   => 'पेसाको वितरण',
                    'title_en'=> 'Occupation Distribution',
                    'data'    => $occupationFreq,
                ],
                [
                    'id'      => 'vt_subject',
                    'title'   => 'व्यावसायिक तालिमको विषय',
                    'title_en'=> 'Vocational Training Subject',
                    'data'    => $vtSubjectFreq,
                ],
                [
                    'id'      => 'techtraining',
                    'title'   => 'प्राविधिक तालिम',
                    'title_en'=> 'Technical Training',
                    'data'    => $techTrainingFreq,
                ],
                [
                    'id'      => 'sa_training',
                    'title'   => 'साक्षरता/अन्य तालिम',
                    'title_en'=> 'Literacy / Other Training',
                    'data'    => $saTrainingFreq,
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
            'frequencies' => [
                ['id' => 'agri',          'title' => 'कृषि अभ्यास',           'title_en' => 'Agriculture Practice',    'data' => $agriFreq],
                ['id' => 'land',          'title' => 'जमिन स्वामित्व',         'title_en' => 'Land Ownership',          'data' => $landFreq],
                ['id' => 'irrigation',    'title' => 'सिँचाइको स्रोत',          'title_en' => 'Irrigation Source',       'data' => $irrigationFreq],
                ['id' => 'agri_cat',      'title' => 'बाली/कृषि श्रेणी',        'title_en' => 'Crop / Agriculture Category', 'data' => $agriCategoryFreq],
                ['id' => 'farm_challenge','title' => 'कृषि समस्याहरू',           'title_en' => 'Farm Challenges',         'data' => $farmChallengeFreq],
                ['id' => 'chem_fert',     'title' => 'रासायनिक मल प्रयोग',    'title_en' => 'Chemical Fertilizer Use', 'data' => $chemFertFreq],
                ['id' => 'org_fert',      'title' => 'जैविक मल प्रयोग',       'title_en' => 'Organic Fertilizer Use',  'data' => $orgFertFreq],
                ['id' => 'sells',         'title' => 'उपज बिक्री',             'title_en' => 'Sells Produce',           'data' => $sellsFreq],
                ['id' => 'agri_loan',     'title' => 'कृषि ऋण',                'title_en' => 'Agricultural Loan',       'data' => $agriLoanFreq],
                ['id' => 'livestock',     'title' => 'पशुपालनको प्रकार',       'title_en' => 'Livestock Type',          'data' => $livestockFreq],
            ],
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

        $residenceFreq = CrossTabService::freq(
            clone $mq, 'residence_status', CrossTabService::optionLabels('residence_status'), $ck . '_residence'
        );

        $residenceByGender = CrossTabService::tab(
            clone $mq,
            'residence_status',
            'gender',
            CrossTabService::optionLabels('residence_status'),
            CrossTabService::genderLabels(),
            $ck . '_resxgender'
        );

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $residenceByWard = CrossTabService::tab(
            (clone $mq)
                ->join('households', 'household_members.household_id', '=', 'households.id')
                ->join('wards', 'households.ward_id', '=', 'wards.id'),
            'household_members.residence_status',
            'wards.ward_number',
            CrossTabService::optionLabels('residence_status'),
            $wardColDef,
            $ck . '_resxward'
        );

        // Abroad-specific members only
        $abroadMq = (clone $mq)->where('residence_status', 'abroad');

        $abroadJobFreq = CrossTabService::freq(
            clone $abroadMq, 'typeofabroadjob', CrossTabService::optionLabels('typeofabroadjob'), $ck . '_abroad_job'
        );

        $abroadStatusFreq = CrossTabService::freq(
            clone $abroadMq, 'abroadjobstatus', CrossTabService::optionLabels('abroadjobstatus'), $ck . '_abroad_status'
        );

        $abroadJobByGender = CrossTabService::tab(
            clone $abroadMq,
            'typeofabroadjob',
            'gender',
            CrossTabService::optionLabels('typeofabroadjob'),
            CrossTabService::genderLabels(),
            $ck . '_abroadxgender'
        );

        // Internal migration (district of residence)
        $internalMq   = (clone $mq)->whereNotNull('addrslocation')->where('residence_status', '!=', 'abroad');
        $districtFreq = CrossTabService::freq(
            $internalMq, 'addrslocation', CrossTabService::optionLabels('addrslocation'), $ck . '_district'
        );

        return Inertia::render('Public/Reports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'migration',
            'title'       => 'बसाइँसराइ तथा वैदेशिक रोजगार',
            'title_en'    => 'Migration & Abroad Employment',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'      => 'residence_by_gender',
                    'title'   => 'बसोबास अवस्था अनुसार लिङ्ग',
                    'title_en'=> 'Residence Status by Gender',
                    'data'    => $residenceByGender,
                ],
                [
                    'id'      => 'residence_by_ward',
                    'title'   => 'बसोबास अवस्था अनुसार वडा',
                    'title_en'=> 'Residence Status by Ward',
                    'data'    => $residenceByWard,
                ],
                [
                    'id'      => 'abroad_job_by_gender',
                    'title'   => 'विदेशी रोजगार प्रकार अनुसार लिङ्ग',
                    'title_en'=> 'Abroad Job Type by Gender',
                    'data'    => $abroadJobByGender,
                ],
            ],
            'frequencies' => [
                ['id' => 'residence',     'title' => 'बसोबास अवस्था',         'title_en' => 'Residence Status',       'data' => $residenceFreq],
                ['id' => 'abroad_job',    'title' => 'विदेशी रोजगारको प्रकार', 'title_en' => 'Type of Abroad Job',     'data' => $abroadJobFreq],
                ['id' => 'abroad_status', 'title' => 'विदेशी रोजगार अवस्था',   'title_en' => 'Abroad Job Status',      'data' => $abroadStatusFreq],
                ['id' => 'district',      'title' => 'देशभित्र बसोबासको जिल्ला','title_en' => 'Internal Residence District', 'data' => $districtFreq],
            ],
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
