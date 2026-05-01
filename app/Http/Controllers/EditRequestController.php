<?php

namespace App\Http\Controllers;

use App\Models\EditRequest;
use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EditRequestController extends Controller
{
    /** Ward reviewer creates an edit request for a published record */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->isReviewer(), 403);

        $data = $request->validate([
            'requestable_type' => 'required|string|in:household,institution,business',
            'requestable_id'   => 'required|integer',
            'reason'           => 'required|string|max:1000',
        ]);

        $modelMap = [
            'household'   => Household::class,
            'institution' => Institution::class,
            'business'    => MemberBusiness::class,
        ];

        $modelClass = $modelMap[$data['requestable_type']];
        $record     = $modelClass::findOrFail($data['requestable_id']);

        abort_unless($record->status === 'published', 422, 'केवल प्रकाशित रेकर्डको लागि अनुरोध गर्न सकिन्छ।');

        // Reviewer may only request edits for records in their assigned wards
        $assignedWardIds = auth()->user()->assignedWards()->pluck('wards.id')->toArray();
        abort_unless(in_array($record->ward_id, $assignedWardIds), 403, 'यस वडाको रेकर्डमा पहुँच छैन।');

        // Prevent duplicate pending requests
        $existing = EditRequest::where('requestable_type', $modelClass)
            ->where('requestable_id', $record->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return back()->with('error', 'यस रेकर्डको लागि पहिले नै अनुरोध पेस गरिएको छ।');
        }

        EditRequest::create([
            'requestable_type' => $modelClass,
            'requestable_id'   => $record->id,
            'requested_by'     => auth()->id(),
            'status'           => 'pending',
            'reason'           => $data['reason'],
        ]);

        return back()->with('success', 'सम्पादन अनुरोध पेस गरियो। प्रशासकले समीक्षा गर्नेछन्।');
    }

    /** Admin approves an edit request */
    public function approve(Request $request, EditRequest $editRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($editRequest->isPending(), 422);

        $data = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $editRequest->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'admin_note'  => $data['admin_note'] ?? null,
        ]);

        return back()->with('success', 'अनुरोध स्वीकृत गरियो।');
    }

    /** Admin rejects an edit request */
    public function reject(Request $request, EditRequest $editRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_unless($editRequest->isPending(), 422);

        $data = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $editRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'admin_note'  => $data['admin_note'] ?? null,
        ]);

        return back()->with('success', 'अनुरोध अस्वीकार गरियो।');
    }

    /** Admin view — list all edit requests */
    public function index(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $status = $request->get('status', 'pending');

        $requests = EditRequest::with(['requester:id,name,role', 'reviewer:id,name'])
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->through(function (EditRequest $er) {
                $record = $er->requestable;
                return [
                    'id'             => $er->id,
                    'status'         => $er->status,
                    'reason'         => $er->reason,
                    'admin_note'     => $er->admin_note,
                    'resource_label' => $er->resourceLabel(),
                    'record_code'    => $record?->unique_code ?? '—',
                    'record_id'      => $er->requestable_id,
                    'record_type'    => $er->requestable_type,
                    'record_url'     => $this->recordUrl($er),
                    'requester'      => $er->requester ? ['id' => $er->requester->id, 'name' => $er->requester->name] : null,
                    'reviewer'       => $er->reviewer  ? ['id' => $er->reviewer->id,  'name' => $er->reviewer->name]  : null,
                    'created_at'     => $er->created_at,
                    'updated_at'     => $er->updated_at,
                ];
            });

        $counts = [
            'pending'  => EditRequest::where('status', 'pending')->count(),
            'approved' => EditRequest::where('status', 'approved')->count(),
            'rejected' => EditRequest::where('status', 'rejected')->count(),
        ];

        return Inertia::render('Admin/EditRequests', [
            'requests'      => $requests,
            'activeStatus'  => $status,
            'counts'        => $counts,
        ]);
    }

    private function recordUrl(EditRequest $er): string
    {
        return match ($er->requestable_type) {
            Household::class      => "/households/{$er->requestable_id}",
            Institution::class    => "/institutions/{$er->requestable_id}",
            MemberBusiness::class => "/businesses/{$er->requestable_id}",
            default               => '#',
        };
    }
}
