<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Services\CompletenessScoreService;
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
        $wardIds = $this->scopedWardIds();

        $households = Household::with(['ward', 'settlement', 'enumerator', 'housing', 'utilities', 'economic', 'members'])
            ->whereIn('ward_id', $wardIds)
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->paginate(20)
            ->through(fn($hh) => [
                'id'             => $hh->id,
                'unique_code'    => $hh->unique_code,
                'respondent_name'=> $hh->respondent_name,
                'ward'           => $hh->ward ? ['ward_number' => $hh->ward->ward_number] : null,
                'settlement'     => $hh->settlement ? ['name_np' => $hh->settlement->name_np] : null,
                'enumerator'     => $hh->enumerator ? ['name' => $hh->enumerator->name] : null,
                'submitted_at'   => $hh->submitted_at?->diffForHumans(),
                'completeness'   => CompletenessScoreService::score($hh),
            ]);

        return Inertia::render('Review/Households', compact('households'));
    }

    public function institutions(Request $request)
    {
        $wardIds = $this->scopedWardIds();

        $institutions = Institution::with(['ward', 'settlement', 'institutionType', 'enumerator', 'infrastructure'])
            ->whereIn('ward_id', $wardIds)
            ->where('status', 'submitted')
            ->latest('submitted_at')
            ->paginate(20)
            ->through(fn($i) => [
                'id'              => $i->id,
                'unique_code'     => $i->unique_code,
                'name_np'         => $i->name_np,
                'name_en'         => $i->name_en,
                'ward'            => $i->ward ? ['ward_number' => $i->ward->ward_number] : null,
                'institutionType' => $i->institutionType ? ['name_np' => $i->institutionType->name_np] : null,
                'ownership_type'  => $i->ownership_type,
                'enumerator'      => $i->enumerator ? ['name' => $i->enumerator->name] : null,
                'submitted_at'    => $i->submitted_at?->diffForHumans(),
                'completeness'    => CompletenessScoreService::institution($i),
            ]);

        return Inertia::render('Review/Institutions', compact('institutions'));
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
        $wardIds = $this->scopedWardIds();

        $businesses = MemberBusiness::with(['member', 'household', 'ward', 'settlement', 'enumerator'])
            ->whereIn('ward_id', $wardIds)
            ->where('status', 'submitted')
            ->latest('submitted_at')
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

        return Inertia::render('Review/Businesses', compact('businesses'));
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

    private function authorizeWardAccess(int $wardId): void
    {
        abort_unless(in_array($wardId, $this->scopedWardIds()), 403);
    }
}
