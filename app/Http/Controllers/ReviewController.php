<?php

namespace App\Http\Controllers;

use App\Support\InertiaPaginator;
use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Services\CompletenessScoreService;
use App\Services\DataQualityService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    private function scopedWardIds(): array
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return \App\Models\Ward::where('local_government_id', $user->local_government_id)
                ->pluck('id')->toArray();
        }
        return $user->assignedWards()->pluck('wards.id')->toArray();
    }

    public function index()
    {
        $wardIds = $this->scopedWardIds();

        $pendingHouseholds   = Household::whereIn('ward_id', $wardIds)->where('status', 'submitted')->count();
        $pendingInstitutions = Institution::whereIn('ward_id', $wardIds)->where('status', 'submitted')->count();
        $pendingBusinesses   = MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'submitted')->count();

        return Inertia::render('Review/Index', compact('pendingHouseholds', 'pendingInstitutions', 'pendingBusinesses'));
    }

    public function households(Request $request)
    {
        $wardIds           = $this->scopedWardIds();
        $filterWard        = $request->input('ward_id');
        $filterMissingGps  = $request->boolean('missing_gps');
        $filterNoMembers   = $request->boolean('no_members');
        $filterLowComplete = $request->boolean('low_completeness');
        $filterDataFlags   = $request->boolean('data_flags');

        // Base (no quality filters) for counts shown on chips
        $base = Household::whereIn('ward_id', $wardIds)->where('status', 'submitted');
        if ($filterWard && in_array((int)$filterWard, $wardIds)) {
            $base->where('ward_id', $filterWard);
        }

        $flaggedBase = clone $base;
        DataQualityService::applyFlaggedScope($flaggedBase);

        $qualityCounts = [
            'missing_gps'      => (clone $base)
                ->where(fn($q) => $q->whereNull('gps_lat')->orWhereNull('gps_lng'))
                ->count(),
            'no_members'       => (clone $base)->whereDoesntHave('members')->count(),
            'low_completeness' => (clone $base)
                ->where(fn($q) => $q
                    ->whereDoesntHave('housing')
                    ->orWhereDoesntHave('utilities')
                    ->orWhereNull('gps_lat')
                    ->orWhereNull('survey_date')
                    ->orWhereNull('respondent_name')
                )->count(),
            'data_flags'       => $flaggedBase->count(),
        ];

        $query = Household::with(['ward', 'settlement', 'enumerator', 'housing', 'utilities', 'economic', 'members'])
            ->whereIn('ward_id', $wardIds)
            ->where('status', 'submitted');

        if ($filterWard && in_array((int)$filterWard, $wardIds)) {
            $query->where('ward_id', $filterWard);
        }
        if ($filterMissingGps) {
            $query->where(fn($q) => $q->whereNull('gps_lat')->orWhereNull('gps_lng'));
        }
        if ($filterNoMembers) {
            $query->whereDoesntHave('members');
        }
        if ($filterLowComplete) {
            $query->where(fn($q) => $q
                ->whereDoesntHave('housing')
                ->orWhereDoesntHave('utilities')
                ->orWhereNull('gps_lat')
                ->orWhereNull('survey_date')
                ->orWhereNull('respondent_name')
            );
        }
        if ($filterDataFlags) {
            DataQualityService::applyFlaggedScope($query);
        }

        $households = $query->latest('submitted_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn($hh) => [
                'id'              => $hh->id,
                'unique_code'     => $hh->unique_code,
                'respondent_name' => $hh->respondent_name,
                'ward'            => $hh->ward ? ['ward_number' => $hh->ward->ward_number] : null,
                'settlement'      => $hh->settlement ? ['name_np' => $hh->settlement->name_np] : null,
                'enumerator'      => $hh->enumerator ? ['name' => $hh->enumerator->name] : null,
                'submitted_at'    => $hh->submitted_at?->diffForHumans(),
                'completeness'    => CompletenessScoreService::score($hh),
                'flags'           => DataQualityService::flags($hh),
                'member_count'    => $hh->members->where('is_deceased', false)->count(),
            ]);

        $wards = \App\Models\Ward::whereIn('id', $wardIds)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number']);

        $filters = [
            'ward_id'          => $filterWard,
            'missing_gps'      => $filterMissingGps,
            'no_members'       => $filterNoMembers,
            'low_completeness' => $filterLowComplete,
            'data_flags'       => $filterDataFlags,
        ];

        return Inertia::render('Review/Households', [
            'households'    => InertiaPaginator::format($households),
            'wards'         => $wards,
            'filters'       => $filters,
            'qualityCounts' => $qualityCounts,
        ]);
    }

    public function institutions(Request $request)
    {
        $wardIds    = $this->scopedWardIds();
        $filterWard = $request->input('ward_id');

        $instTypeLabels = \App\Models\SurveyOption::where('category', 'institution_type')->pluck('label_ne', 'value');
        $query = Institution::with(['ward', 'settlement', 'enumerator', 'infrastructure'])
            ->whereIn('ward_id', $wardIds)
            ->where('status', 'submitted');

        if ($filterWard && in_array((int)$filterWard, $wardIds)) {
            $query->where('ward_id', $filterWard);
        }

        $institutions = $query->latest('submitted_at')
            ->paginate(20)
            ->through(fn($i) => [
                'id'              => $i->id,
                'unique_code'     => $i->unique_code,
                'name_np'         => $i->name_np,
                'name_en'         => $i->name_en,
                'ward'            => $i->ward ? ['ward_number' => $i->ward->ward_number] : null,
                'institutionType' => $i->institution_type ? ['name_np' => $instTypeLabels[$i->institution_type] ?? $i->institution_type] : null,
                'ownership_type'  => $i->ownership_type,
                'enumerator'      => $i->enumerator ? ['name' => $i->enumerator->name] : null,
                'submitted_at'    => $i->submitted_at?->diffForHumans(),
                'completeness'    => CompletenessScoreService::institution($i),
            ]);

        $wards   = \App\Models\Ward::whereIn('id', $wardIds)->orderBy('ward_number')->get(['id', 'ward_number']);
        $filters = ['ward_id' => $filterWard];

        return Inertia::render('Review/Institutions', [
            'institutions' => InertiaPaginator::format($institutions),
            'wards'        => $wards,
            'filters'      => $filters,
        ]);
    }

    public function approveHousehold(Request $request, Household $household)
    {
        $this->authorizeWardAccess($household->ward_id);
        $comment = $request->input('comment');

        try {
            $this->workflow->wardApprove($household, auth()->user(), $comment);
            return back()->with('success', "Household {$household->unique_code} approved.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectHousehold(Request $request, Household $household)
    {
        $this->authorizeWardAccess($household->ward_id);
        $request->validate(['comment' => 'required|string|max:500']);

        try {
            $this->workflow->reject($household, auth()->user(), $request->comment);
            return back()->with('success', "Household {$household->unique_code} rejected.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function escalateHousehold(Request $request, Household $household)
    {
        $this->authorizeWardAccess($household->ward_id);

        try {
            $this->workflow->escalate($household, auth()->user(), $request->input('comment'));
            return back()->with('success', "Household {$household->unique_code} escalated to admin.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approveInstitution(Request $request, Institution $institution)
    {
        $this->authorizeWardAccess($institution->ward_id);

        try {
            $this->workflow->wardApprove($institution, auth()->user(), $request->input('comment'));
            return back()->with('success', "Institution {$institution->unique_code} approved.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectInstitution(Request $request, Institution $institution)
    {
        $this->authorizeWardAccess($institution->ward_id);
        $request->validate(['comment' => 'required|string|max:500']);

        try {
            $this->workflow->reject($institution, auth()->user(), $request->comment);
            return back()->with('success', "Institution {$institution->unique_code} rejected.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function escalateInstitution(Request $request, Institution $institution)
    {
        $this->authorizeWardAccess($institution->ward_id);

        try {
            $this->workflow->escalate($institution, auth()->user(), $request->input('comment'));
            return back()->with('success', "Institution {$institution->unique_code} escalated to admin.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function businesses(Request $request)
    {
        $wardIds    = $this->scopedWardIds();
        $filterWard = $request->input('ward_id');

        $query = MemberBusiness::with(['member', 'household', 'ward', 'settlement', 'enumerator'])
            ->whereIn('ward_id', $wardIds)
            ->where('status', 'submitted');

        if ($filterWard && in_array((int)$filterWard, $wardIds)) {
            $query->where('ward_id', $filterWard);
        }

        $businesses = $query->latest('submitted_at')
            ->paginate(20)
            ->through(fn($b) => [
                'id'            => $b->id,
                'unique_code'   => $b->unique_code,
                'business_name' => $b->business_name,
                'business_type' => $b->business_type,
                'business_field'=> $b->business_field,
                'business_location' => $b->business_location,
                'is_registered' => $b->is_registered,
                'contact_number'=> $b->contact_number,
                'member'        => $b->member  ? ['full_name' => $b->member->full_name]        : null,
                'household'     => $b->household ? ['unique_code' => $b->household->unique_code] : null,
                'ward'          => $b->ward     ? ['ward_number' => $b->ward->ward_number]      : null,
                'settlement'    => $b->settlement ? ['name_np'   => $b->settlement->name_np]    : null,
                'enumerator'    => $b->enumerator ? ['name'      => $b->enumerator->name]       : null,
                'submitted_at'  => $b->submitted_at?->diffForHumans(),
                'completeness'  => CompletenessScoreService::business($b),
            ]);

        $wards   = \App\Models\Ward::whereIn('id', $wardIds)->orderBy('ward_number')->get(['id', 'ward_number']);
        $filters = ['ward_id' => $filterWard];

        return Inertia::render('Review/Businesses', [
            'businesses' => InertiaPaginator::format($businesses),
            'wards'      => $wards,
            'filters'    => $filters,
        ]);
    }

    public function approveBusiness(Request $request, MemberBusiness $business)
    {
        $this->authorizeWardAccess($business->ward_id);

        try {
            $this->workflow->wardApprove($business, auth()->user(), $request->input('comment'));
            return back()->with('success', "Business {$business->unique_code} approved.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectBusiness(Request $request, MemberBusiness $business)
    {
        $this->authorizeWardAccess($business->ward_id);
        $request->validate(['comment' => 'required|string|max:500']);

        try {
            $this->workflow->reject($business, auth()->user(), $request->comment);
            return back()->with('success', "Business {$business->unique_code} rejected.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function escalateBusiness(Request $request, MemberBusiness $business)
    {
        $this->authorizeWardAccess($business->ward_id);

        try {
            $this->workflow->escalate($business, auth()->user(), $request->input('comment'));
            return back()->with('success', "Business {$business->unique_code} escalated to admin.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function bulkApproveInstitutions(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user    = auth()->user();
        $wardIds = $this->scopedWardIds();

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

    public function bulkRejectInstitutions(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'reason' => 'required|string|max:500',
        ]);
        $user    = auth()->user();
        $wardIds = $this->scopedWardIds();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $inst = Institution::find($id);
            if (!$inst || $inst->status !== 'submitted') { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($inst->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->reject($inst, $user, $request->reason); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} संस्था अस्वीकार भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    public function bulkApproveBusinesses(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user    = auth()->user();
        $wardIds = $this->scopedWardIds();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $biz = MemberBusiness::find($id);
            if (!$biz || $biz->status !== 'submitted') { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($biz->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->wardApprove($biz, $user); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} व्यवसाय अनुमोदन भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    public function bulkRejectBusinesses(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'reason' => 'required|string|max:500',
        ]);
        $user    = auth()->user();
        $wardIds = $this->scopedWardIds();

        $count = 0; $skipped = 0;
        foreach ($request->ids as $id) {
            $biz = MemberBusiness::find($id);
            if (!$biz || $biz->status !== 'submitted') { $skipped++; continue; }
            if (!$user->isAdmin() && !in_array($biz->ward_id, $wardIds)) { $skipped++; continue; }
            try { $this->workflow->reject($biz, $user, $request->reason); $count++; }
            catch (\Throwable) { $skipped++; }
        }

        return back()->with('success', "{$count} व्यवसाय अस्वीकार भयो।" . ($skipped ? " ({$skipped} छोडियो)" : ''));
    }

    private function authorizeWardAccess(int $wardId): void
    {
        abort_unless(in_array($wardId, $this->scopedWardIds()), 403);
    }
}
