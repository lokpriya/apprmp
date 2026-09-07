<?php

namespace App\Http\Controllers\Public;

use App\Exports\Reports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\HouseholdEconomic;
use App\Models\HouseholdHousing;
use App\Models\HouseholdMember;
use App\Models\HouseholdUtility;
use App\Models\LocalGovernment;
use App\Models\Ward;
use App\Services\CrossTabService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    // ── LG + ward helpers (same as ReportController) ─────────────────────────

    private function defaultLg(): ?LocalGovernment
    {
        // Canonical, cached and deterministic. Resolving this per controller meant
        // an uncached joined query on every public request, and copies that could
        // disagree with one another after a settings change.
        return LocalGovernment::getActiveFull();
    }

    private function wardIds(LocalGovernment $lg, ?int $wardId = null): array
    {
        $q = Ward::where('local_government_id', $lg->id);
        if ($wardId) $q->where('id', $wardId);
        return $q->pluck('id')->toArray();
    }

    private function memberQuery(array $wardIds, Request $request)
    {
        $q = HouseholdMember::whereHas('household', fn ($hq) =>
            $hq->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')
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

    private function filterSummary(Request $request, LocalGovernment $lg): string
    {
        $parts = [];
        if ($request->filled('ward_id')) {
            $w = Ward::find((int)$request->ward_id);
            if ($w) $parts[] = 'वडा ' . $w->ward_number;
        }
        $genderMap = ['male' => 'पुरुष', 'female' => 'महिला', 'other' => 'अन्य'];
        if ($request->filled('gender')) {
            $parts[] = $genderMap[$request->gender] ?? $request->gender;
        }
        if ($request->filled('age_min') || $request->filled('age_max')) {
            $parts[] = 'उमेर ' . ($request->age_min ?? '0') . '–' . ($request->age_max ?? '∞');
        }
        return $parts ? implode(' | ', $parts) : 'सबै';
    }

    // ── Main download dispatcher ───────────────────────────────────────────────

    public function download(string $category, Request $request): BinaryFileResponse
    {
        $lg = $this->defaultLg();
        abort_unless($lg, 404);

        $wardId  = $request->integer('ward_id') ?: null;
        $wardIds = $this->wardIds($lg, $wardId);

        [$tables, $frequencies, $title] = match($category) {
            'demographics' => $this->demographicsData($wardIds, $request, $lg->id),
            'age-groups'   => $this->ageGroupsData($wardIds, $request, $lg->id),
            'education'    => $this->educationData($wardIds, $request, $lg->id),
            'health'       => $this->healthData($wardIds, $request, $lg->id),
            'employment'   => $this->employmentData($wardIds, $request, $lg->id),
            'housing'      => $this->housingData($wardIds, $request, $lg->id),
            'utilities'    => $this->utilitiesData($wardIds, $request, $lg->id),
            'economics'    => $this->economicsData($wardIds, $request, $lg->id),
            default        => abort(404),
        };

        $filterSummary = $this->filterSummary($request, $lg);
        $filename      = $lg->name_en . '_' . $category . '_' . now()->format('Ymd') . '.xlsx';

        return Excel::download(
            new ReportExport($title, $lg->name_en, $filterSummary, $tables, $frequencies),
            $filename
        );
    }

    // ── Per-category data methods ─────────────────────────────────────────────

    private function demographicsData(array $wardIds, Request $request, int $lgId): array
    {
        $mq = $this->memberQuery($wardIds, $request);
        $ck = CrossTabService::cacheKey('demo', $lgId, $request->only(['ward_id','settlement_id']));

        $wards      = Ward::where('local_government_id', $lgId)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $genderByWardQ = (clone $mq)
            ->join('households',   'household_members.household_id', '=', 'households.id')
            ->join('wards',        'households.ward_id',            '=', 'wards.id');

        $tables = [
            [
                'id'       => 'gender_by_age',
                'title'    => 'लिङ्ग अनुसार उमेर समूह',
                'title_en' => 'Gender by Age Group',
                'data'     => CrossTabService::tab(
                    clone $mq, 'gender', CrossTabService::ageGroupExpr(),
                    CrossTabService::genderLabels(), CrossTabService::ageGroupLabels(),
                    $ck . '_gxage'
                ),
            ],
            [
                'id'       => 'gender_by_ward',
                'title'    => 'लिङ्ग अनुसार वडा',
                'title_en' => 'Gender by Ward',
                'data'     => CrossTabService::tab(
                    $genderByWardQ, 'household_members.gender', 'wards.ward_number',
                    CrossTabService::genderLabels(), $wardColDef, $ck . '_gxward'
                ),
            ],
            [
                'id'       => 'ethnicity_by_gender',
                'title'    => 'जाति/जनजाति अनुसार लिङ्ग',
                'title_en' => 'Ethnicity by Gender',
                'data'     => CrossTabService::tab(
                    clone $mq, 'ethnicity', 'gender',
                    [], CrossTabService::genderLabels(), $ck . '_ethxgender'
                ),
            ],
        ];

        $frequencies = [
            ['id' => 'ethnicity',    'title' => 'जातीय विवरण',             'title_en' => 'Ethnicity',     'data' => CrossTabService::freq(clone $mq, 'ethnicity',    [], $ck . '_ethnicity')],
            ['id' => 'religion',     'title' => 'धर्म अनुसार जनसंख्या',    'title_en' => 'Religion',      'data' => CrossTabService::freq(clone $mq, 'religion',     [], $ck . '_religion')],
            ['id' => 'mother_tongue','title' => 'मातृभाषा अनुसार जनसंख्या','title_en' => 'Mother Tongue', 'data' => CrossTabService::freq(clone $mq, 'mother_tongue',[], $ck . '_mother_tongue')],
        ];

        return [$tables, $frequencies, 'जनसांख्यिकीय विवरण'];
    }

    private function ageGroupsData(array $wardIds, Request $request, int $lgId): array
    {
        $mq = $this->memberQuery($wardIds, $request);
        $ck = CrossTabService::cacheKey('age', $lgId, $request->only(['ward_id','settlement_id']));

        $wards      = Ward::where('local_government_id', $lgId)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $ageByWardQ = (clone $mq)
            ->join('households', 'household_members.household_id', '=', 'households.id')
            ->join('wards',      'households.ward_id',            '=', 'wards.id');

        $tables = [
            [
                'id'       => 'age_by_gender',
                'title'    => 'उमेर समूह अनुसार लिङ्ग',
                'title_en' => 'Age Group by Gender',
                'chart'    => 'pyramid',
                'data'     => CrossTabService::tab(
                    clone $mq, CrossTabService::ageGroupExpr(), 'gender',
                    CrossTabService::ageGroupLabels(), CrossTabService::genderLabels(),
                    $ck . '_agexgender'
                ),
            ],
            [
                'id'       => 'age_by_ward',
                'title'    => 'उमेर समूह अनुसार वडा',
                'title_en' => 'Age Group by Ward',
                'data'     => CrossTabService::tab(
                    $ageByWardQ, CrossTabService::ageGroupExpr('household_members.age'),
                    'wards.ward_number', CrossTabService::ageGroupLabels(), $wardColDef,
                    $ck . '_agexward'
                ),
            ],
        ];

        return [$tables, [], 'उमेर समूह विवरण'];
    }

    private function educationData(array $wardIds, Request $request, int $lgId): array
    {
        $mq = $this->memberQuery($wardIds, $request);
        $ck = CrossTabService::cacheKey('edu', $lgId, $request->only(['ward_id','settlement_id','gender']));

        $tables = [
            [
                'id'       => 'edu_by_gender',
                'title'    => 'शैक्षिक स्तर अनुसार लिङ्ग',
                'title_en' => 'Education Level by Gender',
                'data'     => CrossTabService::tab(clone $mq, 'education_level', 'gender', [], CrossTabService::genderLabels(), $ck . '_eduxgender'),
            ],
            [
                'id'       => 'literacy_by_gender',
                'title'    => 'साक्षरता अनुसार लिङ्ग',
                'title_en' => 'Literacy by Gender',
                'data'     => CrossTabService::tab(clone $mq, 'is_literate', 'gender', ['1' => 'साक्षर', '0' => 'निरक्षर'], CrossTabService::genderLabels(), $ck . '_litxgender'),
            ],
        ];

        $frequencies = [
            ['id' => 'edu_status',  'title' => 'शिक्षा अवस्था',         'title_en' => 'Education Status', 'data' => CrossTabService::freq(clone $mq, 'education_status', [], $ck . '_edu_status')],
            ['id' => 'school_type', 'title' => 'विद्यालयको प्रकार',     'title_en' => 'School Type',      'data' => CrossTabService::freq(clone $mq, 'school_type',      [], $ck . '_school_type')],
            ['id' => 'not_school',  'title' => 'विद्यालय नजाने कारण',   'title_en' => 'Not in School',    'data' => CrossTabService::freq(clone $mq, 'cause_not_school', [], $ck . '_not_school')],
        ];

        return [$tables, $frequencies, 'शिक्षा सम्बन्धी विवरण'];
    }

    private function healthData(array $wardIds, Request $request, int $lgId): array
    {
        $mq = $this->memberQuery($wardIds, $request);
        $ck = CrossTabService::cacheKey('health', $lgId, $request->only(['ward_id','settlement_id','gender']));

        $tables = [
            [
                'id'       => 'disability_by_gender',
                'title'    => 'अपाङ्गता अनुसार लिङ्ग',
                'title_en' => 'Disability by Gender',
                'data'     => CrossTabService::tab(clone $mq, 'disability_status', 'gender', ['yes' => 'अपाङ्गता छ', 'no' => 'छैन'], CrossTabService::genderLabels(), $ck . '_disxgender'),
            ],
            [
                'id'       => 'chronic_by_gender',
                'title'    => 'दीर्घ रोग अनुसार लिङ्ग',
                'title_en' => 'Chronic Illness by Gender',
                'data'     => CrossTabService::tab(clone $mq, 'chronic_illness', 'gender', ['yes' => 'छ', 'no' => 'छैन'], CrossTabService::genderLabels(), $ck . '_chronicxgender'),
            ],
        ];

        $frequencies = [
            ['id' => 'disability_type', 'title' => 'अपाङ्गताको प्रकार',    'title_en' => 'Type of Disability', 'data' => CrossTabService::freq(clone $mq, 'disability_type', [], $ck . '_dis_type')],
            ['id' => 'ss_allowance',    'title' => 'सामाजिक सुरक्षा भत्ता', 'title_en' => 'SS Allowance',      'data' => CrossTabService::freq(clone $mq, 'ss_allowance',    [], $ck . '_ss_allowance')],
        ];

        return [$tables, $frequencies, 'स्वास्थ्य तथा अपाङ्गता विवरण'];
    }

    private function employmentData(array $wardIds, Request $request, int $lgId): array
    {
        $mq = $this->memberQuery($wardIds, $request);
        $ck = CrossTabService::cacheKey('emp', $lgId, $request->only(['ward_id','settlement_id','gender']));

        $tables = [
            [
                'id'       => 'occupation_by_gender',
                'title'    => 'पेसा अनुसार लिङ्ग',
                'title_en' => 'Occupation by Gender',
                'data'     => CrossTabService::tab(clone $mq, 'occupation_type', 'gender', [], CrossTabService::genderLabels(), $ck . '_occxgender'),
            ],
            [
                'id'       => 'employment_status_by_gender',
                'title'    => 'रोजगारी अवस्था अनुसार लिङ्ग',
                'title_en' => 'Employment Status by Gender',
                'data'     => CrossTabService::tab(clone $mq, 'employment_status', 'gender', [], CrossTabService::genderLabels(), $ck . '_empstatxgender'),
            ],
        ];

        $frequencies = [
            ['id' => 'occupation', 'title' => 'पेसाको वितरण', 'title_en' => 'Occupation Distribution', 'data' => CrossTabService::freq(clone $mq, 'occupation_type', [], $ck . '_occ')],
        ];

        return [$tables, $frequencies, 'पेसा तथा रोजगारी विवरण'];
    }

    private function housingData(array $wardIds, Request $request, int $lgId): array
    {
        $ck = CrossTabService::cacheKey('housing', $lgId, $request->only(['ward_id']));

        $hq = fn () => HouseholdHousing::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')
        );

        $frequencies = [
            ['id' => 'roof',       'title' => 'छानाको प्रकार',   'title_en' => 'Roof Type',       'data' => CrossTabService::freq($hq(), 'roof_type',       [], $ck . '_roof')],
            ['id' => 'wall',       'title' => 'गारोको प्रकार',   'title_en' => 'Wall Type',       'data' => CrossTabService::freq($hq(), 'wall_type',       [], $ck . '_wall')],
            ['id' => 'foundation', 'title' => 'जगको प्रकार',     'title_en' => 'Foundation Type', 'data' => CrossTabService::freq($hq(), 'foundation_type', [], $ck . '_foundation')],
            ['id' => 'ownership',  'title' => 'घर स्वामित्व',    'title_en' => 'Ownership Type',  'data' => CrossTabService::freq($hq(), 'ownership_type',  [], $ck . '_ownership')],
            ['id' => 'condition',  'title' => 'घरको अवस्था',     'title_en' => 'House Condition', 'data' => CrossTabService::freq($hq(), 'house_condition', [], $ck . '_condition')],
        ];

        $wards      = Ward::where('local_government_id', $lgId)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $tables = [
            [
                'id'       => 'roof_by_ward',
                'title'    => 'छाना प्रकार अनुसार वडा',
                'title_en' => 'Roof Type by Ward',
                'data'     => CrossTabService::tab(
                    HouseholdHousing::whereHas('household', fn ($q) =>
                        $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')
                    )->join('households', 'household_housing.household_id', '=', 'households.id')
                     ->join('wards',      'households.ward_id',             '=', 'wards.id'),
                    'household_housing.roof_type', 'wards.ward_number',
                    [], $wardColDef, $ck . '_roofxward'
                ),
            ],
        ];

        return [$tables, $frequencies, 'आवास सम्बन्धी विवरण'];
    }

    private function utilitiesData(array $wardIds, Request $request, int $lgId): array
    {
        $ck = CrossTabService::cacheKey('util', $lgId, $request->only(['ward_id']));

        $uq = fn () => HouseholdUtility::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')
        );

        $frequencies = [
            ['id' => 'water',    'title' => 'खानेपानीको स्रोत',  'title_en' => 'Water Source',       'data' => CrossTabService::freq($uq(), 'water_source',           [], $ck . '_water')],
            ['id' => 'toilet',   'title' => 'शौचालयको प्रकार',   'title_en' => 'Toilet Type',        'data' => CrossTabService::freq($uq(), 'toilet_type',            [], $ck . '_toilet')],
            ['id' => 'electric', 'title' => 'विद्युत स्रोत',      'title_en' => 'Electricity Source', 'data' => CrossTabService::freq($uq(), 'electricity_source',     [], $ck . '_electric')],
            ['id' => 'fuel',     'title' => 'खाना पकाउने इन्धन', 'title_en' => 'Cooking Fuel',       'data' => CrossTabService::freq($uq(), 'cooking_fuel',           [], $ck . '_fuel')],
            ['id' => 'waste',    'title' => 'फोहर व्यवस्थापन',    'title_en' => 'Waste Management',   'data' => CrossTabService::freq($uq(), 'solid_waste_management', [], $ck . '_waste')],
            ['id' => 'internet', 'title' => 'इन्टरनेट सेवा',      'title_en' => 'Internet Type',      'data' => CrossTabService::freq($uq(), 'internet_type',          [], $ck . '_internet')],
        ];

        return [[], $frequencies, 'सेवा तथा सुविधा विवरण'];
    }

    private function economicsData(array $wardIds, Request $request, int $lgId): array
    {
        $ck = CrossTabService::cacheKey('econ', $lgId, $request->only(['ward_id']));

        $eq = fn () => HouseholdEconomic::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->whereNotNull('ward_id')
        );

        $frequencies = [
            ['id' => 'income_src',   'title' => 'मुख्य आय स्रोत',    'title_en' => 'Primary Income Source', 'data' => CrossTabService::freq($eq(), 'primary_income_source',   [], $ck . '_income_src')],
            ['id' => 'income_range', 'title' => 'वार्षिक आय दायरा',  'title_en' => 'Annual Income Range',   'data' => CrossTabService::freq($eq(), 'annual_income_range',      [], $ck . '_income_range')],
            ['id' => 'expend',       'title' => 'वार्षिक खर्च दायरा', 'title_en' => 'Annual Expenditure',    'data' => CrossTabService::freq($eq(), 'annual_expenditure_range', [], $ck . '_expend')],
            ['id' => 'bank',         'title' => 'बैंक खाता',           'title_en' => 'Bank Account',          'data' => CrossTabService::freq($eq(), 'has_bank_account', ['1' => 'छ', '0' => 'छैन'], $ck . '_bank')],
            ['id' => 'poverty',      'title' => 'गरिबी परिचयपत्र',    'title_en' => 'Poverty Card',          'data' => CrossTabService::freq($eq(), 'poverty_card',     ['1' => 'छ', '0' => 'छैन'], $ck . '_poverty')],
        ];

        return [[], $frequencies, 'आर्थिक विवरण'];
    }
}
