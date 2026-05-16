<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Models\Ward;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublishQueueController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user    = auth()->user();
        $wardIds = $user->isAdmin()
            ? Ward::where('local_government_id', $user->local_government_id)->pluck('id')
            : $user->assignedWards()->pluck('wards.id');

        $households = Household::with(['ward', 'settlement', 'enumerator', 'wardReviewer'])
            ->whereIn('ward_id', $wardIds)
            ->whereIn('status', ['ward_approved', 'escalated'])
            ->latest('ward_reviewed_at')
            ->paginate(20, ['*'], 'hh_page')
            ->through(fn($hh) => [
                'id'                  => $hh->id,
                'unique_code'         => $hh->unique_code,
                'respondent_name'     => $hh->respondent_name,
                'status'              => $hh->status,
                'ward'                => $hh->ward ? ['ward_number' => $hh->ward->ward_number] : null,
                'settlement'          => $hh->settlement ? ['name_np' => $hh->settlement->name_np] : null,
                'enumerator'          => $hh->enumerator ? ['name' => $hh->enumerator->name] : null,
                'ward_reviewer'       => $hh->wardReviewer ? ['name' => $hh->wardReviewer->name] : null,
                'ward_review_comment' => $hh->ward_review_comment,
                'ward_reviewed_at'    => $hh->ward_reviewed_at?->diffForHumans(),
                'submitted_at'        => $hh->submitted_at?->format('Y-m-d'),
            ]);

        $institutions = Institution::with(['ward', 'settlement', 'institutionType', 'enumerator', 'wardReviewer'])
            ->whereIn('ward_id', $wardIds)
            ->whereIn('status', ['ward_approved', 'escalated'])
            ->latest('ward_reviewed_at')
            ->paginate(20, ['*'], 'inst_page')
            ->through(fn($i) => [
                'id'                  => $i->id,
                'unique_code'         => $i->unique_code,
                'name_np'             => $i->name_np,
                'name_en'             => $i->name_en,
                'status'              => $i->status,
                'ward'                => $i->ward ? ['ward_number' => $i->ward->ward_number] : null,
                'institutionType'     => $i->institutionType ? ['name_np' => $i->institutionType->name_np] : null,
                'enumerator'          => $i->enumerator ? ['name' => $i->enumerator->name] : null,
                'ward_reviewer'       => $i->wardReviewer ? ['name' => $i->wardReviewer->name] : null,
                'ward_review_comment' => $i->ward_review_comment,
                'ward_reviewed_at'    => $i->ward_reviewed_at?->diffForHumans(),
            ]);

        $businesses = MemberBusiness::with(['member', 'household', 'ward', 'settlement', 'enumerator', 'wardReviewer'])
            ->whereIn('ward_id', $wardIds)
            ->whereIn('status', ['ward_approved', 'escalated'])
            ->latest('ward_reviewed_at')
            ->paginate(20, ['*'], 'biz_page')
            ->through(fn($b) => [
                'id'                  => $b->id,
                'unique_code'         => $b->unique_code,
                'business_name'       => $b->business_name,
                'business_type'       => $b->business_type,
                'business_field'      => $b->business_field,
                'business_location'   => $b->business_location,
                'is_registered'       => $b->is_registered,
                'contact_number'      => $b->contact_number,
                'status'              => $b->status,
                'member'              => $b->member    ? ['full_name'    => $b->member->full_name]        : null,
                'household'           => $b->household ? ['unique_code'  => $b->household->unique_code]   : null,
                'ward'                => $b->ward      ? ['ward_number'  => $b->ward->ward_number]        : null,
                'settlement'          => $b->settlement? ['name_np'      => $b->settlement->name_np]      : null,
                'enumerator'          => $b->enumerator? ['name'         => $b->enumerator->name]         : null,
                'ward_reviewer'       => $b->wardReviewer ? ['name'      => $b->wardReviewer->name]       : null,
                'ward_review_comment' => $b->ward_review_comment,
                'ward_reviewed_at'    => $b->ward_reviewed_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/PublishQueue', [
            'households'   => $households,
            'institutions' => $institutions,
            'businesses'   => $businesses,
            'tab'          => $request->input('tab', 'households'),
            'counts'       => [
                'households'   => $households->total(),
                'institutions' => $institutions->total(),
                'businesses'   => $businesses->total(),
            ],
        ]);
    }

    public function publishHousehold(Request $request, Household $household)
    {
        $this->authorize('publish', $household);

        $this->workflow->publish($household, auth()->user());
        return back()->with('success', "घरधुरी {$household->unique_code} प्रकाशित गरियो।");
    }

    public function rejectHousehold(Request $request, Household $household)
    {
        $this->authorize('reject', $household);
        $request->validate(['comment' => 'required|string|max:500']);

        $this->workflow->reject($household, auth()->user(), $request->comment);
        return back()->with('success', "घरधुरी {$household->unique_code} अस्वीकृत गरियो।");
    }

    public function publishInstitution(Request $request, Institution $institution)
    {
        $this->authorize('publish', $institution);

        $this->workflow->publish($institution, auth()->user());
        return back()->with('success', "संस्था {$institution->unique_code} प्रकाशित गरियो।");
    }

    public function rejectInstitution(Request $request, Institution $institution)
    {
        $this->authorize('reject', $institution);
        $request->validate(['comment' => 'required|string|max:500']);

        $this->workflow->reject($institution, auth()->user(), $request->comment);
        return back()->with('success', "संस्था {$institution->unique_code} अस्वीकृत गरियो।");
    }

    public function publishBusiness(Request $request, MemberBusiness $business)
    {
        $this->authorize('publish', $business);

        $this->workflow->publish($business, auth()->user());
        return back()->with('success', "व्यवसाय {$business->unique_code} प्रकाशित गरियो।");
    }

    public function rejectBusiness(Request $request, MemberBusiness $business)
    {
        $this->authorize('reject', $business);
        $request->validate(['comment' => 'required|string|max:500']);

        $this->workflow->reject($business, auth()->user(), $request->comment);
        return back()->with('success', "व्यवसाय {$business->unique_code} अस्वीकृत गरियो।");
    }

    // ── Bulk Publish / Reject ────────────────────────────────────────────────

    private function resolveWardIds(): \Illuminate\Support\Collection
    {
        $user = auth()->user();
        return $user->isAdmin()
            ? Ward::where('local_government_id', $user->local_government_id)->pluck('id')
            : $user->assignedWards()->pluck('wards.id');
    }

    public function bulkPublishHouseholds(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user = auth()->user(); $wardIds = $this->resolveWardIds();
        [$published, $skipped] = [0, 0];
        foreach ($request->ids as $id) {
            $hh = Household::find($id);
            if (!$hh || !in_array($hh->status, ['ward_approved', 'escalated']) || !$wardIds->contains($hh->ward_id)) { $skipped++; continue; }
            $this->workflow->publish($hh, $user); $published++;
        }
        return back()->with('success', "{$published} घरधुरी प्रकाशित गरियो" . ($skipped ? " ({$skipped} छोडियो)।" : "।"));
    }

    public function bulkRejectHouseholds(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'comment' => 'required|string|max:500']);
        $user = auth()->user(); $wardIds = $this->resolveWardIds();
        [$rejected, $skipped] = [0, 0];
        foreach ($request->ids as $id) {
            $hh = Household::find($id);
            if (!$hh || !in_array($hh->status, ['ward_approved', 'escalated']) || !$wardIds->contains($hh->ward_id)) { $skipped++; continue; }
            $this->workflow->reject($hh, $user, $request->comment); $rejected++;
        }
        return back()->with('success', "{$rejected} घरधुरी अस्वीकृत गरियो" . ($skipped ? " ({$skipped} छोडियो)।" : "।"));
    }

    public function bulkPublishInstitutions(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user = auth()->user(); $wardIds = $this->resolveWardIds();
        [$published, $skipped] = [0, 0];
        foreach ($request->ids as $id) {
            $inst = Institution::find($id);
            if (!$inst || !in_array($inst->status, ['ward_approved', 'escalated']) || !$wardIds->contains($inst->ward_id)) { $skipped++; continue; }
            $this->workflow->publish($inst, $user); $published++;
        }
        return back()->with('success', "{$published} संस्था प्रकाशित गरियो" . ($skipped ? " ({$skipped} छोडियो)।" : "।"));
    }

    public function bulkRejectInstitutions(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'comment' => 'required|string|max:500']);
        $user = auth()->user(); $wardIds = $this->resolveWardIds();
        [$rejected, $skipped] = [0, 0];
        foreach ($request->ids as $id) {
            $inst = Institution::find($id);
            if (!$inst || !in_array($inst->status, ['ward_approved', 'escalated']) || !$wardIds->contains($inst->ward_id)) { $skipped++; continue; }
            $this->workflow->reject($inst, $user, $request->comment); $rejected++;
        }
        return back()->with('success', "{$rejected} संस्था अस्वीकृत गरियो" . ($skipped ? " ({$skipped} छोडियो)।" : "।"));
    }

    public function bulkPublishBusinesses(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $user = auth()->user(); $wardIds = $this->resolveWardIds();
        [$published, $skipped] = [0, 0];
        foreach ($request->ids as $id) {
            $biz = MemberBusiness::find($id);
            if (!$biz || !in_array($biz->status, ['ward_approved', 'escalated']) || !$wardIds->contains($biz->ward_id)) { $skipped++; continue; }
            $this->workflow->publish($biz, $user); $published++;
        }
        return back()->with('success', "{$published} व्यवसाय प्रकाशित गरियो" . ($skipped ? " ({$skipped} छोडियो)।" : "।"));
    }

    public function bulkRejectBusinesses(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'comment' => 'required|string|max:500']);
        $user = auth()->user(); $wardIds = $this->resolveWardIds();
        [$rejected, $skipped] = [0, 0];
        foreach ($request->ids as $id) {
            $biz = MemberBusiness::find($id);
            if (!$biz || !in_array($biz->status, ['ward_approved', 'escalated']) || !$wardIds->contains($biz->ward_id)) { $skipped++; continue; }
            $this->workflow->reject($biz, $user, $request->comment); $rejected++;
        }
        return back()->with('success', "{$rejected} व्यवसाय अस्वीकृत गरियो" . ($skipped ? " ({$skipped} छोडियो)।" : "।"));
    }
}
