<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Institution;
use App\Models\SurveyOption;
use App\Models\MemberBusiness;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $lgId  = $user->local_government_id;

        $wardIds = Ward::where('local_government_id', $lgId)
            ->pluck('id')
            ->toArray();

        // ── Core Counts ────────────────────────────────────────────────
        $stats = [
            'total_households'         => Household::whereIn('ward_id', $wardIds)->count(),
            'published_households'     => Household::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
            'draft_households'         => Household::whereIn('ward_id', $wardIds)->where('status', 'draft')->count(),
            'submitted_households'     => Household::whereIn('ward_id', $wardIds)->where('status', 'submitted')->count(),
            'ward_approved_households' => Household::whereIn('ward_id', $wardIds)->where('status', 'ward_approved')->count(),
            'rejected_households'      => Household::whereIn('ward_id', $wardIds)->where('status', 'rejected')->count(),

            'total_institutions'       => Institution::whereIn('ward_id', $wardIds)->count(),
            'published_institutions'   => Institution::whereIn('ward_id', $wardIds)->where('status', 'published')->count(),
            'submitted_institutions'   => Institution::whereIn('ward_id', $wardIds)->where('status', 'submitted')->count(),

            'total_businesses'         => MemberBusiness::whereHas('member.household', fn($q) => $q->whereIn('ward_id', $wardIds))->count(),
            'published_businesses'     => MemberBusiness::whereHas('member.household', fn($q) => $q->whereIn('ward_id', $wardIds))->where('status', 'published')->count(),
            'submitted_businesses'     => MemberBusiness::whereHas('member.household', fn($q) => $q->whereIn('ward_id', $wardIds))->where('status', 'submitted')->count(),

            'total_users'              => User::where('local_government_id', $lgId)->count(),
            'active_enumerators'       => User::where('local_government_id', $lgId)
                                              ->where('role', 'enumerator')
                                              ->where('is_active', true)
                                              ->count(),

            'missing_gps_households'  => Household::whereIn('ward_id', $wardIds)
                ->where(fn($q) => $q->whereNull('gps_lat')->orWhereNull('gps_lng'))
                ->count(),
            'no_member_households'    => Household::whereIn('ward_id', $wardIds)
                ->whereDoesntHave('members')
                ->count(),
        ];

        // ── Total Population (published households only) ────────────────
        $stats['total_population'] = Household::whereIn('ward_id', $wardIds)
            ->where('status', 'published')
            ->sum('total_members');

        // ── Gender Breakdown (from members of published households) ─────
        $genderBreakdown = HouseholdMember::whereHas('household', fn ($q) =>
            $q->whereIn('ward_id', $wardIds)->where('status', 'published')
        )
        ->selectRaw('gender, count(*) as cnt')
        ->groupBy('gender')
        ->pluck('cnt', 'gender');

        // ── Household Status Distribution ───────────────────────────────
        $statusBreakdown = Household::whereIn('ward_id', $wardIds)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // ── Institution Types Count ─────────────────────────────────────
        $typeLabels = SurveyOption::where('category', 'institution_type')->pluck('label_ne', 'value');
        $typeCounts = Institution::whereIn('ward_id', $wardIds)
            ->selectRaw('institution_type, count(*) as institutions_count')
            ->groupBy('institution_type')
            ->orderByDesc('institutions_count')
            ->get();
        $institutionTypes = $typeCounts->map(fn($r) => (object)[
            'name_np'            => $typeLabels[$r->institution_type] ?? $r->institution_type,
            'institutions_count' => $r->institutions_count,
        ]);

        // ── Enumerator Leaderboard (top 5) ──────────────────────────────
        $enumeratorLeaderboard = User::where('local_government_id', $lgId)
            ->where('role', 'enumerator')
            ->withCount([
                'households as total_submitted' => fn ($q) =>
                    $q->whereIn('ward_id', $wardIds)->where('status', '!=', 'draft'),
                'households as total_published' => fn ($q) =>
                    $q->whereIn('ward_id', $wardIds)->where('status', 'published'),
            ])
            ->orderByDesc('total_submitted')
            ->take(5)
            ->get();

        // ── Enumerator Progress (all enumerators, full breakdown) ────────
        $lastActiveMap = Household::whereIn('ward_id', $wardIds)
            ->select('enumerator_id', DB::raw('MAX(updated_at) as last_active_at'))
            ->groupBy('enumerator_id')
            ->pluck('last_active_at', 'enumerator_id');

        $enumeratorProgress = User::where('local_government_id', $lgId)
            ->where('role', 'enumerator')
            ->with('assignedWards:id,ward_number')
            ->withCount([
                'households as hh_total'         => fn($q) => $q->whereIn('ward_id', $wardIds),
                'households as hh_draft'         => fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'draft'),
                'households as hh_submitted'     => fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'submitted'),
                'households as hh_ward_approved' => fn($q) => $q->whereIn('ward_id', $wardIds)->where('status', 'ward_approved'),
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
                'wards'            => $u->assignedWards->pluck('ward_number')->sort()->values(),
                'hh_total'         => $u->hh_total         ?? 0,
                'hh_draft'         => $u->hh_draft         ?? 0,
                'hh_submitted'     => $u->hh_submitted     ?? 0,
                'hh_ward_approved' => $u->hh_ward_approved ?? 0,
                'hh_published'     => $u->hh_published     ?? 0,
                'hh_rejected'      => $u->hh_rejected      ?? 0,
                'inst_total'       => $u->inst_total        ?? 0,
                'last_active_at'   => $lastActiveMap[$u->id] ?? null,
            ]);

        // ── Weekly Submission Trend (last 7 days) ───────────────────────
        $rawTrend = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(submitted_at) as day, count(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day             = now()->subDays($i)->format('Y-m-d');
            $trendData[$day] = $rawTrend[$day] ?? 0;
        }

        // ── Ward Summary ────────────────────────────────────────────────
        $wardSummary = Ward::where('local_government_id', $lgId)
            ->withCount([
                'households',
                'households as published_count'  => fn ($q) => $q->where('status', 'published'),
                'households as pending_count'    => fn ($q) => $q->where('status', 'submitted'),
                'households as rejected_count'   => fn ($q) => $q->where('status', 'rejected'),
                'institutions',
                'institutions as pub_inst_count' => fn ($q) => $q->where('status', 'published'),
            ])
            ->orderBy('ward_number')
            ->get();

        // ── Weekly Digest (last 7 days vs previous 7 days) ─────────────
        $weekStart = now()->subDays(7)->startOfDay();
        $prevStart = now()->subDays(14)->startOfDay();

        $hhSubmittedWeek = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $weekStart)->count();
        $hhSubmittedPrev = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [$prevStart, $weekStart])->count();

        $hhApprovedWeek = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('ward_reviewed_at')
            ->where('ward_reviewed_at', '>=', $weekStart)
            ->whereIn('status', ['ward_approved', 'escalated', 'published'])->count();
        $hhApprovedPrev = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('ward_reviewed_at')
            ->whereBetween('ward_reviewed_at', [$prevStart, $weekStart])
            ->whereIn('status', ['ward_approved', 'escalated', 'published'])->count();

        $hhPublishedWeek = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $weekStart)->count();
        $hhPublishedPrev = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$prevStart, $weekStart])->count();

        $topEnumRow = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $weekStart)
            ->whereNotNull('enumerator_id')
            ->select('enumerator_id', DB::raw('count(*) as cnt'))
            ->groupBy('enumerator_id')
            ->orderByDesc('cnt')
            ->first();
        $topEnumUser = $topEnumRow ? User::find($topEnumRow->enumerator_id) : null;

        $topWardRow = Household::whereIn('ward_id', $wardIds)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $weekStart)
            ->select('ward_id', DB::raw('count(*) as cnt'))
            ->groupBy('ward_id')
            ->orderByDesc('cnt')
            ->first();
        $topWardObj = $topWardRow ? Ward::find($topWardRow->ward_id) : null;

        $digest = [
            'hh_submitted_week'  => $hhSubmittedWeek,
            'hh_submitted_delta' => $hhSubmittedWeek - $hhSubmittedPrev,
            'hh_approved_week'   => $hhApprovedWeek,
            'hh_approved_delta'  => $hhApprovedWeek - $hhApprovedPrev,
            'hh_published_week'  => $hhPublishedWeek,
            'hh_published_delta' => $hhPublishedWeek - $hhPublishedPrev,
            'top_enumerator'     => $topEnumUser?->name,
            'top_enumerator_count' => (int) ($topEnumRow?->cnt ?? 0),
            'top_ward_number'    => $topWardObj?->ward_number,
            'top_ward_count'     => (int) ($topWardRow?->cnt ?? 0),
        ];

        // ── Recent Activity (households + institutions, combined) ────────
        $recentHouseholds = Household::with(['ward', 'enumerator'])
            ->whereIn('ward_id', $wardIds)
            ->whereIn('status', ['submitted', 'ward_approved', 'rejected', 'published'])
            ->latest('updated_at')
            ->take(6)
            ->get()
            ->map(fn ($h) => [
                'type'        => 'household',
                'label'       => 'घर',
                'name'        => $h->respondent_name ?? '—',
                'code'        => $h->unique_code,
                'ward'        => $h->ward->ward_number ?? '—',
                'enumerator'  => $h->enumerator->name ?? '—',
                'status'      => $h->status,
                'updated_at'  => $h->updated_at,
                'url'         => route('households.show', $h),
            ]);

        $recentInstitutions = Institution::with(['ward', 'enumerator'])
            ->whereIn('ward_id', $wardIds)
            ->whereIn('status', ['submitted', 'ward_approved', 'rejected', 'published'])
            ->latest('updated_at')
            ->take(6)
            ->get()
            ->map(fn ($i) => [
                'type'        => 'institution',
                'label'       => 'संस्था',
                'name'        => $i->name_np ?? $i->name_en ?? '—',
                'code'        => $i->unique_code,
                'ward'        => $i->ward->ward_number ?? '—',
                'enumerator'  => $i->enumerator->name ?? '—',
                'status'      => $i->status,
                'updated_at'  => $i->updated_at,
                'url'         => route('institutions.show', $i),
            ]);

        $recentActivity = collect($recentHouseholds)
            ->merge($recentInstitutions)
            ->sortByDesc('updated_at')
            ->take(10)
            ->values();

        return Inertia::render('Admin/Dashboard', [
            'title'                => 'ड्यासबोर्ड',
            'stats'                => $stats,
            'genderBreakdown'      => $genderBreakdown,
            'statusBreakdown'      => $statusBreakdown,
            'institutionTypes'     => $institutionTypes,
            'enumeratorLeaderboard'=> $enumeratorLeaderboard,
            'enumeratorProgress'   => $enumeratorProgress,
            'trendData'            => $trendData,
            'wardSummary'          => $wardSummary,
            'recentActivity'       => $recentActivity,
            'digest'               => $digest,
        ]);
    }
}
