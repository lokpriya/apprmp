<?php

namespace App\Http\Controllers;

use App\Support\InertiaPaginator;
use App\Models\AuditLog;
use App\Models\EditRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\Ward;
use App\Services\CompletenessScoreService;
use App\Services\DataQualityService;
use App\Services\DuplicateDetectionService;
use App\Services\HouseholdService;
use App\Services\WorkflowService;
use App\Support\ScopesWardAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HouseholdController extends Controller
{
    use ScopesWardAccess;

    public function __construct(private WorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user  = auth()->user();
        $lgId  = $user->local_government_id;

        $query = Household::with(['ward', 'settlement', 'enumerator', 'housing', 'utilities', 'economic', 'members'])->latest();

        if ($user->isEnumerator()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds)->where('enumerator_id', $user->id);
        } elseif ($user->isReviewer() || $user->isWardAdmin()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds);
        } elseif ($user->isAdmin()) {
            $query->whereHas('ward', fn($q) => $q->where('local_government_id', $lgId));
        } else {
            // Fail closed. Without this an unrecognised role falls through with no
            // scope applied and sees every household in the system. ward_admin was
            // added to this chain after it was first written, so roles do change
            // here, and the failure mode of forgetting a branch must be "sees
            // nothing", never "sees everything".
            $query->whereRaw('1 = 0');
        }

        if ($s = $request->search) {
            $query->where(fn($q) => $q->where('respondent_name', 'like', "%$s%")->orWhere('unique_code', 'like', "%$s%"));
        }
        if ($request->ward_id)  $query->where('ward_id', $request->ward_id);
        if ($request->status)   $query->where('status', $request->status);

        $perPage    = min(max((int)($request->per_page ?? 20), 10), 100);
        $households = $query->paginate($perPage)->withQueryString()
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
                'can_edit'          => $user->hasPermission('households.edit') && ($user->isAdmin() || ($user->isWardAdmin() && in_array($hh->ward_id, $user->getAssignedWardIds())) || (in_array($hh->status, ['draft','rejected','submitted']) && ($user->isReviewer() || $hh->enumerator_id === $user->id))),
                'can_submit'        => $user->hasPermission('households.submit') && in_array($hh->status, ['draft', 'rejected']) && ($user->isAdmin() || $user->isWardAdmin() || $user->isReviewer() || $hh->enumerator_id === $user->id),
                'can_approve'       => $user->hasPermission('households.approve') && (($user->isAdmin() && $hh->status === 'ward_approved') || (($user->isWardAdmin() || $user->isReviewer()) && $hh->status === 'submitted' && in_array($hh->ward_id, $user->getAssignedWardIds()))),
                'completeness'      => CompletenessScoreService::score($hh),
            ]);

        return Inertia::render('Households/Index', [
            'households'    => InertiaPaginator::format($households),
            // Only the wards this user can actually have results in, so the
            // filter cannot be set to a ward that always returns nothing.
            'wards'         => $this->selectableWards(),
            'filters'       => $request->only('search','ward_id','status','per_page'),
            'surveyOptions' => SurveyOption::allGroupedForVue($lgId),
        ]);
    }

    public function create()
    {
        $user    = auth()->user();
        $lgId    = $user->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id');

        return Inertia::render('Households/Form', [
            'household'     => null,
            'wards'         => $this->selectableWards(),
            'settlements'   => Settlement::whereIn('ward_id', $wardIds)
                                         ->orderBy('name_np')
                                         ->get(['id', 'ward_id', 'name_np']),
            // Anyone assigned to exactly one ward gets it filled in for them —
            // not only enumerators, as before. Ward admins are the common case.
            'initialWardId' => $this->defaultWardId(),
            'surveyOptions' => SurveyOption::allGroupedForVue($lgId),
            'mapConfig'     => \App\Models\LocalGovernment::getCached($lgId)?->map_config ?? [],
        ]);
    }

    public function show(Household $household)
    {
        $this->authorize('view', $household);

        $household->load([
            'ward', 'settlement', 'enumerator',
            'housing', 'utilities', 'economic', 'agriculture', 'disaster',
            'members', 'livestock',
            'submissionLogs.actor', 'fieldVerification.verifier',
        ]);

        $user = auth()->user();
        $lgId = $user->local_government_id;
        $fv   = $household->fieldVerification;
        return Inertia::render('Households/Show', [
            'household'  => $household,
            'canEdit'        => $user->hasPermission('households.edit')
                                 && ($user->isAdmin()
                                     || ($user->isWardAdmin() && in_array($household->ward_id, $user->getAssignedWardIds()))
                                     || (in_array($household->status, ['draft','rejected','submitted']) && ($user->isReviewer() || $household->enumerator_id === $user->id))),
            'canSubmit'      => $user->hasPermission('households.submit') && in_array($household->status, ['draft', 'rejected']) && ($user->isAdmin() || $user->isWardAdmin() || $user->isReviewer() || $household->enumerator_id === $user->id),
            'canPublish'     => $user->hasPermission('households.publish') && in_array($household->status, ['ward_approved', 'escalated'])
                                 && ($user->isAdmin() || ($user->isWardAdmin() && in_array($household->ward_id, $user->getAssignedWardIds()))),
            'canApprove'     => $user->hasPermission('households.approve')
                                 && (($user->isAdmin() && $household->status === 'ward_approved')
                                     || (($user->isWardAdmin() || $user->isReviewer()) && $household->status === 'submitted' && in_array($household->ward_id, $user->getAssignedWardIds()))),
            'canReject'      => $user->hasPermission('households.reject')
                                 && ($user->isAdmin()
                                     ? in_array($household->status, ['ward_approved', 'escalated', 'published'])
                                     : in_array($household->status, ['submitted', 'ward_approved']) && in_array($household->ward_id, $user->getAssignedWardIds())),
            'canRequestEdit' => $user->isReviewer() && $household->status === 'published'
                                 && !EditRequest::where('requestable_type', Household::class)
                                                ->where('requestable_id', $household->id)
                                                ->where('status', 'pending')
                                                ->exists(),
            'hasPendingEditRequest' => $user->isReviewer() && EditRequest::where('requestable_type', Household::class)
                                                ->where('requestable_id', $household->id)
                                                ->where('status', 'pending')
                                                ->exists(),
            'canVerify'          => ($user->isReviewer() || $user->isWardAdmin() || $user->isAdmin()) && $household->status !== 'draft',
            'canMarkDeceased'    => $user->hasPermission('households.mark_deceased'),
            'fieldVerification' => $fv ? [
                'visited_at'  => $fv->visited_at?->format('Y-m-d'),
                'is_verified' => $fv->is_verified,
                'notes'       => $fv->notes,
                'verifier'    => $fv->verifier ? ['name' => $fv->verifier->name] : null,
                'created_at'  => $fv->created_at?->format('Y-m-d'),
            ] : null,
            'surveyOptions' => SurveyOption::allGroupedForVue($lgId),
            'dataFlags'     => DataQualityService::flags($household),
            'flagMeta'      => DataQualityService::FLAG_META,
        ]);
    }

    public function edit(Household $household)
    {
        $this->authorize('update', $household);

        $user    = auth()->user();
        $lgId    = $user->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id');

        $household->load(['housing', 'utilities', 'economic', 'agriculture', 'disaster', 'members', 'livestock']);

        return Inertia::render('Households/Form', [
            'household'     => $household,
            // Pass the record's own ward so editing never blanks the select,
            // even if the user's assignments changed since it was created.
            'wards'         => $this->selectableWards($household->ward_id),
            'settlements'   => Settlement::whereIn('ward_id', $wardIds)
                                         ->orderBy('name_np')
                                         ->get(['id', 'ward_id', 'name_np']),
            'initialWardId' => null,
            'surveyOptions' => SurveyOption::allGroupedForVue($lgId),
            'mapConfig'     => \App\Models\LocalGovernment::getCached($lgId)?->map_config ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            // Not exists:wards,id — that let a ward admin file a record into a
            // ward they cannot afterwards open, leaving it stranded there.
            'ward_id'         => $this->wardRule(),
            'respondent_name' => 'required|string|max:200',
        ], $this->wardRuleMessages());

        $user = auth()->user();
        if (!$user->isAdmin()) {
            $lg = \App\Models\LocalGovernment::getCached($user->local_government_id);
            if ($lg?->isSurveyFrozen()) {
                abort(403, 'सर्भेक्षण अवधि बन्द छ।');
            }
        }

        $data = $request->except('save_draft', 'duplicate_confirmed');
        $data['survey_data'] = [
            'location'        => $data['location']        ?? [],
            'social'          => $data['social']          ?? [],
            'wellbeing'       => $data['wellbeing']       ?? [],
            'decisions'       => $data['decisions']       ?? [],
            'occupation'      => $data['occupation']      ?? [],
            'livestockDetail' => $data['livestockDetail'] ?? [],
            'beeFishSilk'     => $data['beeFishSilk']     ?? [],
            'cropProduction'  => $data['cropProduction']  ?? [],
            'hasLandInMunicipality' => $data['hasLandInMunicipality'] ?? '',
            'landParcels'     => $data['landParcels']     ?? [],
            'health'          => $data['health']          ?? [],
        ];
        $data['total_members'] = count($data['members'] ?? []) ?: 1;

        // Duplicate detection — skip if user has already confirmed
        if (!$request->boolean('duplicate_confirmed') && !$request->boolean('save_draft')) {
            $dupResult = app(DuplicateDetectionService::class)->checkHousehold($data);
            $errors = [];
            if ($dupResult['found']) {
                $errors['duplicate_warning'] = json_encode($dupResult['matches']);
            }
            if ($dupResult['phone_warning'] ?? null) {
                $errors['phone_warning'] = $dupResult['phone_warning'];
            }
            if ($errors) {
                return back()->withErrors($errors)->withInput();
            }
        }

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
        $this->authorize('update', $household);

        // update() validated nothing, so an edit could move a record into any
        // ward at all. The record's current ward stays permitted so that saving
        // an unchanged form never fails.
        $request->validate([
            'ward_id'         => $this->wardRule($household->ward_id),
            'respondent_name' => 'required|string|max:200',
        ], $this->wardRuleMessages());

        $user = auth()->user();
        if (!$user->isAdmin()) {
            $lg = \App\Models\LocalGovernment::getCached($user->local_government_id);
            if ($lg?->isSurveyFrozen()) {
                abort(403, 'सर्भेक्षण अवधि बन्द छ।');
            }
        }

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
            'hasLandInMunicipality' => $data['hasLandInMunicipality'] ?? '',
            'landParcels'     => $data['landParcels']     ?? [],
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
        $this->authorize('delete', $household);

        if (!$household->isDraft()) {
            return back()->with('error', 'Only draft households can be deleted.');
        }

        $household->delete();

        return redirect()->route('households.index')->with('success', 'Household record deleted.');
    }

    public function submit(Request $request, Household $household)
    {
        $this->authorize('update', $household);

        $errors = $this->validateForSubmission($household);
        if (!empty($errors)) {
            $preview = implode(', ', array_slice($errors, 0, 4));
            $more    = count($errors) > 4 ? ' र ' . (count($errors) - 4) . ' थप' : '';
            return back()->with('error', 'फारम अपूर्ण छ — पेस गर्नु अघि भर्नुहोस्: ' . $preview . $more . '।');
        }

        try {
            $this->workflow->submit($household, auth()->user());
            return redirect()->route('households.show', $household)
                ->with('success', 'Household submitted for ward review.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function validateForSubmission(Household $household): array
    {
        $errors = [];

        // Step 1 — Basic info
        if (!$household->respondent_name)        $errors[] = 'उत्तरदाताको नाम';
        if (!$household->survey_date)            $errors[] = 'सर्वेक्षण मिति';
        if (!$household->gps_lat || !$household->gps_lng) $errors[] = 'GPS स्थान';

        $h = $household->housing;
        if (!$h?->house_type)       $errors[] = 'घरको प्रकार';
        if (!$h?->roof_type)        $errors[] = 'छानाको प्रकार';
        if (!$h?->wall_type)        $errors[] = 'पर्खालको प्रकार';
        if (!$h?->foundation_type)  $errors[] = 'जगको प्रकार';
        if (!$h?->ownership_type)   $errors[] = 'स्वामित्वको प्रकार';
        if (!$h?->house_condition)  $errors[] = 'घरको अवस्था';

        // Step 2 — Social
        $social = $household->survey_data['social'] ?? [];
        if (empty($social['family_head']))             $errors[] = 'घरमूलीको नाम';
        if (!($social['male_num'] ?? '') && !($social['female_num'] ?? '') && !($social['other_num'] ?? ''))
                                                       $errors[] = 'परिवार सदस्य संख्या';
        if (empty($social['family_type']))             $errors[] = 'परिवारको प्रकार';
        if (empty($social['ethnicity']))               $errors[] = 'जाति/जनजाति';
        if (empty($social['religion']))                $errors[] = 'धर्म';
        if (empty($social['mother_tongue']))           $errors[] = 'मातृभाषा';
        if (empty($social['major_family_occupation'])) $errors[] = 'मुख्य पेशा';
        if (!isset($social['migration_status']) || $social['migration_status'] === '') $errors[] = 'बसाइँसराइ अवस्था';
        if (!isset($social['have_land'])        || $social['have_land'] === '')        $errors[] = 'जग्गा स्वामित्व';
        if (!isset($social['health_insurance']) || $social['health_insurance'] === '') $errors[] = 'स्वास्थ्य बीमा';
        if (!isset($social['bankloan'])         || $social['bankloan'] === '')         $errors[] = 'ऋण विवरण';

        // Step 3 — Utilities
        $u = $household->utilities;
        if (!$u?->water_source)             $errors[] = 'पानीको स्रोत';
        if (!$u?->electricity_source)       $errors[] = 'बिजुलीको स्रोत';
        if (!$u?->solid_waste_management)   $errors[] = 'फोहोर व्यवस्थापन';

        // Step 12 — Economic
        $e = $household->economic;
        if (!$e?->primary_income_source)    $errors[] = 'मुख्य आम्दानीको स्रोत';
        if (!$e?->annual_income_range)      $errors[] = 'वार्षिक आम्दानी दायरा';
        if (!$e?->annual_expenditure_range) $errors[] = 'वार्षिक खर्च दायरा';

        // Step 13 — Disaster
        $d = $household->disaster;
        $affectedSet = isset($d?->affected_last_3years) && $d->affected_last_3years !== '';
        if (!$affectedSet) $errors[] = 'प्राकृतिक प्रकोप प्रभाव';

        // Step 14 — Members
        if ($household->members()->count() === 0) {
            $errors[] = 'कम्तीमा एक परिवार सदस्य';
        } else {
            foreach ($household->members as $i => $m) {
                if (!$m->full_name)        $errors[] = 'सदस्य ' . ($i + 1) . ': नाम';
                if (!$m->relation_to_head) $errors[] = 'सदस्य ' . ($i + 1) . ': नाता';
                if ($m->age === null || $m->age === '') $errors[] = 'सदस्य ' . ($i + 1) . ': उमेर';
                if (!$m->gender)           $errors[] = 'सदस्य ' . ($i + 1) . ': लिङ्ग';
            }
        }

        return $errors;
    }

    public function bulkSubmit(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user    = auth()->user();
        $wardIds = $user->isAdmin() ? null : $user->assignedWards()->pluck('wards.id')->toArray();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $hh = Household::find($id);
            if (!$hh || $hh->status !== 'draft') { $skipped++; continue; }
            if ($user->isWardAdmin()) {
                if (!in_array($hh->ward_id, $wardIds)) { $skipped++; continue; }
            } elseif (!$user->isAdmin()) {
                if ($hh->enumerator_id !== $user->id || !in_array($hh->ward_id, $wardIds)) { $skipped++; continue; }
            }
            try { $this->workflow->submit($hh, $user); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} घरधुरी सफलतापूर्वक पेस भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    public function bulkApprove(Request $request)
    {
        abort_unless(auth()->user()->isReviewer() || auth()->user()->isWardAdmin() || auth()->user()->isAdmin(), 403);
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user    = auth()->user();
        $wardIds = $user->isAdmin() ? null : $user->assignedWards()->pluck('wards.id')->toArray();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $hh = Household::find($id);
            if (!$hh || $hh->status !== 'submitted') { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($hh->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->wardApprove($hh, $user); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} घरधुरी अनुमोदन भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    public function bulkReject(Request $request)
    {
        abort_unless(auth()->user()->isReviewer() || auth()->user()->isWardAdmin() || auth()->user()->isAdmin(), 403);
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'reason' => 'required|string|max:500',
        ]);
        $user    = auth()->user();
        $wardIds = $user->isAdmin() ? null : $user->assignedWards()->pluck('wards.id')->toArray();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $hh = Household::find($id);
            if (!$hh || !in_array($hh->status, ['submitted', 'ward_approved'])) { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($hh->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->reject($hh, $user, $request->reason); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} घरधुरी अस्वीकार भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    public function publish(Request $request, Household $household)
    {
        $this->authorize('publish', $household);

        try {
            $this->workflow->publish($household, auth()->user());
            return back()->with('success', 'Household published successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function showMember(Household $household, HouseholdMember $member)
    {
        $this->authorize('view', $household);
        abort_unless((int)$member->household_id === (int)$household->id, 404);

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
            'surveyOptions' => SurveyOption::allGroupedForVue($lgId),
        ]);
    }

    public function markDeceased(Request $request, Household $household, \App\Models\HouseholdMember $member): \Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('households.mark_deceased'), 403);
        $this->authorize('view', $household);
        abort_unless((int)$member->household_id === (int)$household->id, 404);

        $data = $request->validate([
            'death_date'    => 'nullable|date_format:Y-m-d',
            'death_cause'   => 'nullable|string|max:100',
            'death_remarks' => 'nullable|string|max:500',
        ]);

        $member->update([
            'is_deceased'   => true,
            'death_date'    => $data['death_date']    ?? null,
            'death_cause'   => $data['death_cause']   ?? null,
            'death_remarks' => $data['death_remarks'] ?? null,
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($member)
            ->withProperties(['household_id' => $household->id, 'member_name' => $member->full_name])
            ->log('member_marked_deceased');

        return back()->with('success', $member->full_name . ' लाई मृत्यु भएको चिह्नित गरियो।');
    }

    public function unmarkDeceased(Household $household, \App\Models\HouseholdMember $member): \Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('households.mark_deceased'), 403);
        $this->authorize('view', $household);
        abort_unless((int)$member->household_id === (int)$household->id, 404);

        $member->update([
            'is_deceased'   => false,
            'death_date'    => null,
            'death_cause'   => null,
            'death_remarks' => null,
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($member)
            ->withProperties(['household_id' => $household->id, 'member_name' => $member->full_name])
            ->log('member_unmark_deceased');

        return back()->with('success', $member->full_name . ' को मृत्यु स्थिति हटाइयो।');
    }

    public function preview(Household $household)
    {
        $this->authorize('view', $household);

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
            'canEdit'   => $user->hasPermission('households.edit') && ($user->isAdmin() || (in_array($household->status, ['draft','rejected']) && ($user->isReviewer() || $household->enumerator_id === $user->id))),
            'canSubmit' => $user->hasPermission('households.submit') && in_array($household->status, ['draft', 'rejected']) && ($user->isAdmin() || $user->isReviewer() || $household->enumerator_id === $user->id),
        ]);
    }

}
