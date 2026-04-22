<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EditRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\Ward;
use App\Services\HouseholdService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HouseholdController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user  = auth()->user();
        $lgId  = $user->local_government_id;

        $query = Household::with(['ward', 'settlement', 'enumerator'])->latest();

        if ($user->isEnumerator()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds)->where('enumerator_id', $user->id);
        } elseif ($user->isReviewer()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds);
        } elseif ($user->isAdmin()) {
            $query->whereHas('ward', fn($q) => $q->where('local_government_id', $lgId));
        }

        if ($s = $request->search) {
            $query->where(fn($q) => $q->where('respondent_name', 'like', "%$s%")->orWhere('unique_code', 'like', "%$s%"));
        }
        if ($request->ward_id)  $query->where('ward_id', $request->ward_id);
        if ($request->status)   $query->where('status', $request->status);

        $households = $query->paginate(20)->withQueryString()
            ->through(fn($hh) => [
                'id'                => $hh->id,
                'unique_code'       => $hh->unique_code,
                'respondent_name'   => $hh->respondent_name,
                'respondent_contact'=> $hh->respondent_contact,
                'ward'              => $hh->ward ? ['ward_number' => $hh->ward->ward_number] : null,
                'settlement'        => $hh->settlement ? ['name_np' => $hh->settlement->name_np] : null,
                'survey_date'       => $hh->survey_date?->format('Y-m-d'),
                'status'            => $hh->status,
                'enumerator'        => $hh->enumerator ? ['name' => $hh->enumerator->name] : null,
                'can_edit'          => $user->isAdmin() || (in_array($hh->status, ['draft','rejected']) && $hh->enumerator_id === $user->id),
                'can_submit'        => $hh->status === 'draft' && ($user->isAdmin() || $hh->enumerator_id === $user->id),
            ]);

        return Inertia::render('Households/Index', [
            'households'    => $households,
            'wards'         => Ward::where('local_government_id', $lgId)->orderBy('ward_number')->get(['id','ward_number']),
            'filters'       => $request->only('search','ward_id','status'),
            'surveyOptions' => SurveyOption::allGrouped($lgId)
                ->map(fn($opts) => collect($opts)
                    ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()->all()
                )->all(),
        ]);
    }

    public function create()
    {
        $user    = auth()->user();
        $lgId    = $user->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id');

        return Inertia::render('Households/Form', [
            'household'     => null,
            'wards'         => Ward::where('local_government_id', $lgId)
                                   ->orderBy('ward_number')
                                   ->get(['id', 'ward_number']),
            'settlements'   => Settlement::whereIn('ward_id', $wardIds)
                                         ->orderBy('name_np')
                                         ->get(['id', 'ward_id', 'name_np']),
            'initialWardId' => $user->isEnumerator()
                                   ? $user->assignedWards()->first()?->id
                                   : null,
            'surveyOptions' => SurveyOption::allGrouped($lgId)
                ->map(fn($opts) => collect($opts)
                    ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()->all()
                )->all(),
        ]);
    }

    public function show(Household $household)
    {
        $this->authorizeView($household);

        $household->load([
            'ward', 'settlement', 'enumerator',
            'housing', 'utilities', 'economic', 'agriculture', 'disaster',
            'members', 'livestock',
            'submissionLogs.actor',
        ]);

        $user = auth()->user();
        $lgId = $user->local_government_id;
        return Inertia::render('Households/Show', [
            'household'  => $household,
            'canEdit'        => $user->isAdmin()
                                 || (in_array($household->status, ['draft','rejected']) && $household->enumerator_id === $user->id),
            'canSubmit'      => $household->status === 'draft' && ($user->isAdmin() || $household->enumerator_id === $user->id),
            'canPublish'     => $household->status === 'ward_approved' && $user->isAdmin(),
            'canRequestEdit' => $user->isReviewer() && $household->status === 'published'
                                 && !EditRequest::where('requestable_type', Household::class)
                                                ->where('requestable_id', $household->id)
                                                ->where('status', 'pending')
                                                ->exists(),
            'hasPendingEditRequest' => $user->isReviewer() && EditRequest::where('requestable_type', Household::class)
                                                ->where('requestable_id', $household->id)
                                                ->where('status', 'pending')
                                                ->exists(),
            'surveyOptions' => SurveyOption::allGrouped($lgId)
                ->map(fn($opts) => collect($opts)
                    ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()->all()
                )->all(),
        ]);
    }

    public function edit(Household $household)
    {
        $this->authorizeEdit($household);

        $user    = auth()->user();
        $lgId    = $user->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id');

        $household->load(['housing', 'utilities', 'economic', 'agriculture', 'disaster', 'members', 'livestock']);

        return Inertia::render('Households/Form', [
            'household'     => $household,
            'wards'         => Ward::where('local_government_id', $lgId)
                                   ->orderBy('ward_number')
                                   ->get(['id', 'ward_number']),
            'settlements'   => Settlement::whereIn('ward_id', $wardIds)
                                         ->orderBy('name_np')
                                         ->get(['id', 'ward_id', 'name_np']),
            'initialWardId' => null,
            'surveyOptions' => SurveyOption::allGrouped($lgId)
                ->map(fn($opts) => collect($opts)
                    ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()->all()
                )->all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'ward_id'         => 'required|exists:wards,id',
            'respondent_name' => 'required|string|max:200',
        ]);

        $data = $request->except('save_draft');
        $data['survey_data'] = [
            'location'        => $data['location']        ?? [],
            'social'          => $data['social']          ?? [],
            'wellbeing'       => $data['wellbeing']       ?? [],
            'decisions'       => $data['decisions']       ?? [],
            'occupation'      => $data['occupation']      ?? [],
            'livestockDetail' => $data['livestockDetail'] ?? [],
            'beeFishSilk'     => $data['beeFishSilk']     ?? [],
            'cropProduction'  => $data['cropProduction']  ?? [],
            'health'          => $data['health']          ?? [],
        ];
        $data['total_members'] = count($data['members'] ?? []) ?: 1;

        $household = app(HouseholdService::class)->create($data, auth()->user());

        if ($request->boolean('save_draft')) {
            return redirect()->route('households.edit', $household)
                ->with('success', 'ड्राफ्ट सुरक्षित भयो।');
        }

        return redirect()->route('households.show', $household)
            ->with('success', 'घरधुरी विवरण सफलतापूर्वक सुरक्षित भयो।');
    }

    public function update(Request $request, Household $household)
    {
        $this->authorizeEdit($household);

        $wasPublished = $household->status === 'published';
        $oldValues    = $wasPublished ? $household->toArray() : null;

        $data = $request->except('save_draft');
        $data['survey_data'] = [
            'location'        => $data['location']        ?? [],
            'social'          => $data['social']          ?? [],
            'wellbeing'       => $data['wellbeing']       ?? [],
            'decisions'       => $data['decisions']       ?? [],
            'occupation'      => $data['occupation']      ?? [],
            'livestockDetail' => $data['livestockDetail'] ?? [],
            'beeFishSilk'     => $data['beeFishSilk']     ?? [],
            'cropProduction'  => $data['cropProduction']  ?? [],
            'health'          => $data['health']          ?? [],
        ];
        $data['total_members'] = count($data['members'] ?? []) ?: $household->total_members;

        app(HouseholdService::class)->update($household, $data);

        // Audit log for edits to published records
        if ($wasPublished) {
            AuditLog::create([
                'user_id'        => auth()->id(),
                'event_type'     => 'published_edit',
                'auditable_type' => Household::class,
                'auditable_id'   => $household->id,
                'old_values'     => $oldValues,
                'new_values'     => $household->fresh()->toArray(),
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
            ]);
        }

        if ($request->boolean('save_draft')) {
            return redirect()->route('households.edit', $household)
                ->with('success', 'ड्राफ्ट सुरक्षित भयो।');
        }

        return redirect()->route('households.show', $household)
            ->with('success', 'घरधुरी विवरण अद्यावधिक भयो।');
    }

    public function destroy(Household $household)
    {
        $this->authorizeEdit($household);

        if (!$household->isDraft()) {
            return back()->with('error', 'Only draft households can be deleted.');
        }

        $household->delete();

        return redirect()->route('households.index')->with('success', 'Household record deleted.');
    }

    public function submit(Request $request, Household $household)
    {
        $this->authorizeEdit($household);

        try {
            $this->workflow->submit($household, auth()->user());
            return redirect()->route('households.show', $household)
                ->with('success', 'Household submitted for ward review.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function publish(Request $request, Household $household)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        try {
            $this->workflow->publish($household, auth()->user());
            return back()->with('success', 'Household published successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function showMember(Household $household, HouseholdMember $member)
    {
        $this->authorizeView($household);
        abort_unless($member->household_id === $household->id, 404);

        $member->load(['migration', 'health', 'business']);
        $household->load(['ward', 'settlement', 'enumerator']);

        $user = auth()->user();
        $lgId = $user->local_government_id;

        $biz = $member->business;

        return Inertia::render('Households/Member', [
            'household'     => array_merge(
                $household->only(['id','unique_code','ward','settlement','status']),
                ['social' => $household->survey_data['social'] ?? []]
            ),
            'member'        => $member,
            'memberBusiness'=> $biz ? [
                'id'             => $biz->id,
                'unique_code'    => $biz->unique_code,
                'business_name'  => $biz->business_name,
                'business_field' => $biz->business_field,
                'business_type'  => $biz->business_type,
                'status'         => $biz->status,
            ] : null,
            'surveyOptions' => SurveyOption::allGrouped($lgId)
                ->map(fn($opts) => collect($opts)
                    ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()->all()
                )->all(),
        ]);
    }

    public function preview(Household $household)
    {
        $this->authorizeView($household);

        $household->load([
            'ward', 'settlement', 'enumerator',
            'housing', 'utilities', 'economic',
            'agriculture', 'disaster',
            'livestock' => fn($q) => $q->orderBy('id'),
            'members'   => fn($q) => $q->orderBy('sort_order'),
            'submissionLogs.actor',
        ]);

        $user = auth()->user();
        return response()->json([
            'household' => $household,
            'canEdit'   => in_array($household->status, ['draft','rejected']) && ($user->isAdmin() || $household->enumerator_id === $user->id),
            'canSubmit' => $household->status === 'draft' && ($user->isAdmin() || $household->enumerator_id === $user->id),
        ]);
    }

    private function authorizeView(Household $household): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) return;
        $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
        abort_unless(in_array($household->ward_id, $wardIds), 403);
    }

    private function authorizeEdit(Household $household): void
    {
        $user = auth()->user();
        // Admin can edit any record regardless of status (including published)
        if ($user->isAdmin()) return;
        // Enumerator can only edit their own draft/rejected records
        abort_unless($household->enumerator_id === $user->id, 403);
        abort_unless(in_array($household->status, ['draft', 'rejected']), 403);
    }
}
