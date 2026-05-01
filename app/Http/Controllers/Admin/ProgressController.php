<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Institution;
use App\Models\MemberBusiness;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProgressController extends Controller
{
    public function index()
    {
        $lgId    = auth()->user()->local_government_id;
        $wardIds = Ward::where('local_government_id', $lgId)->pluck('id')->toArray();

        // ── Top-level summary counts ───────────────────────────────────
        $statusCounts = Household::whereIn('ward_id', $wardIds)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $totalHH        = array_sum($statusCounts);
        $totalSubmitted = ($statusCounts['submitted']     ?? 0)
                        + ($statusCounts['ward_approved'] ?? 0)
                        + ($statusCounts['escalated']     ?? 0)
                        + ($statusCounts['published']     ?? 0)
                        + ($statusCounts['rejected']      ?? 0);
        $totalApproved  = ($statusCounts['ward_approved'] ?? 0)
                        + ($statusCounts['escalated']     ?? 0)
                        + ($statusCounts['published']     ?? 0);
        $totalPublished = $statusCounts['published'] ?? 0;

        $summary = [
            'total'           => $totalHH,
            'draft'           => $statusCounts['draft']         ?? 0,
            'submitted'       => $statusCounts['submitted']     ?? 0,
            'ward_approved'   => $statusCounts['ward_approved'] ?? 0,
            'escalated'       => $statusCounts['escalated']     ?? 0,
            'published'       => $totalPublished,
            'rejected'        => $statusCounts['rejected']      ?? 0,
            'submitted_pct'   => $totalHH > 0 ? round($totalSubmitted / $totalHH * 100) : 0,
            'approved_pct'    => $totalHH > 0 ? round($totalApproved  / $totalHH * 100) : 0,
            'published_pct'   => $totalHH > 0 ? round($totalPublished / $totalHH * 100) : 0,
            'total_inst'      => Institution::whereIn('ward_id', $wardIds)->count(),
            'pub_inst'        => Institution::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
            'total_biz'       => MemberBusiness::whereIn('ward_id', $wardIds)->count(),
            'pub_biz'         => MemberBusiness::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
        ];

        // ── 30-day daily submission trend ──────────────────────────────
        $rawTrend = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(submitted_at) as day, count(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $trendDays    = [];
        $trendCounts  = [];
        for ($i = 29; $i >= 0; $i--) {
            $day           = now()->subDays($i)->format('Y-m-d');
            $trendDays[]   = now()->subDays($i)->format('M d');
            $trendCounts[] = (int) ($rawTrend[$day] ?? 0);
        }

        // ── Ward progress cards ────────────────────────────────────────
        $lastActWard = Household::whereIn('ward_id', $wardIds)
            ->select('ward_id', DB::raw('MAX(updated_at) as last_at'))
            ->groupBy('ward_id')
            ->pluck('last_at', 'ward_id');

        $wardProgress = Ward::where('local_government_id', $lgId)
            ->with([
                'assignedUsers' => fn($q) => $q->select('users.id', 'users.name', 'users.role', 'users.is_active'),
            ])
            ->withCount([
                'households',
                'households as hh_draft'         => fn($q) => $q->where('status', 'draft'),
                'households as hh_submitted'     => fn($q) => $q->where('status', 'submitted'),
                'households as hh_ward_approved' => fn($q) => $q->whereIn('status', ['ward_approved', 'escalated']),
                'households as hh_published'     => fn($q) => $q->where('status', 'published'),
                'households as hh_rejected'      => fn($q) => $q->where('status', 'rejected'),
                'institutions',
                'institutions as inst_published' => fn($q) => $q->where('status', 'published'),
            ])
            ->orderBy('ward_number')
            ->get()
            ->map(fn($w) => [
                'id'               => $w->id,
                'ward_number'      => $w->ward_number,
                'hh_total'         => $w->households_count,
                'hh_draft'         => $w->hh_draft,
                'hh_submitted'     => $w->hh_submitted,
                'hh_ward_approved' => $w->hh_ward_approved,
                'hh_published'     => $w->hh_published,
                'hh_rejected'      => $w->hh_rejected,
                'inst_total'       => $w->institutions_count,
                'inst_published'   => $w->inst_published,
                'enumerators'      => $w->assignedUsers
                    ->where('role', 'enumerator')
                    ->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'is_active' => $u->is_active])
                    ->values(),
                'reviewers'        => $w->assignedUsers
                    ->where('role', 'ward_reviewer')
                    ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values(),
                'last_active_at'   => $lastActWard[$w->id] ?? null,
            ]);

        // ── Enumerator breakdown ───────────────────────────────────────
        $lastActEnum = Household::whereIn('ward_id', $wardIds)
            ->select('enumerator_id', DB::raw('MAX(updated_at) as last_at'))
            ->groupBy('enumerator_id')
            ->pluck('last_at', 'enumerator_id');

        $enumerators = User::where('local_government_id', $lgId)
            ->where('role', 'enumerator')
            ->with('assignedWards:id,ward_number')
            ->withCount([
                'households as hh_total'         => fn($q) => $q->whereIn('ward_id', $wardIds),
                'households as hh_draft'         => fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'draft'),
                'households as hh_submitted'     => fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'submitted'),
                'households as hh_ward_approved' => fn($q) => $q->whereIn('ward_id', $wardIds)->whereIn('status', ['ward_approved', 'escalated']),
                'households as hh_published'     => fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'published'),
                'households as hh_rejected'      => fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'rejected'),
                'institutions as inst_total'     => fn($q) => $q->whereIn('ward_id', $wardIds),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'               => $u->id,
                'name'             => $u->name,
                'is_active'        => $u->is_active,
                'assigned_wards'   => $u->assignedWards->pluck('ward_number')->sort()->values(),
                'hh_total'         => $u->hh_total         ?? 0,
                'hh_draft'         => $u->hh_draft         ?? 0,
                'hh_submitted'     => $u->hh_submitted     ?? 0,
                'hh_ward_approved' => $u->hh_ward_approved ?? 0,
                'hh_published'     => $u->hh_published     ?? 0,
                'hh_rejected'      => $u->hh_rejected      ?? 0,
                'inst_total'       => $u->inst_total        ?? 0,
                'last_active_at'   => $lastActEnum[$u->id] ?? null,
            ]);

        return Inertia::render('Admin/Progress', [
            'title'        => 'प्रगति ड्यासबोर्ड',
            'summary'      => $summary,
            'trendDays'    => $trendDays,
            'trendCounts'  => $trendCounts,
            'wardProgress' => $wardProgress,
            'enumerators'  => $enumerators,
        ]);
    }
}
