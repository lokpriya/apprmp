<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DataExport;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Ward;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class DataExportController extends Controller
{
    private const STATUS_GROUPS = [
        'all'           => ['draft', 'submitted', 'ward_approved', 'escalated', 'published', 'rejected'],
        'no_draft'      => ['submitted', 'ward_approved', 'escalated', 'published', 'rejected'],
        'approved_plus' => ['ward_approved', 'escalated', 'published'],
        'published'     => ['published'],
    ];

    public function index(Request $request)
    {
        $lgId  = auth()->user()->local_government_id;
        $wards = Ward::where('local_government_id', $lgId)
            ->orderBy('ward_number')
            ->get(['id', 'ward_number']);

        // Quick counts for the preview table (per ward, published)
        $wardIds = $wards->pluck('id')->toArray();
        $counts  = Household::whereIn('ward_id', $wardIds)
            ->selectRaw('ward_id, status, count(*) as cnt')
            ->groupBy('ward_id', 'status')
            ->get()
            ->groupBy('ward_id');

        $wardStats = $wards->map(fn($w) => [
            'id'          => $w->id,
            'ward_number' => $w->ward_number,
            'total'       => ($counts[$w->id] ?? collect())->sum('cnt'),
            'published'   => ($counts[$w->id] ?? collect())->where('status', 'published')->sum('cnt'),
            'draft'       => ($counts[$w->id] ?? collect())->where('status', 'draft')->sum('cnt'),
        ]);

        return Inertia::render('Admin/DataExport', [
            'title'     => 'तथ्याङ्क निर्यात',
            'wards'     => $wards,
            'wardStats' => $wardStats,
        ]);
    }

    public function download(Request $request)
    {
        ini_set('memory_limit', '512M');

        $request->validate([
            'ward_scope'   => 'required|in:all,specific',
            'ward_id'      => 'nullable|integer|exists:wards,id',
            'status_scope' => 'required|in:all,no_draft,approved_plus,published',
            'sheets'       => 'required|array|min:1',
            'sheets.*'     => 'in:households,members,institutions,businesses',
        ]);

        $lgId    = auth()->user()->local_government_id;
        $allWardIds = Ward::where('local_government_id', $lgId)->pluck('id')->toArray();

        if ($request->ward_scope === 'specific' && $request->ward_id) {
            abort_unless(in_array($request->ward_id, $allWardIds), 403);
            $wardIds = [$request->ward_id];
        } else {
            $wardIds = $allWardIds;
        }

        $statuses = self::STATUS_GROUPS[$request->status_scope];
        $sheets   = $request->input('sheets', []);

        // Ward summary for the Summary sheet
        $wardSummary = $this->buildWardSummary($wardIds);

        $lgName  = auth()->user()->localGovernment->name_np
                ?? auth()->user()->localGovernment->name_en
                ?? 'LG';

        $export = new DataExport(
            wardIds:             $wardIds,
            statuses:            $statuses,
            includeHouseholds:   in_array('households',   $sheets),
            includeMembers:      in_array('members',      $sheets),
            includeInstitutions: in_array('institutions', $sheets),
            includeBusinesses:   in_array('businesses',   $sheets),
            lgName:              $lgName,
            wardSummary:         $wardSummary,
        );

        $wardLabel = $request->ward_scope === 'specific'
            ? 'ward' . $request->ward_id
            : 'municipality';
        $filename  = "export_{$wardLabel}_" . now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    private function buildWardSummary(array $wardIds): array
    {
        $wards = Ward::whereIn('id', $wardIds)->orderBy('ward_number')->get(['id', 'ward_number']);

        $counts = Household::whereIn('ward_id', $wardIds)
            ->selectRaw('ward_id, status, count(*) as cnt')
            ->groupBy('ward_id', 'status')
            ->get()
            ->groupBy('ward_id');

        return $wards->map(fn($w) => [
            'ward_number'      => $w->ward_number,
            'hh_total'         => ($counts[$w->id] ?? collect())->sum('cnt'),
            'hh_draft'         => ($counts[$w->id] ?? collect())->where('status', 'draft')->sum('cnt'),
            'hh_submitted'     => ($counts[$w->id] ?? collect())->where('status', 'submitted')->sum('cnt'),
            'hh_ward_approved' => ($counts[$w->id] ?? collect())->whereIn('status', ['ward_approved', 'escalated'])->sum('cnt'),
            'hh_published'     => ($counts[$w->id] ?? collect())->where('status', 'published')->sum('cnt'),
            'hh_rejected'      => ($counts[$w->id] ?? collect())->where('status', 'rejected')->sum('cnt'),
        ])->values()->toArray();
    }
}
