<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EditRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\MemberBusiness;
use App\Models\SurveyOption;
use App\Models\Ward;
use App\Services\BusinessService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BusinessController extends Controller
{
    public function __construct(
        private BusinessService  $service,
        private WorkflowService  $workflow,
    ) {}

    /* ── Helpers ──────────────────────────────────────────────────────────── */

    private function scopedWardIds(): array
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return Ward::where('local_government_id', $user->local_government_id)
                ->pluck('id')->toArray();
        }
        return $user->assignedWards()->pluck('wards.id')->toArray();
    }

    private function surveyOptions(): array
    {
        $cats = [
            'business_location', 'business_type', 'business_field',
            'sub_ProductionIndustry', 'sub_AgricultureAnimalHusbandary',
            'sub_Suppliers', 'sub_InformationAndCommunication',
            'sub_HotelAndRestaurant', 'sub_VehicleAndTransportation',
            'sub_EducationalInstutions', 'sub_ConsultancyBusiness',
            'sub_MechinaryMaintenance', 'sub_HospitalandClinc',
            'sub_FinancialInstutions', 'sub_AbroadjobsSecurity',
            'sub_FancyBusiness', 'sub_OtherShopBusiness',
            'business_registration_place',
        ];

        $options = [];
        foreach ($cats as $cat) {
            $options[$cat] = SurveyOption::where('category', $cat)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['value', 'label_ne', 'label_en'])
                ->map(fn($o) => ['value' => $o->value, 'label' => $o->label_ne, 'label_en' => $o->label_en])
                ->values()
                ->toArray();
        }
        return $options;
    }

    /* ── Index ────────────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $wardIds = $this->scopedWardIds();
        $user    = auth()->user();

        $query = MemberBusiness::with(['member', 'household', 'ward', 'settlement', 'enumerator'])
            ->whereIn('ward_id', $wardIds);

        // Scope enumerators to their own records
        if ($user->role === 'enumerator') {
            $query->where('enumerator_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('ward')) {
            $query->whereHas('ward', fn($q) => $q->where('ward_number', $request->ward));
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('unique_code', 'like', "%{$s}%")
                  ->orWhere('business_name', 'like', "%{$s}%")
                  ->orWhereHas('member', fn($mq) => $mq->where('full_name', 'like', "%{$s}%"));
            });
        }

        $businesses = $query->latest()->paginate(20)->through(fn($b) => [
            'id'              => $b->id,
            'unique_code'     => $b->unique_code,
            'business_name'   => $b->business_name,
            'business_field'  => $b->business_field,
            'business_type'   => $b->business_type,
            'status'          => $b->status,
            'member'          => $b->member ? ['id' => $b->member->id, 'full_name' => $b->member->full_name] : null,
            'household'       => $b->household ? ['id' => $b->household->id, 'unique_code' => $b->household->unique_code] : null,
            'ward'            => $b->ward ? ['ward_number' => $b->ward->ward_number] : null,
            'settlement'      => $b->settlement ? ['name_np' => $b->settlement->name_np] : null,
            'enumerator'      => $b->enumerator ? ['name' => $b->enumerator->name] : null,
            'created_at'      => $b->created_at?->diffForHumans(),
        ]);

        $wards = Ward::whereIn('id', $wardIds)->orderBy('ward_number')->pluck('ward_number');

        return Inertia::render('Business/Index', [
            'businesses' => $businesses,
            'wards'      => $wards,
            'filters'    => $request->only(['status', 'ward', 'search']),
        ]);
    }

    /* ── Create ───────────────────────────────────────────────────────────── */

    public function create(Request $request)
    {
        $wardIds   = $this->scopedWardIds();
        $wards     = Ward::whereIn('id', $wardIds)->orderBy('ward_number')->get(['id', 'ward_number']);

        // Pre-select household/member from query params (from member show page link)
        $selectedHousehold = null;
        $selectedMember    = null;

        if ($request->filled('household_id')) {
            $hh = Household::with(['settlement'])
                ->whereIn('ward_id', $wardIds)
                ->find($request->household_id);
            if ($hh) {
                $selectedHousehold = [
                    'id'          => $hh->id,
                    'unique_code' => $hh->unique_code,
                    'ward_id'     => $hh->ward_id,
                    'settlement'  => $hh->settlement ? ['name_np' => $hh->settlement->name_np] : null,
                ];
            }
        }

        if ($request->filled('member_id') && $selectedHousehold) {
            $m = HouseholdMember::where('household_id', $selectedHousehold['id'])
                ->whereDoesntHave('business')
                ->find($request->member_id);
            if ($m) {
                $selectedMember = [
                    'id'        => $m->id,
                    'full_name' => $m->full_name,
                    'age'       => $m->age,
                    'gender'    => $m->gender,
                ];
            }
        }

        return Inertia::render('Business/Form', [
            'mode'             => 'create',
            'wards'            => $wards,
            'selectedHousehold'=> $selectedHousehold,
            'selectedMember'   => $selectedMember,
            'surveyOptions'    => $this->surveyOptions(),
        ]);
    }

    /* ── Store ────────────────────────────────────────────────────────────── */

    public function store(Request $request)
    {
        $request->validate([
            'household_member_id' => 'required|integer|exists:household_members,id',
            'business_name'       => 'required|string|max:200',
            'business_location'   => 'nullable|string|max:50',
            'business_type'       => 'nullable|string|max:60',
            'business_field'      => 'nullable|string|max:80',
        ]);

        // Prevent duplicate
        $exists = MemberBusiness::where('household_member_id', $request->household_member_id)->exists();
        if ($exists) {
            return back()->withErrors(['household_member_id' => 'यो सदस्यको व्यवसाय विवरण पहिले नै दर्ता भएको छ।']);
        }

        $business = $this->service->create($request->all(), auth()->id());

        return redirect()->route('businesses.show', $business)
            ->with('success', "व्यवसाय {$business->unique_code} सफलतापूर्वक सुरक्षित गरियो।");
    }

    /* ── Show ─────────────────────────────────────────────────────────────── */

    public function show(MemberBusiness $business)
    {
        $this->authorizeWard($business->ward_id);

        $business->load(['member.household', 'ward', 'settlement', 'enumerator', 'wardReviewer', 'publisher']);

        return Inertia::render('Business/Show', [
            'business'      => $business,
            'member'        => $business->member ? [
                'id'        => $business->member->id,
                'full_name' => $business->member->full_name,
                'age'       => $business->member->age,
                'gender'    => $business->member->gender,
            ] : null,
            'household'     => $business->member?->household ? [
                'id'          => $business->member->household->id,
                'unique_code' => $business->member->household->unique_code,
            ] : null,
            'canEdit'        => auth()->user()->isAdmin()
                                 || in_array($business->status, ['draft', 'rejected']),
            'canSubmit'      => $business->status === 'draft',
            'canPublish'     => auth()->user()->isAdmin() && in_array($business->status, ['ward_approved', 'escalated']),
            'canRequestEdit' => auth()->user()->isReviewer() && $business->status === 'published'
                                 && !EditRequest::where('requestable_type', MemberBusiness::class)
                                                ->where('requestable_id', $business->id)
                                                ->where('status', 'pending')
                                                ->exists(),
            'hasPendingEditRequest' => auth()->user()->isReviewer() && EditRequest::where('requestable_type', MemberBusiness::class)
                                                ->where('requestable_id', $business->id)
                                                ->where('status', 'pending')
                                                ->exists(),
            'surveyOptions' => $this->surveyOptions(),
        ]);
    }

    /* ── Edit ─────────────────────────────────────────────────────────────── */

    public function edit(MemberBusiness $business)
    {
        $this->authorizeWard($business->ward_id);
        $user = auth()->user();
        if (!$user->isAdmin()) {
            abort_unless(in_array($business->status, ['draft', 'rejected']), 403, 'यो रेकर्ड सम्पादन गर्न सकिँदैन।');
        }

        $business->load(['member', 'ward', 'settlement']);

        return Inertia::render('Business/Form', [
            'mode'          => 'edit',
            'business'      => $business,
            'member'        => $business->member ? [
                'id'        => $business->member->id,
                'full_name' => $business->member->full_name,
                'age'       => $business->member->age,
                'gender'    => $business->member->gender,
            ] : null,
            'household'     => $business->member?->household_id ? [
                'id'          => $business->member->household_id,
                'unique_code' => $business->household?->unique_code,
            ] : null,
            'surveyOptions' => $this->surveyOptions(),
            'wards'         => [],
            'selectedHousehold' => null,
            'selectedMember'    => null,
        ]);
    }

    /* ── Update ───────────────────────────────────────────────────────────── */

    public function update(Request $request, MemberBusiness $business)
    {
        $this->authorizeWard($business->ward_id);
        $user = auth()->user();
        if (!$user->isAdmin()) {
            abort_unless(in_array($business->status, ['draft', 'rejected']), 403);
        }

        $request->validate([
            'business_name' => 'required|string|max:200',
        ]);

        $wasPublished = $business->status === 'published';
        $oldValues    = $wasPublished ? $business->toArray() : null;

        $this->service->update($business, $request->all());

        if ($wasPublished) {
            AuditLog::create([
                'user_id'        => auth()->id(),
                'event_type'     => 'published_edit',
                'auditable_type' => MemberBusiness::class,
                'auditable_id'   => $business->id,
                'old_values'     => $oldValues,
                'new_values'     => $business->fresh()->toArray(),
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
            ]);
        }

        return redirect()->route('businesses.show', $business)
            ->with('success', "व्यवसाय {$business->unique_code} अपडेट गरियो।");
    }

    /* ── Submit ───────────────────────────────────────────────────────────── */

    public function submit(Request $request, MemberBusiness $business)
    {
        $this->authorizeWard($business->ward_id);
        abort_unless($business->status === 'draft', 422, 'केवल मस्यौदा पेस गर्न सकिन्छ।');

        $this->workflow->submit($business, auth()->user());

        return back()->with('success', "व्यवसाय {$business->unique_code} पेस गरियो।");
    }

    /* ── AJAX: households for a ward ─────────────────────────────────────── */

    public function householdsForWard(Request $request)
    {
        $wardIds      = $this->scopedWardIds();
        $wardId       = $request->integer('ward_id');
        $settlementId = $request->integer('settlement_id') ?: null;
        $q            = trim($request->string('q'));

        abort_unless(in_array($wardId, $wardIds), 403);

        $query = Household::where('ward_id', $wardId)->with('settlement');

        if ($settlementId) {
            $query->where('settlement_id', $settlementId);
        }

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('unique_code',        'like', "%{$q}%")
                   ->orWhere('respondent_name',   'like', "%{$q}%")
                   ->orWhere('respondent_contact','like', "%{$q}%");
            });
        }

        $households = $query
            ->orderBy('unique_code')
            ->limit(80)
            ->get(['id', 'unique_code', 'settlement_id', 'respondent_name', 'respondent_contact'])
            ->map(fn($hh) => [
                'id'                 => $hh->id,
                'unique_code'        => $hh->unique_code,
                'respondent_name'    => $hh->respondent_name,
                'respondent_contact' => $hh->respondent_contact,
                'settlement'         => $hh->settlement ? ['name_np' => $hh->settlement->name_np] : null,
            ]);

        return response()->json($households);
    }

    public function settlementsForWard(Request $request)
    {
        $wardIds = $this->scopedWardIds();
        $wardId  = $request->integer('ward_id');

        abort_unless(in_array($wardId, $wardIds), 403);

        $settlements = \App\Models\Settlement::where('ward_id', $wardId)
            ->orderBy('name_np')
            ->get(['id', 'name_np']);

        return response()->json($settlements);
    }

    /* ── AJAX: eligible members for a household ───────────────────────────── */

    public function membersForHousehold(Request $request)
    {
        $wardIds     = $this->scopedWardIds();
        $householdId = $request->integer('household_id');

        $household = Household::whereIn('ward_id', $wardIds)->findOrFail($householdId);

        $members = HouseholdMember::where('household_id', $household->id)
            ->where('involveinbusiness', true)  // Step 14: व्यवसायमा संलग्न = छ
            ->where(fn($q) => $q->where('age', '>=', 16)->orWhereNull('age'))
            ->whereDoesntHave('business')       // only members without an existing business record
            ->orderBy('sort_order')
            ->get(['id', 'full_name', 'age', 'gender', 'involveinbusiness'])
            ->map(fn($m) => [
                'id'                => $m->id,
                'full_name'         => $m->full_name,
                'age'               => $m->age,
                'gender'            => $m->gender,
                'involveinbusiness' => $m->involveinbusiness,
            ]);

        return response()->json($members);
    }

    /* ── Publish (admin) ─────────────────────────────────────────────────── */

    public function publish(Request $request, MemberBusiness $business)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $this->workflow->publish($business, auth()->user());
        return back()->with('success', "व्यवसाय {$business->unique_code} प्रकाशित गरियो।");
    }

    /* ── Auth helper ──────────────────────────────────────────────────────── */

    private function authorizeWard(int $wardId): void
    {
        abort_unless(in_array($wardId, $this->scopedWardIds()), 403);
    }
}
