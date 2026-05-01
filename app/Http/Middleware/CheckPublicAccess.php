<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPublicAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('public_verified')) {
            session(['public_intended' => $request->fullUrl()]);
            return redirect('/verify');
        }
        return $next($request);
    }
}
