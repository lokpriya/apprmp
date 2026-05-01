<?php

namespace App\Http\Middleware;

use App\Models\EditRequest;
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
                'local_government' => $request->user()->localGovernment?->name_np,
                'lg' => $request->user()->localGovernment?->only('id', 'name_np', 'name_en', 'photo_dir'),
            ] : null,

            // Cached for 30 s — cheap for a toggle that rarely changes mid-session
            'surveyFrozen' => fn () => $request->user()
                ? Cache::remember(
                    'survey_frozen_' . ($request->user()->local_government_id ?? 0),
                    30,
                    fn () => (bool) $request->user()->localGovernment?->isSurveyFrozen()
                )
                : false,

            // Cached 60 s per LG — bust via EditRequest::saved/deleted observer
            'editRequestsCount' => fn () => $request->user()?->isAdmin()
                ? Cache::remember('edit_requests_pending', 60, fn () =>
                    EditRequest::where('status', 'pending')->count()
                  )
                : 0,

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

            'flash' => [
                'success'                         => fn () => $request->session()->get('success'),
                'error'                           => fn () => $request->session()->get('error'),
                'import_result'                   => fn () => $request->session()->get('import_result'),
                'settlement_import_result'        => fn () => $request->session()->get('settlement_import_result'),
                'ward_import_result'              => fn () => $request->session()->get('ward_import_result'),
                'ward_settlements_import_result'  => fn () => $request->session()->get('ward_settlements_import_result'),
            ],
        ];
    }
}
