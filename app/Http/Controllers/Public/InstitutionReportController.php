<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionEducation;
use App\Models\InstitutionHealth;
use App\Models\InstitutionInfrastructure;
use App\Models\InstitutionService;
use App\Models\InstitutionStaff;
use App\Models\InstitutionType;
use App\Models\LocalGovernment;
use App\Models\SurveyOption;
use App\Models\Ward;
use App\Services\CrossTabService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class InstitutionReportController extends Controller
{
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

    private function wardIds(LocalGovernment $lg, ?int $wardId = null): array
    {
        $q = Ward::where('local_government_id', $lg->id);
        if ($wardId) $q->where('id', $wardId);
        return $q->pluck('id')->toArray();
    }

    private function baseQuery(array $wardIds): \Illuminate\Database\Eloquent\Builder
    {
        return Institution::whereIn('ward_id', $wardIds)->where('status', 'published');
    }

    private function filterProps(LocalGovernment $lg, Request $request): array
    {
        $wards = Ward::where('local_government_id', $lg->id)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number']);

        return [
            'wards'   => $wards,
            'filters' => ['ward_id' => $request->input('ward_id')],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Index — overview
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $lg = $this->defaultLg();
        if (!$lg) return Inertia::render('Public/InstitutionReports/Index', ['lg' => null]);

        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $base    = $this->baseQuery($wardIds);

        $allWardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();

        $total      = (clone $base)->count();
        $totalTypes = InstitutionType::count();

        // Count by type
        $byType = (clone $base)
            ->join('institution_types', 'institutions.institution_type_id', '=', 'institution_types.id')
            ->select('institution_types.name_en', 'institution_types.name_np', DB::raw('count(*) as count'))
            ->groupBy('institution_types.name_en', 'institution_types.name_np')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => [
                'name_en' => $r->name_en,
                'name_np' => $r->name_np,
                'count'   => $r->count,
            ]);

        // Count by ward
        $byWard = (clone $base)
            ->join('wards', 'institutions.ward_id', '=', 'wards.id')
            ->select('wards.ward_number', DB::raw('count(*) as count'))
            ->groupBy('wards.ward_number')
            ->orderBy('wards.ward_number')
            ->pluck('count', 'ward_number');

        // Ownership breakdown
        $byOwnership = (clone $base)
            ->whereNotNull('ownership_type')->where('ownership_type', '!=', '')
            ->select('ownership_type', DB::raw('count(*) as count'))
            ->groupBy('ownership_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['value' => $r->ownership_type, 'count' => $r->count]);

        return Inertia::render('Public/InstitutionReports/Index', [
            'lg'          => $this->lgProps($lg),
            'summary'     => [
                'total_institutions' => $total,
                'total_types'        => $totalTypes,
                'total_wards'        => count($allWardIds),
            ],
            'byType'      => $byType,
            'byWard'      => $byWard,
            'byOwnership' => $byOwnership,
            ...$this->filterProps($lg, $request),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. By Type
    // ─────────────────────────────────────────────────────────────────────────

    public function byType(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('inst_type', $lg->id, $request->only(['ward_id']));

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        // Type labels from DB — key=name_en (used in SQL), value=Nepali label
        $typeLabels = InstitutionType::all()
            ->mapWithKeys(fn($t) => [$t->name_en => $t->name_np ?: $t->name_en])
            ->toArray();

        $typeByWard = CrossTabService::tab(
            Institution::whereIn('institutions.ward_id', $wardIds)
                ->where('institutions.status', 'published')
                ->join('institution_types', 'institutions.institution_type_id', '=', 'institution_types.id')
                ->join('wards', 'institutions.ward_id', '=', 'wards.id'),
            'institution_types.name_en',
            'wards.ward_number',
            $typeLabels,
            $wardColDef,
            $ck . '_typexward'
        );

        $typeFreq = CrossTabService::freq(
            Institution::whereIn('institutions.ward_id', $wardIds)
                ->where('institutions.status', 'published')
                ->join('institution_types', 'institutions.institution_type_id', '=', 'institution_types.id'),
            'institution_types.name_en',
            $typeLabels,
            $ck . '_type'
        );

        return Inertia::render('Public/InstitutionReports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'by-type',
            'title'       => 'संस्थाको प्रकार अनुसार विवरण',
            'title_en'    => 'Institutions by Type',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'       => 'type_by_ward',
                    'title'    => 'संस्थाको प्रकार अनुसार वडा',
                    'title_en' => 'Institution Type by Ward',
                    'data'     => $typeByWard,
                ],
            ],
            'frequencies' => [
                [
                    'id'       => 'type',
                    'title'    => 'संस्थाको प्रकार वितरण',
                    'title_en' => 'Institution Type Distribution',
                    'data'     => $typeFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. By Ownership
    // ─────────────────────────────────────────────────────────────────────────

    public function byOwnership(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('inst_own', $lg->id, $request->only(['ward_id']));

        $ownershipLabels = [
            'govt'        => 'सरकारी / Government',
            'private'     => 'निजी / Private',
            'community'   => 'सामुदायिक / Community',
            'cooperative' => 'सहकारी / Cooperative',
            'ngo'         => 'गैसस / NGO',
            'joint'       => 'संयुक्त / Joint',
            'other'       => 'अन्य / Other',
        ];

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);
        $base       = $this->baseQuery($wardIds);

        $ownershipFreq = CrossTabService::freq(
            clone $base, 'ownership_type', $ownershipLabels, $ck . '_own'
        );

        $ownerByWard = CrossTabService::tab(
            Institution::whereIn('institutions.ward_id', $wardIds)
                ->where('institutions.status', 'published')
                ->join('wards', 'institutions.ward_id', '=', 'wards.id'),
            'institutions.ownership_type',
            'wards.ward_number',
            $ownershipLabels,
            $wardColDef,
            $ck . '_ownxward'
        );

        $ownerByType = CrossTabService::tab(
            Institution::whereIn('institutions.ward_id', $wardIds)
                ->where('institutions.status', 'published')
                ->join('institution_types', 'institutions.institution_type_id', '=', 'institution_types.id'),
            'institutions.ownership_type',
            'institution_types.name_en',
            $ownershipLabels,
            InstitutionType::pluck('name_en', 'name_en')->toArray(),
            $ck . '_ownxtype'
        );

        return Inertia::render('Public/InstitutionReports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'by-ownership',
            'title'       => 'स्वामित्व अनुसार विवरण',
            'title_en'    => 'Institutions by Ownership',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'       => 'ownership_by_ward',
                    'title'    => 'स्वामित्व अनुसार वडा',
                    'title_en' => 'Ownership by Ward',
                    'data'     => $ownerByWard,
                ],
                [
                    'id'       => 'ownership_by_type',
                    'title'    => 'स्वामित्व अनुसार संस्थाको प्रकार',
                    'title_en' => 'Ownership by Institution Type',
                    'data'     => $ownerByType,
                ],
            ],
            'frequencies' => [
                [
                    'id'       => 'ownership',
                    'title'    => 'स्वामित्वको प्रकार वितरण',
                    'title_en' => 'Ownership Type Distribution',
                    'data'     => $ownershipFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Infrastructure
    // ─────────────────────────────────────────────────────────────────────────

    public function infrastructure(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);

        $infra = fn () => InstitutionInfrastructure::whereHas('institution', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->where('status', 'published')
        );

        $total = $infra()->count();

        $booleanFields = [
            'has_electricity'   => ['बिजुली छ', 'Electricity'],
            'has_water'         => ['खानेपानी छ', 'Water Supply'],
            'has_internet'      => ['इन्टरनेट छ', 'Internet'],
            'has_ramp'          => ['ढलान (Ramp) छ', 'Ramp for Disabled'],
            'has_boundary_wall' => ['चारदेवारी छ', 'Boundary Wall'],
            'has_toilet'        => ['शौचालय छ', 'Toilet'],
            'earthquake_safe'   => ['भूकम्परोधी', 'Earthquake Safe'],
            'evacuation_plan'   => ['निकास योजना छ', 'Evacuation Plan'],
            'fire_extinguisher' => ['अग्निशमन यन्त्र', 'Fire Extinguisher'],
            'solar'             => ['सौर्य ऊर्जा', 'Solar Power'],
            'generator'         => ['जनरेटर', 'Generator'],
            'has_library'       => ['पुस्तकालय छ', 'Library'],
            'has_lab'           => ['प्रयोगशाला छ', 'Laboratory'],
        ];

        $facilityData = [];
        foreach ($booleanFields as $field => [$labelNp, $labelEn]) {
            $count = $infra()->where($field, true)->count();
            $facilityData[] = [
                'field'      => $field,
                'label_np'   => $labelNp,
                'label_en'   => $labelEn,
                'count'      => $count,
                'percentage' => $total > 0 ? round($count / $total * 100, 1) : 0,
            ];
        }

        usort($facilityData, fn($a, $b) => $b['count'] - $a['count']);

        $condLabels    = CrossTabService::optionLabels('inst_building_condition');
        $conditionFreq = [];
        $conditions = $infra()
            ->whereNotNull('condition')->where('condition', '!=', '')
            ->select('condition', DB::raw('count(*) as count'))
            ->groupBy('condition')->orderByDesc('count')->get();
        $condTotal = $conditions->sum('count');
        foreach ($conditions as $r) {
            $canonKey = array_key_first(array_filter(array_keys($condLabels), fn($k) => strtolower($k) === strtolower($r->condition))) ?? null;
            $conditionFreq[] = [
                'value'      => $r->condition,
                'label'      => ($canonKey ? ($condLabels[$canonKey] ?? $r->condition) : ($condLabels[$r->condition] ?? ucfirst($r->condition))),
                'count'      => $r->count,
                'percentage' => $condTotal > 0 ? round($r->count / $condTotal * 100, 1) : 0,
            ];
        }

        return Inertia::render('Public/InstitutionReports/Show', [
            'lg'             => $this->lgProps($lg),
            'category'       => 'infrastructure',
            'title'          => 'पूर्वाधार विवरण',
            'title_en'       => 'Infrastructure',
            ...$this->filterProps($lg, $request),
            'tables'         => [],
            'frequencies'    => [],
            'facilityData'   => $facilityData,
            'conditionFreq'  => $conditionFreq,
            'totalInfra'     => $total,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Services
    // ─────────────────────────────────────────────────────────────────────────

    public function services(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('inst_svc', $lg->id, $request->only(['ward_id']));

        $svcBase = fn () => InstitutionService::whereHas('institution', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->where('status', 'published')
        );

        $serviceTypeFreq = CrossTabService::freq(
            $svcBase(), 'service_type', CrossTabService::optionLabels('inst_service_type'), $ck . '_svctype'
        );

        $freeRows  = $svcBase()->select('is_free', DB::raw('count(*) as count'))->groupBy('is_free')->get();
        $freeTotal = $freeRows->sum('count');
        $freeFreq  = $freeRows->map(fn($r) => [
            'value'      => $r->is_free ? 'free' : 'paid',
            'label'      => $r->is_free ? 'निःशुल्क / Free' : 'शुल्क लाग्ने / Paid',
            'count'      => $r->count,
            'percentage' => $freeTotal > 0 ? round($r->count / $freeTotal * 100, 1) : 0,
        ])->values()->toArray();

        $totalBeneficiaries = $svcBase()->sum('annual_beneficiaries');
        $totalServices      = $svcBase()->count();

        return Inertia::render('Public/InstitutionReports/Show', [
            'lg'                 => $this->lgProps($lg),
            'category'           => 'services',
            'title'              => 'सेवाहरूको विवरण',
            'title_en'           => 'Services',
            ...$this->filterProps($lg, $request),
            'tables'             => [],
            'frequencies'        => [
                [
                    'id'       => 'service_type',
                    'title'    => 'सेवाको प्रकार',
                    'title_en' => 'Service Type',
                    'data'     => $serviceTypeFreq,
                ],
            ],
            'freeFreq'           => $freeFreq,
            'totalServices'      => $totalServices,
            'totalBeneficiaries' => (int) $totalBeneficiaries,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Staff
    // ─────────────────────────────────────────────────────────────────────────

    public function staff(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);

        $grouped        = SurveyOption::allGrouped($lg->id);
        $categoryLabels = $grouped['inst_staff_category'] ?? [];

        $staffBase = fn () => InstitutionStaff::whereHas('institution', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->where('status', 'published')
        );

        $totalMale      = $staffBase()->sum('male_count');
        $totalFemale    = $staffBase()->sum('female_count');
        $totalPermanent = $staffBase()->sum('permanent_count');
        $totalContract  = $staffBase()->sum('contract_count');
        $totalDalit     = $staffBase()->sum('dalit_count');
        $totalJanajati  = $staffBase()->sum('janajati_count');

        // Staff by category
        $byCategory = $staffBase()
            ->whereNotNull('staff_category')->where('staff_category', '!=', '')
            ->select(
                'staff_category',
                DB::raw('SUM(male_count) as male'),
                DB::raw('SUM(female_count) as female'),
                DB::raw('SUM(permanent_count) as permanent'),
                DB::raw('SUM(contract_count) as contract'),
                DB::raw('SUM(male_count) + SUM(female_count) as total')
            )
            ->groupBy('staff_category')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'category'     => $r->staff_category,
                'category_lbl' => $categoryLabels[$r->staff_category] ?? $r->staff_category,
                'male'         => (int) $r->male,
                'female'       => (int) $r->female,
                'permanent'    => (int) $r->permanent,
                'contract'     => (int) $r->contract,
                'total'        => (int) $r->total,
            ]);

        return Inertia::render('Public/InstitutionReports/Show', [
            'lg'             => $this->lgProps($lg),
            'category'       => 'staff',
            'title'          => 'कर्मचारी विवरण',
            'title_en'       => 'Staff',
            ...$this->filterProps($lg, $request),
            'tables'         => [],
            'frequencies'    => [],
            'staffSummary'   => [
                'male'      => (int) $totalMale,
                'female'    => (int) $totalFemale,
                'permanent' => (int) $totalPermanent,
                'contract'  => (int) $totalContract,
                'dalit'     => (int) $totalDalit,
                'janajati'  => (int) $totalJanajati,
                'total'     => (int) ($totalMale + $totalFemale),
            ],
            'byCategory'     => $byCategory,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Education
    // ─────────────────────────────────────────────────────────────────────────

    public function education(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);

        $eduBase = fn () => InstitutionEducation::whereHas('institution', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->where('status', 'published')
        );

        $totalMale        = (int) $eduBase()->sum('male_students');
        $totalFemale      = (int) $eduBase()->sum('female_students');
        $totalDalit       = (int) $eduBase()->sum('dalit_students');
        $totalJanajati    = (int) $eduBase()->sum('janajati_students');
        $totalScholarship = (int) $eduBase()->sum('scholarship_students');
        $totalDropout     = (int) $eduBase()->sum('dropout_count');
        $totalStudents    = $totalMale + $totalFemale;

        $totalSchools = InstitutionEducation::whereHas('institution', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->where('status', 'published')
        )->distinct('institution_id')->count('institution_id');

        $byGrade = $eduBase()
            ->whereNotNull('grade')->where('grade', '!=', '')
            ->select(
                'grade',
                DB::raw('SUM(male_students) as male'),
                DB::raw('SUM(female_students) as female'),
                DB::raw('SUM(dalit_students) as dalit'),
                DB::raw('SUM(janajati_students) as janajati'),
                DB::raw('SUM(scholarship_students) as scholarship'),
                DB::raw('SUM(dropout_count) as dropout'),
                DB::raw('SUM(male_students) + SUM(female_students) as total')
            )
            ->groupBy('grade')
            ->orderBy('grade')
            ->get()
            ->map(fn($r) => [
                'grade'       => $r->grade,
                'male'        => (int) $r->male,
                'female'      => (int) $r->female,
                'dalit'       => (int) $r->dalit,
                'janajati'    => (int) $r->janajati,
                'scholarship' => (int) $r->scholarship,
                'dropout'     => (int) $r->dropout,
                'total'       => (int) $r->total,
            ]);

        return Inertia::render('Public/InstitutionReports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'education',
            'title'       => 'शिक्षा विवरण',
            'title_en'    => 'Education',
            ...$this->filterProps($lg, $request),
            'tables'      => [],
            'frequencies' => [],
            'eduSummary'  => [
                'total_students'    => $totalStudents,
                'male'              => $totalMale,
                'female'            => $totalFemale,
                'dalit'             => $totalDalit,
                'janajati'          => $totalJanajati,
                'scholarship'       => $totalScholarship,
                'dropout'           => $totalDropout,
                'total_schools'     => (int) $totalSchools,
            ],
            'byGrade'     => $byGrade,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Health
    // ─────────────────────────────────────────────────────────────────────────

    public function health(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);

        $grouped          = SurveyOption::allGrouped($lg->id);
        $healthTypeLabels = $grouped['inst_health_type'] ?? [];

        $healthBase = fn () => InstitutionHealth::whereHas('institution', fn($q) =>
            $q->whereIn('ward_id', $wardIds)->where('status', 'published')
        );

        $totalFacilities = $healthBase()->count();
        $totalBeds       = (int) $healthBase()->sum('bed_count');
        $totalOpdDaily   = (int) $healthBase()->sum('opd_daily');
        $totalOpdMonthly = (int) $healthBase()->sum('opd_monthly');

        $serviceFields = [
            'delivery_service'      => ['प्रसूति सेवा', 'Delivery Service'],
            'emergency_service'     => ['आपतकालीन सेवा', 'Emergency Service'],
            'has_lab'               => ['प्रयोगशाला', 'Laboratory'],
            'has_xray'              => ['एक्सरे', 'X-Ray'],
            'has_ambulance'         => ['एम्बुलेन्स', 'Ambulance'],
            'vaccine_service'       => ['खोप सेवा', 'Vaccine Service'],
            'family_planning'       => ['परिवार नियोजन', 'Family Planning'],
            'has_ultrasound'        => ['अल्ट्रासाउन्ड', 'Ultrasound'],
            'has_ecg'               => ['ईसीजी', 'ECG'],
            'has_icu'               => ['आईसीयू', 'ICU'],
            'has_operation_theatre' => ['शल्यक्रिया कक्ष', 'Operation Theatre'],
            'has_blood_bank'        => ['रक्त बैंक', 'Blood Bank'],
            'has_dialysis'          => ['डायलिसिस', 'Dialysis'],
            'has_dental'            => ['दन्त सेवा', 'Dental'],
            'has_physiotherapy'     => ['फिजियोथेरापी', 'Physiotherapy'],
            'has_oxygen_plant'      => ['अक्सिजन प्लान्ट', 'Oxygen Plant'],
        ];

        $serviceData = [];
        foreach ($serviceFields as $field => [$labelNp, $labelEn]) {
            $count = $healthBase()->where($field, true)->count();
            $serviceData[] = [
                'field'      => $field,
                'label_np'   => $labelNp,
                'label_en'   => $labelEn,
                'count'      => $count,
                'percentage' => $totalFacilities > 0 ? round($count / $totalFacilities * 100, 1) : 0,
            ];
        }
        usort($serviceData, fn($a, $b) => $b['count'] - $a['count']);

        $byHealthType = $healthBase()
            ->whereNotNull('health_type')->where('health_type', '!=', '')
            ->select('health_type', DB::raw('count(*) as count'), DB::raw('SUM(bed_count) as beds'))
            ->groupBy('health_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => [
                'type'     => $r->health_type,
                'type_lbl' => $healthTypeLabels[$r->health_type] ?? $r->health_type,
                'count'    => (int) $r->count,
                'beds'     => (int) $r->beds,
            ]);

        $bedBreakdown = [
            'male_ward'   => (int) $healthBase()->sum('male_ward_beds'),
            'female_ward' => (int) $healthBase()->sum('female_ward_beds'),
            'children'    => (int) $healthBase()->sum('children_ward_beds'),
            'maternity'   => (int) $healthBase()->sum('maternity_beds'),
            'icu'         => (int) $healthBase()->sum('icu_beds'),
            'emergency'   => (int) $healthBase()->sum('emergency_beds'),
        ];

        return Inertia::render('Public/InstitutionReports/Show', [
            'lg'            => $this->lgProps($lg),
            'category'      => 'health',
            'title'         => 'स्वास्थ्य संस्था विवरण',
            'title_en'      => 'Health Facilities',
            ...$this->filterProps($lg, $request),
            'tables'        => [],
            'frequencies'   => [],
            'healthSummary' => [
                'total_facilities' => $totalFacilities,
                'total_beds'       => $totalBeds,
                'opd_daily'        => $totalOpdDaily,
                'opd_monthly'      => $totalOpdMonthly,
            ],
            'serviceData'   => $serviceData,
            'byHealthType'  => $byHealthType,
            'bedBreakdown'  => $bedBreakdown,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. School
    // ─────────────────────────────────────────────────────────────────────────

    public function school(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);

        // Survey option label maps
        $grouped      = SurveyOption::allGrouped($lg->id);
        $levelLabels  = $grouped['inst_school_level']    ?? [];
        $mgmtLabels   = $grouped['inst_management_type'] ?? [];
        $mediumLabels = $grouped['inst_medium']           ?? [];
        $gradeLabels  = $grouped['inst_grade']            ?? [];

        // Base query: only school types (has_education = true)
        $schoolBase = fn () => Institution::whereIn('ward_id', $wardIds)
            ->where('status', 'published')
            ->whereHas('institutionType', fn($q) => $q->where('has_education', true));

        $totalSchools = $schoolBase()->count();

        // School list with ward + education aggregates
        $schools = $schoolBase()
            ->with(['ward:id,ward_number', 'institutionType:id,name_np,name_en', 'education'])
            ->orderBy('name_np')
            ->get()
            ->map(function ($inst) use ($levelLabels, $mgmtLabels, $mediumLabels) {
                $ts          = $inst->survey_data['typespecific'] ?? [];
                $edu         = $inst->education;
                $totalMale   = (int) $edu->sum('male_students');
                $totalFemale = (int) $edu->sum('female_students');
                $teachers    = $inst->survey_data['teachers'] ?? [];
                $levelCode   = $ts['school_level']    ?? null;
                $mgmtCode    = $ts['management_type'] ?? null;
                $mediumCode  = $ts['medium']          ?? null;

                return [
                    'id'               => $inst->id,
                    'name_np'          => $inst->name_np,
                    'name_en'          => $inst->name_en,
                    'unique_code'      => $inst->unique_code,
                    'ward'             => $inst->ward?->ward_number,
                    'ownership_type'   => $inst->ownership_type,
                    'school_level'     => $levelCode,
                    'school_level_lbl' => $levelLabels[$levelCode] ?? $levelCode,
                    'management_type'  => $mgmtCode,
                    'management_lbl'   => $mgmtLabels[$mgmtCode]   ?? $mgmtCode,
                    'medium'           => $mediumCode,
                    'medium_lbl'       => $mediumLabels[$mediumCode] ?? $mediumCode,
                    'grade_from'       => $edu->min('grade'),
                    'grade_to'         => $edu->max('grade'),
                    'total_male'       => $totalMale,
                    'total_female'     => $totalFemale,
                    'total_students'   => $totalMale + $totalFemale,
                    'total_teachers'   => count($teachers),
                    'teacher_male'     => collect($teachers)->where('gender', 'male')->count(),
                    'teacher_female'   => collect($teachers)->where('gender', 'female')->count(),
                ];
            });

        $totalStudents = $schools->sum('total_students');
        $totalTeachers = $schools->sum('total_teachers');
        $teacherMale   = $schools->sum('teacher_male');
        $teacherFemale = $schools->sum('teacher_female');

        // By school level
        $byLevel = $schools
            ->filter(fn($s) => $s['school_level'])
            ->groupBy('school_level')
            ->map(fn($g, $k) => [
                'code'     => $k,
                'label'    => $levelLabels[$k] ?? $k,
                'count'    => $g->count(),
                'students' => (int) $g->sum('total_students'),
            ])
            ->sortByDesc('count')
            ->values();

        // By management type
        $byManagement = $schools
            ->filter(fn($s) => $s['management_type'])
            ->groupBy('management_type')
            ->map(fn($g, $k) => [
                'code'  => $k,
                'label' => $mgmtLabels[$k] ?? $k,
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->values();

        // By medium
        $byMedium = $schools
            ->filter(fn($s) => $s['medium'])
            ->groupBy('medium')
            ->map(fn($g, $k) => [
                'code'  => $k,
                'label' => $mediumLabels[$k] ?? $k,
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->values();

        // Grade enrollment aggregate (only school institutions)
        $gradeEnrollment = InstitutionEducation::whereHas('institution', fn($q) =>
            $q->whereIn('ward_id', $wardIds)
              ->where('status', 'published')
              ->whereHas('institutionType', fn($qt) => $qt->where('has_education', true))
        )
        ->select(
            'grade',
            DB::raw('SUM(male_students) as male'),
            DB::raw('SUM(female_students) as female'),
            DB::raw('SUM(dalit_students) as dalit'),
            DB::raw('SUM(janajati_students) as janajati'),
            DB::raw('SUM(scholarship_students) as scholarship'),
            DB::raw('SUM(dropout_count) as dropout'),
            DB::raw('SUM(male_students) + SUM(female_students) as total')
        )
        ->groupBy('grade')
        ->orderBy('grade')
        ->get()
        ->map(fn($r) => [
            'grade'       => $r->grade,
            'grade_label' => $gradeLabels[$r->grade] ?? $r->grade,
            'male'        => (int) $r->male,
            'female'      => (int) $r->female,
            'dalit'       => (int) $r->dalit,
            'janajati'    => (int) $r->janajati,
            'scholarship' => (int) $r->scholarship,
            'dropout'     => (int) $r->dropout,
            'total'       => (int) $r->total,
        ]);

        $totalScholarship = (int) $gradeEnrollment->sum('scholarship');
        $totalDropout     = (int) $gradeEnrollment->sum('dropout');
        $totalDalit       = (int) $gradeEnrollment->sum('dalit');
        $totalJanajati    = (int) $gradeEnrollment->sum('janajati');

        // ── Teacher detail records from survey_data.teachers ──────────────────
        $categoryLabels      = $grouped['inst_teacher_category']     ?? [];
        $qualificationLabels = $grouped['inst_teacher_qualification'] ?? [];
        $teacherLevelLabels  = $grouped['inst_teacher_level']         ?? [];
        $subjectLabels       = $grouped['inst_teacher_subject']       ?? [];

        $allTeachers = $schoolBase()
            ->with(['ward:id,ward_number'])
            ->whereNotNull('survey_data')
            ->get()
            ->flatMap(function ($inst) use ($categoryLabels, $qualificationLabels, $teacherLevelLabels, $subjectLabels) {
                $teachers = $inst->survey_data['teachers'] ?? [];
                if (!is_array($teachers) || empty($teachers)) return [];
                return collect($teachers)->map(fn($t) => [
                    'school_name'        => $inst->name_np ?: $inst->name_en,
                    'school_name_en'     => $inst->name_en,
                    'school_id'          => $inst->id,
                    'ward'               => $inst->ward?->ward_number,
                    'full_name'          => $t['full_name'] ?? null,
                    'gender'             => $t['gender'] ?? null,
                    'category'           => $t['category'] ?? null,
                    'category_lbl'       => $categoryLabels[$t['category'] ?? ''] ?? ($t['category'] ?? null),
                    'qualification'      => $t['qualification'] ?? null,
                    'qualification_lbl'  => $qualificationLabels[$t['qualification'] ?? ''] ?? ($t['qualification'] ?? null),
                    'appointment_level'  => $t['appointment_level'] ?? null,
                    'level_lbl'          => $teacherLevelLabels[$t['appointment_level'] ?? ''] ?? ($t['appointment_level'] ?? null),
                    'specialization'     => $t['specialization'] ?? null,
                    'specialization_lbl' => $subjectLabels[$t['specialization'] ?? ''] ?? ($t['specialization'] ?? null),
                    'subjects'           => is_array($t['subjects'] ?? null)
                        ? collect($t['subjects'])->map(fn($s) => $subjectLabels[$s] ?? $s)->values()->toArray()
                        : [],
                    'enrollment_date'    => $t['enrollment_date'] ?? null,
                ]);
            })
            ->values();

        $byTeacherCategory = $allTeachers
            ->filter(fn($t) => !empty($t['category']))
            ->groupBy('category')
            ->map(fn($g, $k) => [
                'code'   => $k,
                'label'  => $categoryLabels[$k] ?? $k,
                'count'  => $g->count(),
                'male'   => $g->where('gender', 'male')->count(),
                'female' => $g->where('gender', 'female')->count(),
            ])
            ->sortByDesc('count')
            ->values();

        return Inertia::render('Public/InstitutionReports/School', [
            'lg'                => $this->lgProps($lg),
            ...$this->filterProps($lg, $request),
            'summary'           => [
                'total_schools'   => $totalSchools,
                'total_students'  => (int) $totalStudents,
                'total_male'      => (int) $schools->sum('total_male'),
                'total_female'    => (int) $schools->sum('total_female'),
                'scholarship'     => $totalScholarship,
                'dropout'         => $totalDropout,
                'dalit'           => $totalDalit,
                'janajati'        => $totalJanajati,
                'total_teachers'  => (int) $totalTeachers,
                'teacher_male'    => (int) $teacherMale,
                'teacher_female'  => (int) $teacherFemale,
            ],
            'schools'            => $schools->values(),
            'byLevel'            => $byLevel,
            'byManagement'       => $byManagement,
            'byMedium'           => $byMedium,
            'gradeEnrollment'    => $gradeEnrollment,
            'teachers'           => $allTeachers,
            'byTeacherCategory'  => $byTeacherCategory,
        ]);
    }
}
