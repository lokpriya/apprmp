<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ScopeToWard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->role, ['enumerator', 'ward_reviewer', 'ward_admin'])) {
            $wardIds = $user->assignedWards()->pluck('wards.id')->toArray();
            $request->merge(['_ward_scope' => $wardIds]);
            View::share('scopedWardIds', $wardIds);
        }

        return $next($request);
    }
}
