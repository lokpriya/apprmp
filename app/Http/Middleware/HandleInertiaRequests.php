<?php

namespace App\Http\Middleware;

use App\Models\EditRequest;
use App\Models\LocalGovernment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => $request->user() ? [
                'user' => [
                    'id'    => $request->user()->id,
                    'name'  => $request->user()->name,
                    'email' => $request->user()->email,
                    'role'  => $request->user()->role,
                ],
                'local_government' => ($request->user()->local_government_id
                    ? LocalGovernment::getCached($request->user()->local_government_id)?->name_np
                    : null),
                'lg' => ($request->user()->local_government_id
                    ? LocalGovernment::getCached($request->user()->local_government_id)?->only('id', 'name_np', 'name_en', 'photo_dir')
                    : null),
            ] : null,

            // Cached for 30 s — cheap for a toggle that rarely changes mid-session
            'surveyFrozen' => fn () => $request->user()
                ? Cache::remember(
                    'survey_frozen_' . ($request->user()->local_government_id ?? 0),
                    30,
                    fn () => (bool) ($request->user()->local_government_id
                        ? LocalGovernment::getCached($request->user()->local_government_id)?->isSurveyFrozen()
                        : false)
                )
                : false,

            // rememberForever for admin (busted by EditRequest::booted()); 60 s per ward_admin
            'editRequestsCount' => fn () => $request->user()?->isAdmin()
                ? Cache::rememberForever('edit_requests_pending', fn () =>
                    EditRequest::where('status', 'pending')->count()
                  )
                : ($request->user()?->isWardAdmin()
                    ? Cache::remember('edit_requests_pending_wa_' . $request->user()->id, 60, function () use ($request) {
                        $wardIds = $request->user()->assignedWards()->pluck('wards.id');
                        return EditRequest::where('status', 'pending')
                            ->where(fn($q) => $q
                                ->whereHasMorph('requestable', [\App\Models\Household::class],        fn($r) => $r->whereIn('ward_id', $wardIds))
                                ->orWhereHasMorph('requestable', [\App\Models\Institution::class],    fn($r) => $r->whereIn('ward_id', $wardIds))
                                ->orWhereHasMorph('requestable', [\App\Models\MemberBusiness::class], fn($r) => $r->whereIn('ward_id', $wardIds))
                            )->count();
                    })
                    : 0),

            // Cached 60 s per user — badge doesn't need to be instant
            'notificationsCount' => fn () => $request->user()
                ? Cache::remember(
                    'notif_unread_' . $request->user()->id,
                    60,
                    fn () => $request->user()->unreadNotifications()->count()
                  )
                : 0,

            // Notifications list: short 30 s cache per user (user reads clear the cache)
            'recentNotifications' => fn () => $request->user()
                ? Cache::remember(
                    'notif_recent_' . $request->user()->id,
                    30,
                    fn () => $request->user()->notifications()->latest()->limit(10)->get()->map(fn ($n) => [
                        'id'         => $n->id,
                        'data'       => $n->data,
                        'read_at'    => $n->read_at?->toISOString(),
                        'created_at' => $n->created_at->diffForHumans(),
                    ])->toArray()
                  )
                : [],

            // Gamification stats — enumerator only, cached 5 min per user
            'myGamification' => fn () => $request->user()?->role === 'enumerator'
                ? Cache::remember('gamification_' . $request->user()->id, 300, function () use ($request) {
                    $uid    = $request->user()->id;
                    $lgId   = $request->user()->local_government_id;
                    $wardIds = \App\Models\Ward::where('local_government_id', $lgId)->pluck('id');

                    $nonDraft  = \App\Models\Household::where('enumerator_id', $uid)
                        ->whereIn('ward_id', $wardIds)->where('status', '!=', 'draft')->count();
                    $published = \App\Models\Household::where('enumerator_id', $uid)
                        ->whereIn('ward_id', $wardIds)->where('status', 'published')->count();

                    $xp = $nonDraft * 10 + $published * 5;

                    // Streak: consecutive calendar days with at least 1 submission
                    $dates = \App\Models\Household::where('enumerator_id', $uid)
                        ->whereNotNull('submitted_at')
                        ->selectRaw('DATE(submitted_at) as day')
                        ->groupBy('day')->orderByDesc('day')->pluck('day')->toArray();
                    $dateSet  = array_flip($dates);
                    $streak   = 0;
                    $startIdx = isset($dateSet[now()->toDateString()]) ? 0 : 1;
                    for ($i = $startIdx; $i <= 365; $i++) {
                        if (isset($dateSet[now()->subDays($i)->toDateString()])) $streak++;
                        else break;
                    }

                    // Level thresholds: 1→2 at 100 XP, 2→3 at 300, 3→4 at 600, 4→5 at 1000
                    $thresholds = [100, 300, 600, 1000];
                    $level = 1;
                    foreach ($thresholds as $t) { if ($xp >= $t) $level++; }
                    $levelNames = [1 => 'नव प्रवेशी', 2 => 'अग्रसर', 3 => 'सक्रिय', 4 => 'कुशल', 5 => 'विशेषज्ञ'];
                    $levelEmojis = [1 => '🌱', 2 => '📈', 3 => '⭐', 4 => '🏆', 5 => '💎'];
                    $prevXp = [1 => 0, 2 => 100, 3 => 300, 4 => 600, 5 => 1000];
                    $nextXp = [1 => 100, 2 => 300, 3 => 600, 4 => 1000, 5 => 1000];
                    $levelPct = $level < 5
                        ? min(100, (int) round(($xp - $prevXp[$level]) / ($nextXp[$level] - $prevXp[$level]) * 100))
                        : 100;

                    // Badges
                    $milestones = [1 => '🌱', 10 => '📝', 25 => '⭐', 50 => '🏆', 100 => '💎', 250 => '🚀'];
                    $badges = [];
                    foreach ($milestones as $n => $emoji) {
                        if ($nonDraft >= $n) $badges[] = ['count' => $n, 'emoji' => $emoji];
                    }

                    return [
                        'xp'          => $xp,
                        'total'       => $nonDraft,
                        'published'   => $published,
                        'streak'      => $streak,
                        'level'       => $level,
                        'level_name'  => $levelNames[$level],
                        'level_emoji' => $levelEmojis[$level],
                        'level_pct'   => $levelPct,
                        'badges'      => $badges,
                    ];
                })
                : null,

            'twoFactorEnabled' => fn () => (bool) $request->user()?->hasTwoFactorEnabled(),

            'impersonating' => fn () => $request->session()->has('impersonator_id') ? [
                'by_id'   => $request->session()->get('impersonator_id'),
                'by_name' => \App\Models\User::find($request->session()->get('impersonator_id'))?->name,
                'as_name' => $request->user()?->name,
                'as_role' => $request->user()?->role,
            ] : null,

            // Pending review badge — submitted/ward_approved HH awaiting action, cached 60 s
            'pendingReviewsCount' => fn () => match ($request->user()?->role) {
                'ward_reviewer', 'ward_admin' => Cache::remember(
                    'pending_review_' . $request->user()->id, 60,
                    function () use ($request) {
                        $wardIds = $request->user()->assignedWards()->pluck('wards.id');
                        return \App\Models\Household::whereIn('ward_id', $wardIds)
                            ->whereIn('status', ['submitted', 'ward_approved'])
                            ->count();
                    }
                ),
                'municipal_admin', 'super_admin' => Cache::remember(
                    'pending_review_admin_' . ($request->user()->local_government_id ?? 0), 60,
                    function () use ($request) {
                        $lgId    = $request->user()->local_government_id;
                        $wardIds = \App\Models\Ward::where('local_government_id', $lgId)->pluck('id');
                        return \App\Models\Household::whereIn('ward_id', $wardIds)
                            ->whereIn('status', ['submitted', 'ward_approved'])
                            ->count();
                    }
                ),
                default => 0,
            },

            // Feedback badge — cached 60 s; bust on submit/update/delete
            'feedbackNewCount' => fn () => $request->user()?->hasPermission('feedback.view')
                ? \Illuminate\Support\Facades\Cache::remember('public_feedback_new_count', 60, fn () =>
                    \App\Models\PublicFeedback::where('status', 'new')->count()
                  )
                : 0,

            // User manual gear link visibility — super_admin can toggle; cached 60 s
            'showUserManual' => fn () => $request->user()?->local_government_id
                ? Cache::remember('show_user_manual_' . $request->user()->local_government_id, 60,
                    fn () => (bool) (LocalGovernment::getCached($request->user()->local_government_id)?->welcome_config['show_user_manual'] ?? true)
                  )
                : true,

            // Public notice ticker — cached 5 min; bust cache when admin saves welcome settings
            'noticeBar' => fn () => Cache::remember('public_notice_bar', 300, function () {
                $lg = LocalGovernment::getFirstCached();
                $nb = $lg?->welcome_config['notice_bar'] ?? null;
                if (!($nb['enabled'] ?? false) || empty(trim($nb['text'] ?? ''))) return null;
                return ['text' => $nb['text'], 'bg' => $nb['bg'] ?? '#1a2d45'];
            }),

            // Last published date — public data freshness indicator, cached 10 min
            'dataLastUpdated' => fn () => Cache::remember('public_data_last_updated', 600, function () {
                return \App\Models\Household::where('status', 'published')->max('updated_at');
            }),

            'flash' => [
                'success'                         => fn () => $request->session()->get('success'),
                'error'                           => fn () => $request->session()->get('error'),
                'generated_key'                   => fn () => $request->session()->get('generated_key'),
                'import_result'                   => fn () => $request->session()->get('import_result'),
                'settlement_import_result'        => fn () => $request->session()->get('settlement_import_result'),
                'ward_import_result'              => fn () => $request->session()->get('ward_import_result'),
                'ward_settlements_import_result'  => fn () => $request->session()->get('ward_settlements_import_result'),
            ],
        ];
    }
}
