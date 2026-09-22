<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EditRequest;
use App\Models\Institution;
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\Ward;
use App\Services\CompletenessScoreService;
use App\Services\DuplicateDetectionService;
use App\Services\InstitutionService;
use App\Services\WorkflowService;
use App\Support\ScopesWardAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InstitutionController extends Controller
{
    use ScopesWardAccess;

    public function __construct(private WorkflowService $workflow) {}

    /**
     * Valid institution_type slugs — global options plus any LG-specific ones.
     * The InstitutionType foreign key was dropped in favour of survey_options
     * slugs, so this is now the only thing keeping arbitrary strings out of the
     * column and, in turn, out of the report groupings.
     */
    private function institutionTypeRule(?int $lgId): array
    {
        $allowed = SurveyOption::query()
            ->where('category', 'institution_type')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('local_government_id')->orWhere('local_government_id', $lgId))
            ->pluck('value')
            ->all();

        return ['nullable', 'string', 'max:100', Rule::in($allowed)];
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $lgId = $user->local_government_id;

        $typeLabels = SurveyOption::where('category', 'institution_type')->pluck('label_ne', 'value');
        $query = Institution::with(['ward', 'settlement', 'enumerator', 'infrastructure'])->latest();

        if ($user->isEnumerator()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds)->where('enumerator_id', $user->id);
        } elseif ($user->isReviewer() || $user->isWardAdmin()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds);
        } elseif ($user->isAdmin()) {
            $query->whereHas('ward', fn($q) => $q->where('local_government_id', $lgId));
        } else {
            // Fail closed — see the matching branch in HouseholdController::index.
            $query->whereRaw('1 = 0');
        }

        if ($s = $request->search) {
            $query->where(fn($q) => $q->where('name_np','like',"%$s%")->orWhere('name_en','like',"%$s%")->orWhere('unique_code','like',"%$s%"));
        }
        if ($request->ward_id) $query->where('ward_id', $request->ward_id);
        if ($request->status)  $query->where('status', $request->status);

        $institutions = $query->paginate(20)->withQueryString()
            ->through(fn($i) => [
                'id'               => $i->id,
                'unique_code'      => $i->unique_code,
                'name_np'          => $i->name_np,
                'name_en'          => $i->name_en,
                'ward'             => $i->ward ? ['ward_number' => $i->ward->ward_number] : null,
                'institutionType'  => $i->institution_type ? ['name_np' => $typeLabels[$i->institution_type] ?? $i->institution_type] : null,
                'ownership_type'   => $i->ownership_type,
                'status'           => $i->status,
                'enumerator'       => $i->enumerator ? ['name' => $i->enumerator->name] : null,
                'can_edit'         => $user->hasPermission('institutions.edit') && ($user->isAdmin() || ($user->isWardAdmin() && in_array($i->ward_id, $user->getAssignedWardIds())) || (in_array($i->status, ['draft','rejected','submitted']) && ($user->isReviewer() || $i->enumerator_id === $user->id))),
                'can_submit'       => $user->hasPermission('institutions.submit') && in_array($i->status, ['draft', 'rejected']) && ($user->isAdmin() || $user->isWardAdmin() || $user->isReviewer() || $i->enumerator_id === $user->id),
                'completeness'     => CompletenessScoreService::institution($i),
            ]);

        return Inertia::render('Institutions/Index', [
            'institutions' => $institutions,
            'wards'        => $this->selectableWards(),
            'filters'      => $request->only('search','ward_id','status'),
        ]);
    }

    public function create()
    {
        $user    = auth()->user();
        $lgId    = $user->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id');

        return Inertia::render('Institutions/Form', [
            'institution'      => null,
            'wards'            => $this->selectableWards(),
            'settlements'      => Settlement::whereIn('ward_id', $wardIds)
                                            ->orderBy('name_np')
                                            ->get(['id', 'ward_id', 'name_np']),
            'institutionTypes' => SurveyOption::where('category', 'institution_type')
                                                 ->where('is_active', true)
                                                 ->orderBy('sort_order')
                                                 ->get(['value', 'label_ne', 'label_en']),
            'buildingTypes'    => SurveyOption::where('category', 'inst_building_type')
                                                ->orderBy('sort_order')->orderBy('label_ne')
                                                ->get(['value', 'label_ne', 'label_en']),
            'surveyOpts'       => SurveyOption::where(fn($q) =>
                                                    $q->where('category', 'like', 'inst_%')
                                                      ->orWhereIn('category', ['ethnicity', 'major_occupation', 'education_level'])
                                                )
                                                ->where('is_active', true)
                                                ->orderBy('category')->orderBy('sort_order')
                                                ->get(['category', 'value', 'label_ne'])
                                                ->groupBy('category'),
            // Anyone with exactly one ward gets it filled in, not only enumerators.
            'initialWardId'    => $this->defaultWardId(),
            'mapConfig'        => \App\Models\LocalGovernment::getCached($lgId)?->map_config ?? [],
            'canEditBuildings'  => $user->isAdmin(),
        ]);
    }

    public function show(Institution $institution)
    {
        $this->authorize('view', $institution);

        $institution->load([
            'ward', 'settlement', 'enumerator',
            'infrastructure', 'healthDetail', 'services', 'staff', 'education',
            'submissionLogs.actor', 'fieldVerification.verifier',
        ]);

        $user = auth()->user();

        $surveyOpts = SurveyOption::where(fn($q) =>
                                        $q->where('category', 'like', 'inst_%')
                                          ->orWhereIn('category', ['ethnicity', 'major_occupation', 'education_level'])
                                    )
                                    ->where('is_active', true)
                                    ->orderBy('category')->orderBy('sort_order')
                                    ->get(['category', 'value', 'label_ne'])
                                    ->groupBy('category');

        // Build institutionType alias for Vue (slug→label lookup)
        $typeLabels = SurveyOption::where('category', 'institution_type')->get()->keyBy('value');
        $institutionData = $institution->toArray();
        $t = $typeLabels[$institution->institution_type] ?? null;
        $institutionData['institutionType'] = $t ? ['name_np' => $t->label_ne, 'name_en' => $t->label_en, 'slug' => $institution->institution_type] : null;
        $institutionData['healthDetail']     = $institutionData['health_detail']     ?? null;
        $institutionData['submissionLogs']   = $institutionData['submission_logs']   ?? [];

        $fv = $institution->fieldVerification;
        return Inertia::render('Institutions/Show', [
            'institution' => $institutionData,
            'surveyOpts'  => $surveyOpts,
            'canEdit'        => $user->hasPermission('institutions.edit')
                                  && ($user->isAdmin()
                                      || ($user->isWardAdmin() && in_array($institution->ward_id, $user->getAssignedWardIds()))
                                      || (in_array($institution->status, ['draft','rejected','submitted']) && ($user->isReviewer() || $institution->enumerator_id === $user->id))),
            'canSubmit'      => $user->hasPermission('institutions.submit') && in_array($institution->status, ['draft', 'rejected']) && ($user->isAdmin() || $user->isWardAdmin() || $user->isReviewer() || $institution->enumerator_id === $user->id),
            'canPublish'     => $user->hasPermission('institutions.publish') && in_array($institution->status, ['ward_approved', 'escalated'])
                                  && ($user->isAdmin() || ($user->isWardAdmin() && in_array($institution->ward_id, $user->getAssignedWardIds()))),
            'canApprove'     => $user->hasPermission('institutions.approve')
                                  && (($user->isAdmin() && $institution->status === 'ward_approved')
                                      || (($user->isWardAdmin() || $user->isReviewer()) && $institution->status === 'submitted' && in_array($institution->ward_id, $user->getAssignedWardIds()))),
            'canReject'      => $user->hasPermission('institutions.reject')
                                  && ($user->isAdmin()
                                      ? in_array($institution->status, ['ward_approved', 'escalated', 'published'])
                                      : in_array($institution->status, ['submitted', 'ward_approved']) && in_array($institution->ward_id, $user->getAssignedWardIds())),
            'canRequestEdit' => $user->isReviewer() && $institution->status === 'published'
                                  && !EditRequest::where('requestable_type', Institution::class)
                                                 ->where('requestable_id', $institution->id)
                                                 ->where('status', 'pending')
                                                 ->exists(),
            'hasPendingEditRequest' => $user->isReviewer() && EditRequest::where('requestable_type', Institution::class)
                                                 ->where('requestable_id', $institution->id)
                                                 ->where('status', 'pending')
                                                 ->exists(),
            'canVerify'      => ($user->isReviewer() || $user->isWardAdmin() || $user->isAdmin()) && $institution->status !== 'draft',
            'fieldVerification' => $fv ? [
                'visited_at'  => $fv->visited_at?->format('Y-m-d'),
                'is_verified' => $fv->is_verified,
                'notes'       => $fv->notes,
                'verifier'    => $fv->verifier ? ['name' => $fv->verifier->name] : null,
                'created_at'  => $fv->created_at?->format('Y-m-d'),
            ] : null,
        ]);
    }

    public function edit(Institution $institution)
    {
        $this->authorize('update', $institution);

        $user    = auth()->user();
        $lgId    = $user->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id');

        $institution->load(['infrastructure', 'staff', 'services', 'education', 'healthDetail']);

        // Ensure date fields are plain YYYY-MM-DD strings (not Carbon objects)
        $institutionData = $institution->toArray();
        $institutionData['established_date'] = $institution->established_date?->format('Y-m-d');
        $institutionData['survey_date']      = $institution->survey_date?->format('Y-m-d');

        return Inertia::render('Institutions/Form', [
            'institution'      => $institutionData,
            // Keep the record's own ward selectable even if assignments changed.
            'wards'            => $this->selectableWards($institution->ward_id),
            'settlements'      => Settlement::whereIn('ward_id', $wardIds)
                                            ->orderBy('name_np')
                                            ->get(['id', 'ward_id', 'name_np']),
            'institutionTypes' => SurveyOption::where('category', 'institution_type')
                                                 ->where('is_active', true)
                                                 ->orderBy('sort_order')
                                                 ->get(['value', 'label_ne', 'label_en']),
            'buildingTypes'    => SurveyOption::where('category', 'inst_building_type')
                                                ->orderBy('sort_order')->orderBy('label_ne')
                                                ->get(['value', 'label_ne', 'label_en']),
            'surveyOpts'       => SurveyOption::where(fn($q) =>
                                                    $q->where('category', 'like', 'inst_%')
                                                      ->orWhereIn('category', ['ethnicity', 'major_occupation', 'education_level'])
                                                )
                                                ->where('is_active', true)
                                                ->orderBy('category')->orderBy('sort_order')
                                                ->get(['category', 'value', 'label_ne'])
                                                ->groupBy('category'),
            'initialWardId'    => null,
            'mapConfig'        => \App\Models\LocalGovernment::getCached($lgId)?->map_config ?? [],
            'canEditBuildings'  => $user->isAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            // See ScopesWardAccess: exists:wards,id let a record be filed into a
            // ward the creator could not afterwards open.
            'ward_id'          => $this->wardRule(),
            'name_en'          => 'required|string|max:200',
            'institution_type' => $this->institutionTypeRule($user->local_government_id),
        ], $this->wardRuleMessages());

        if (!$user->isAdmin()) {
            $lg = \App\Models\LocalGovernment::getCached($user->local_government_id);
            if ($lg?->isSurveyFrozen()) {
                abort(403, 'सर्भेक्षण अवधि बन्द छ।');
            }
        }

        $data = $request->except('duplicate_confirmed');
        $data['survey_data'] = $data['survey_data'] ?? $this->buildSurveyData($data);

        // Duplicate detection — skip if user has already confirmed
        if (!$request->boolean('duplicate_confirmed')) {
            $dupResult = app(DuplicateDetectionService::class)->checkInstitution($data);
            if ($dupResult['found']) {
                return back()->withErrors([
                    'duplicate_warning' => json_encode([
                        'matches'    => $dupResult['matches'],
                        'hard_block' => $dupResult['hard_block'],
                    ]),
                ])->withInput();
            }
        }

        $institution = app(InstitutionService::class)->create($data, auth()->user());

        return redirect()->route('institutions.show', $institution)
            ->with('success', 'संस्था विवरण सफलतापूर्वक सुरक्षित भयो।');
    }

    public function update(Request $request, Institution $institution)
    {
        $this->authorize('update', $institution);

        $user = auth()->user();

        $request->validate([
            // ward_id was not validated here at all, so an edit could move a
            // record into any ward. Its current ward stays permitted.
            'ward_id'          => $this->wardRule($institution->ward_id),
            'institution_type' => $this->institutionTypeRule($user->local_government_id),
        ], $this->wardRuleMessages());

        if (!$user->isAdmin()) {
            $lg = \App\Models\LocalGovernment::getCached($user->local_government_id);
            if ($lg?->isSurveyFrozen()) {
                abort(403, 'सर्भेक्षण अवधि बन्द छ।');
            }
        }

        $wasPublished = $institution->status === 'published';
        $oldValues    = $wasPublished ? $institution->toArray() : null;

        $data = $request->all();
        $data['survey_data'] = $data['survey_data'] ?? $this->buildSurveyData($data);

        app(InstitutionService::class)->update($institution, $data);

        if ($wasPublished) {
            AuditLog::create([
                'user_id'        => auth()->id(),
                'event_type'     => 'published_edit',
                'auditable_type' => Institution::class,
                'auditable_id'   => $institution->id,
                'old_values'     => $oldValues,
                'new_values'     => $institution->fresh()->toArray(),
                'ip_address'     => $request->ip(),
                'user_agent'     => $request->userAgent(),
            ]);
        }

        return redirect()->route('institutions.show', $institution)
            ->with('success', 'संस्था विवरण अद्यावधिक भयो।');
    }

    public function destroy(Institution $institution)
    {
        $this->authorize('delete', $institution);

        if (!$institution->isDraft()) {
            return back()->with('error', 'Only draft institutions can be deleted.');
        }

        $institution->delete();

        return redirect()->route('institutions.index')->with('success', 'Institution record deleted.');
    }

    public function submit(Request $request, Institution $institution)
    {
        $this->authorize('update', $institution);

        $missing = $this->validateForSubmission($institution);
        if ($missing) {
            return back()->with('error', 'पेस गर्न सकिएन — आवश्यक विवरण खाली छ: ' . $missing);
        }

        try {
            $this->workflow->submit($institution, auth()->user());
            return redirect()->route('institutions.show', $institution)
                ->with('success', 'Institution submitted for ward review.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function validateForSubmission(Institution $institution): ?string
    {
        $errors = [];

        if (!$institution->institution_type)                           $errors[] = 'संस्थाको प्रकार';
        if (!$institution->name_np && !$institution->name_en)         $errors[] = 'संस्थाको नाम';
        if (!$institution->ward_id)                                   $errors[] = 'वडा नं.';
        if (!$institution->respondent_name)                           $errors[] = 'उत्तरदाताको नाम';
        if (!$institution->survey_date)                               $errors[] = 'सर्वेक्षण मिति';
        if (!$institution->gps_lat || !$institution->gps_lng)        $errors[] = 'GPS स्थान';

        if (empty($errors)) return null;
        return implode(', ', array_slice($errors, 0, 4))
            . (count($errors) > 4 ? ' र अन्य ' . (count($errors) - 4) . ' विवरण' : '');
    }

    public function publish(Request $request, Institution $institution)
    {
        $this->authorize('publish', $institution);

        try {
            $this->workflow->publish($institution, auth()->user());
            return back()->with('success', 'Institution published successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function bulkSubmit(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user    = auth()->user();
        $wardIds = $user->isAdmin() ? null : $user->assignedWards()->pluck('wards.id')->toArray();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $inst = Institution::find($id);
            if (!$inst || $inst->status !== 'draft') { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($inst->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->submit($inst, $user); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} संस्था पेस भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    public function bulkApprove(Request $request)
    {
        abort_unless(auth()->user()->isReviewer() || auth()->user()->isWardAdmin() || auth()->user()->isAdmin(), 403);
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user    = auth()->user();
        $wardIds = $user->isAdmin() ? null : $user->assignedWards()->pluck('wards.id')->toArray();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $inst = Institution::find($id);
            if (!$inst || $inst->status !== 'submitted') { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($inst->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->wardApprove($inst, $user); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} संस्था अनुमोदन भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
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
            $inst = Institution::find($id);
            if (!$inst || !in_array($inst->status, ['submitted', 'ward_approved'])) { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($inst->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->reject($inst, $user, $request->reason); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} संस्था अस्वीकार भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    private function buildSurveyData(array $data): ?array
    {
        // When the Vue/Inertia form sends fields flat (no survey_data wrapper),
        // collect the known survey_data sub-keys from the top-level payload.
        $keys = [
            'contact_person', 'executive_committee', 'typespecific',
            'cooperative', 'financial', 'ngo', 'govt_office', 'religious',
            'forest', 'sports', 'market', 'industry', 'media',
            'tourism', 'political', 'community',
        ];
        $surveyData = [];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $surveyData[$key] = $data[$key];
            }
        }
        return $surveyData ?: null;
    }

}
