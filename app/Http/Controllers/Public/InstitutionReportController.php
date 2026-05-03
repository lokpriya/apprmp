<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionInfrastructure;
use App\Models\InstitutionService;
use App\Models\InstitutionStaff;
use App\Models\InstitutionType;
use App\Models\LocalGovernment;
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
            'logo_url' => $lg->logo && file_exists(storage_path('app/public/' . $lg->logo))
                            ? Storage::url($lg->logo)
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

        // Type labels from DB
        $typeLabels = InstitutionType::pluck('name_en', 'name_en')->toArray();

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
            'government'   => 'सरकारी / Government',
            'private'      => 'निजी / Private',
            'community'    => 'सामुदायिक / Community',
            'cooperative'  => 'सहकारी / Cooperative',
            'ngo'          => 'गैसस / NGO',
            'religious'    => 'धार्मिक / Religious',
            'other'        => 'अन्य / Other',
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

        $conditionFreq = [];
        $conditions = $infra()
            ->whereNotNull('condition')->where('condition', '!=', '')
            ->select('condition', DB::raw('count(*) as count'))
            ->groupBy('condition')->orderByDesc('count')->get();
        $condTotal = $conditions->sum('count');
        foreach ($conditions as $r) {
            $conditionFreq[] = [
                'value'      => $r->condition,
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
            $svcBase(), 'service_type', [], $ck . '_svctype'
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
                'category'  => $r->staff_category,
                'male'      => (int) $r->male,
                'female'    => (int) $r->female,
                'permanent' => (int) $r->permanent,
                'contract'  => (int) $r->contract,
                'total'     => (int) $r->total,
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
}
