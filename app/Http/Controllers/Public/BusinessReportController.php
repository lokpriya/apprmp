<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LocalGovernment;
use App\Models\MemberBusiness;
use App\Models\Ward;
use App\Services\CrossTabService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BusinessReportController extends Controller
{
    private function defaultLg(): ?LocalGovernment
    {
        return LocalGovernment::with(['district.province'])->first();
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
        return MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'published');
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
        if (!$lg) return Inertia::render('Public/BusinessReports/Index', ['lg' => null]);

        $wardIds    = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $allWardIds = Ward::where('local_government_id', $lg->id)->pluck('id')->toArray();
        $base       = $this->baseQuery($wardIds);

        $total = (clone $base)->count();

        // By business type
        $byType = (clone $base)
            ->whereNotNull('business_type')->where('business_type', '!=', '')
            ->select('business_type', DB::raw('count(*) as count'))
            ->groupBy('business_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($r) => ['value' => $r->business_type, 'count' => $r->count]);

        // By ward
        $byWard = (clone $base)
            ->join('wards', 'member_businesses.ward_id', '=', 'wards.id')
            ->select('wards.ward_number', DB::raw('count(*) as count'))
            ->groupBy('wards.ward_number')
            ->orderBy('wards.ward_number')
            ->pluck('count', 'ward_number');

        // Registration breakdown
        $registered   = (clone $base)->where('is_registered', true)->count();
        $unregistered = $total - $registered;

        // PAN/VAT
        $hasPanVat = (clone $base)->where('has_pan_vat', true)->count();

        // Paid tax
        $paidTax = (clone $base)->where('paid_business_tax', true)->count();

        return Inertia::render('Public/BusinessReports/Index', [
            'lg'      => $this->lgProps($lg),
            'summary' => [
                'total_businesses' => $total,
                'total_wards'      => count($allWardIds),
                'registered'       => $registered,
                'unregistered'     => $unregistered,
                'has_pan_vat'      => $hasPanVat,
                'paid_tax'         => $paidTax,
            ],
            'byType'  => $byType,
            'byWard'  => $byWard,
            ...$this->filterProps($lg, $request),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. By Business Type
    // ─────────────────────────────────────────────────────────────────────────

    public function byType(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('biz_type', $lg->id, $request->only(['ward_id']));

        $typeLabels = [
            'Single'        => 'एकल स्वामित्व / Single Owner',
            'Partnership'   => 'साझेदारी / Partnership',
            'PublicCompany' => 'सार्वजनिक कम्पनी / Public Company',
            'other'         => 'अन्य / Other',
        ];

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $typeByWard = CrossTabService::tab(
            MemberBusiness::whereIn('member_businesses.ward_id', $wardIds)
                ->where('member_businesses.status', 'published')
                ->join('wards', 'member_businesses.ward_id', '=', 'wards.id'),
            'member_businesses.business_type',
            'wards.ward_number',
            $typeLabels,
            $wardColDef,
            $ck . '_typexward'
        );

        $typeFreq = CrossTabService::freq(
            MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'published'),
            'business_type',
            $typeLabels,
            $ck . '_type'
        );

        return Inertia::render('Public/BusinessReports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'by-type',
            'title'       => 'व्यवसायको स्वामित्व प्रकार अनुसार विवरण',
            'title_en'    => 'Businesses by Ownership Type',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'       => 'type_by_ward',
                    'title'    => 'स्वामित्व प्रकार अनुसार वडा',
                    'title_en' => 'Business Type by Ward',
                    'data'     => $typeByWard,
                ],
            ],
            'frequencies' => [
                [
                    'id'       => 'type',
                    'title'    => 'व्यवसाय स्वामित्व प्रकार वितरण',
                    'title_en' => 'Business Ownership Type Distribution',
                    'data'     => $typeFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. By Business Field (sector)
    // ─────────────────────────────────────────────────────────────────────────

    public function byField(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('biz_field', $lg->id, $request->only(['ward_id']));

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $fieldByWard = CrossTabService::tab(
            MemberBusiness::whereIn('member_businesses.ward_id', $wardIds)
                ->where('member_businesses.status', 'published')
                ->whereNotNull('member_businesses.business_field')
                ->where('member_businesses.business_field', '!=', '')
                ->join('wards', 'member_businesses.ward_id', '=', 'wards.id'),
            'member_businesses.business_field',
            'wards.ward_number',
            [],
            $wardColDef,
            $ck . '_fieldxward'
        );

        $fieldFreq = CrossTabService::freq(
            MemberBusiness::whereIn('ward_id', $wardIds)
                ->where('status', 'published')
                ->whereNotNull('business_field')
                ->where('business_field', '!=', ''),
            'business_field',
            [],
            $ck . '_field'
        );

        // Sub-category frequency
        $subCatFreq = CrossTabService::freq(
            MemberBusiness::whereIn('ward_id', $wardIds)
                ->where('status', 'published')
                ->whereNotNull('business_sub_category')
                ->where('business_sub_category', '!=', ''),
            'business_sub_category',
            [],
            $ck . '_subcat'
        );

        return Inertia::render('Public/BusinessReports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'by-field',
            'title'       => 'व्यवसायको क्षेत्र अनुसार विवरण',
            'title_en'    => 'Businesses by Sector / Field',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'       => 'field_by_ward',
                    'title'    => 'व्यवसाय क्षेत्र अनुसार वडा',
                    'title_en' => 'Business Field by Ward',
                    'data'     => $fieldByWard,
                ],
            ],
            'frequencies' => [
                [
                    'id'       => 'field',
                    'title'    => 'व्यवसाय क्षेत्र वितरण',
                    'title_en' => 'Business Field Distribution',
                    'data'     => $fieldFreq,
                ],
                [
                    'id'       => 'sub_category',
                    'title'    => 'व्यवसाय उपश्रेणी वितरण',
                    'title_en' => 'Business Sub-Category Distribution',
                    'data'     => $subCatFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. By Location
    // ─────────────────────────────────────────────────────────────────────────

    public function byLocation(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('biz_loc', $lg->id, $request->only(['ward_id']));

        $locationLabels = [
            'UrbanArea'  => 'शहरी क्षेत्र / Urban Area',
            'MarketArea' => 'बजार क्षेत्र / Market Area',
            'RuralArea'  => 'ग्रामीण क्षेत्र / Rural Area',
        ];

        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $locationByWard = CrossTabService::tab(
            MemberBusiness::whereIn('member_businesses.ward_id', $wardIds)
                ->where('member_businesses.status', 'published')
                ->join('wards', 'member_businesses.ward_id', '=', 'wards.id'),
            'member_businesses.business_location',
            'wards.ward_number',
            $locationLabels,
            $wardColDef,
            $ck . '_locxward'
        );

        $locationFreq = CrossTabService::freq(
            MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'published'),
            'business_location',
            $locationLabels,
            $ck . '_loc'
        );

        // Location × Type cross-tab
        $locationByType = CrossTabService::tab(
            MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'published'),
            'business_location',
            'business_type',
            $locationLabels,
            [
                'Single'        => 'एकल / Single',
                'Partnership'   => 'साझेदारी / Partnership',
                'PublicCompany' => 'कम्पनी / Company',
                'other'         => 'अन्य / Other',
            ],
            $ck . '_locxtype'
        );

        return Inertia::render('Public/BusinessReports/Show', [
            'lg'          => $this->lgProps($lg),
            'category'    => 'by-location',
            'title'       => 'व्यवसाय स्थान अनुसार विवरण',
            'title_en'    => 'Businesses by Location',
            ...$this->filterProps($lg, $request),
            'tables'      => [
                [
                    'id'       => 'location_by_ward',
                    'title'    => 'व्यवसाय स्थान अनुसार वडा',
                    'title_en' => 'Business Location by Ward',
                    'data'     => $locationByWard,
                ],
                [
                    'id'       => 'location_by_type',
                    'title'    => 'व्यवसाय स्थान × स्वामित्व प्रकार',
                    'title_en' => 'Business Location × Ownership Type',
                    'data'     => $locationByType,
                ],
            ],
            'frequencies' => [
                [
                    'id'       => 'location',
                    'title'    => 'व्यवसाय स्थान वितरण',
                    'title_en' => 'Business Location Distribution',
                    'data'     => $locationFreq,
                ],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Registration & Compliance
    // ─────────────────────────────────────────────────────────────────────────

    public function registration(Request $request)
    {
        $lg      = $this->defaultLg();
        $wardIds = $this->wardIds($lg, $request->integer('ward_id') ?: null);
        $ck      = CrossTabService::cacheKey('biz_reg', $lg->id, $request->only(['ward_id']));

        $base  = $this->baseQuery($wardIds);
        $total = (clone $base)->count();

        // Registration rate
        $registered   = (clone $base)->where('is_registered', true)->count();
        $hasPanVat    = (clone $base)->where('has_pan_vat', true)->count();
        $paidTax      = (clone $base)->where('paid_business_tax', true)->count();

        // Fully compliant (registered + pan/vat + paid tax)
        $fullyCompliant = (clone $base)
            ->where('is_registered', true)
            ->where('has_pan_vat', true)
            ->where('paid_business_tax', true)
            ->count();

        $complianceCards = [
            ['key' => 'registered',      'count' => $registered,     'label_np' => 'दर्ता भएका',            'label_en' => 'Registered'],
            ['key' => 'unregistered',    'count' => $total - $registered, 'label_np' => 'दर्ता नभएका',     'label_en' => 'Unregistered'],
            ['key' => 'has_pan_vat',     'count' => $hasPanVat,      'label_np' => 'PAN/VAT भएका',         'label_en' => 'Has PAN/VAT'],
            ['key' => 'paid_tax',        'count' => $paidTax,        'label_np' => 'कर तिरेका',             'label_en' => 'Paid Business Tax'],
            ['key' => 'fully_compliant', 'count' => $fullyCompliant, 'label_np' => 'पूर्ण अनुपालन',        'label_en' => 'Fully Compliant'],
        ];

        foreach ($complianceCards as &$card) {
            $card['percentage'] = $total > 0 ? round($card['count'] / $total * 100, 1) : 0;
        }

        // Place of registration frequency — JSON array column, expand in PHP
        $regPlaceLabels = [
            'Municipality'      => 'नगरपालिका / Municipality',
            'HomeandCottageInd' => 'घरेलु तथा साना उद्योग / Home & Cottage Ind.',
            'CompanyRegistrar'  => 'कम्पनी रजिस्ट्रार / Company Registrar',
            'FCCI'              => 'वाणिज्य संघ / FCCI',
            'GovernmentOffice'  => 'सरकारी कार्यालय / Government Office',
            'other'             => 'अन्य / Other',
        ];

        $regPlaceRaw = MemberBusiness::whereIn('ward_id', $wardIds)
            ->where('status', 'published')
            ->where('is_registered', true)
            ->whereNotNull('place_of_registration')
            ->where('place_of_registration', '!=', '[]')
            ->pluck('place_of_registration');

        $regPlaceCounts = [];
        foreach ($regPlaceRaw as $jsonVal) {
            $items = is_array($jsonVal) ? $jsonVal : json_decode($jsonVal, true) ?? [];
            foreach ($items as $item) {
                $item = trim((string)$item);
                if ($item === '') continue;
                $regPlaceCounts[$item] = ($regPlaceCounts[$item] ?? 0) + 1;
            }
        }
        arsort($regPlaceCounts);
        $regPlaceTotal = array_sum($regPlaceCounts);
        $regPlaceFreq  = [];
        foreach ($regPlaceCounts as $key => $count) {
            $regPlaceFreq[] = [
                'key'   => $key,
                'label' => $regPlaceLabels[$key] ?? $key,
                'count' => $count,
            ];
        }

        // Registration by Ward cross-tab
        $wards      = Ward::where('local_government_id', $lg->id)->orderBy('ward_number')->get();
        $wardColDef = CrossTabService::wardColDef($wards);

        $boolLabels = ['1' => 'दर्ता भएका / Registered', '0' => 'दर्ता नभएका / Unregistered'];

        $regByWard = CrossTabService::tab(
            MemberBusiness::whereIn('member_businesses.ward_id', $wardIds)
                ->where('member_businesses.status', 'published')
                ->join('wards', 'member_businesses.ward_id', '=', 'wards.id'),
            'CAST(member_businesses.is_registered AS CHAR)',
            'wards.ward_number',
            $boolLabels,
            $wardColDef,
            $ck . '_regxward'
        );

        // Tax by type
        $taxByType = CrossTabService::tab(
            MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'published'),
            'business_type',
            'CAST(paid_business_tax AS CHAR)',
            [
                'Single'        => 'एकल / Single',
                'Partnership'   => 'साझेदारी / Partnership',
                'PublicCompany' => 'कम्पनी / Company',
                'other'         => 'अन्य / Other',
            ],
            ['1' => 'कर तिरेका / Paid Tax', '0' => 'कर नतिरेका / Unpaid'],
            $ck . '_taxtype'
        );

        return Inertia::render('Public/BusinessReports/Show', [
            'lg'               => $this->lgProps($lg),
            'category'         => 'registration',
            'title'            => 'दर्ता तथा कर अनुपालन विवरण',
            'title_en'         => 'Registration & Tax Compliance',
            ...$this->filterProps($lg, $request),
            'tables'           => [
                [
                    'id'       => 'reg_by_ward',
                    'title'    => 'दर्ता अवस्था अनुसार वडा',
                    'title_en' => 'Registration Status by Ward',
                    'data'     => $regByWard,
                ],
                [
                    'id'       => 'tax_by_type',
                    'title'    => 'कर अनुपालन × स्वामित्व प्रकार',
                    'title_en' => 'Tax Compliance by Business Type',
                    'data'     => $taxByType,
                ],
            ],
            'frequencies'      => [
                [
                    'id'       => 'reg_place',
                    'title'    => 'दर्ता स्थान वितरण',
                    'title_en' => 'Place of Registration Distribution',
                    'data'     => $regPlaceFreq,
                ],
            ],
            'complianceCards'  => $complianceCards,
            'totalBusinesses'  => $total,
        ]);
    }
}
