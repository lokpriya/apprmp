<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EditRequest;
use App\Models\Institution;
use App\Models\InstitutionType;
use App\Models\Settlement;
use App\Models\SurveyOption;
use App\Models\Ward;
use App\Services\CompletenessScoreService;
use App\Services\DuplicateDetectionService;
use App\Services\InstitutionService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstitutionController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $lgId = $user->local_government_id;

        $query = Institution::with(['ward', 'settlement', 'institutionType', 'enumerator', 'infrastructure'])->latest();

        if ($user->isEnumerator()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds)->where('enumerator_id', $user->id);
        } elseif ($user->isReviewer() || $user->isWardAdmin()) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $query->whereIn('ward_id', $wardIds);
        } elseif ($user->isAdmin()) {
            $query->whereHas('ward', fn($q) => $q->where('local_government_id', $lgId));
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
                'institutionType'  => $i->institutionType ? ['name_np' => $i->institutionType->name_np] : null,
                'ownership_type'   => $i->ownership_type,
                'status'           => $i->status,
                'enumerator'       => $i->enumerator ? ['name' => $i->enumerator->name] : null,
                'can_edit'         => $user->hasPermission('institutions.edit') && ($user->isAdmin() || ($user->isWardAdmin() && in_array($i->ward_id, $user->getAssignedWardIds())) || (in_array($i->status, ['draft','rejected','submitted']) && ($user->isReviewer() || $i->enumerator_id === $user->id))),
                'can_submit'       => $user->hasPermission('institutions.submit') && in_array($i->status, ['draft', 'rejected']) && ($user->isAdmin() || $user->isWardAdmin() || $user->isReviewer() || $i->enumerator_id === $user->id),
                'completeness'     => CompletenessScoreService::institution($i),
            ]);

        return Inertia::render('Institutions/Index', [
            'institutions' => $institutions,
            'wards'        => Ward::where('local_government_id', $lgId)->orderBy('ward_number')->get(['id','ward_number']),
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
            'wards'            => Ward::where('local_government_id', $lgId)
                                      ->orderBy('ward_number')
                                      ->get(['id', 'ward_number']),
            'settlements'      => Settlement::whereIn('ward_id', $wardIds)
                                            ->orderBy('name_np')
                                            ->get(['id', 'ward_id', 'name_np']),
            'institutionTypes' => InstitutionType::where('is_active', true)
                                                 ->orderBy('sort_order')
                                                 ->get(['id', 'name_np', 'name_en', 'slug', 'has_education', 'has_health']),
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
            'initialWardId'    => $user->isEnumerator()
                                       ? $user->assignedWards()->first()?->id
                                       : null,
            'mapConfig'        => \App\Models\LocalGovernment::getCached($lgId)?->map_config ?? [],
        ]);
    }

    public function show(Institution $institution)
    {
        $this->authorize('view', $institution);

        $institution->load([
            'ward', 'settlement', 'institutionType', 'enumerator',
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

        // Laravel serializes relations as snake_case; add camelCase aliases for Vue
        $institutionData = $institution->toArray();
        $institutionData['institutionType']  = $institutionData['institution_type']  ?? null;
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
            'wards'            => Ward::where('local_government_id', $lgId)
                                      ->orderBy('ward_number')
                                      ->get(['id', 'ward_number']),
            'settlements'      => Settlement::whereIn('ward_id', $wardIds)
                                            ->orderBy('name_np')
                                            ->get(['id', 'ward_id', 'name_np']),
            'institutionTypes' => InstitutionType::where('is_active', true)
                                                 ->orderBy('sort_order')
                                                 ->get(['id', 'name_np', 'name_en', 'slug', 'has_education', 'has_health']),
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
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'name_en' => 'required|string|max:200',
        ]);

        $user = auth()->user();
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

        try {
            $this->workflow->submit($institution, auth()->user());
            return redirect()->route('institutions.show', $institution)
                ->with('success', 'Institution submitted for ward review.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
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
