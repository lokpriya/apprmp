<?php

namespace App\Http\Middleware;

use App\Models\LocalGovernment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        // Super admin always passes — they need access to renew the license
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        $lg = LocalGovernment::getFirstCached();

        if ($lg && !$lg->isLicensed()) {
            return response()->view('license-expired', ['lg' => $lg], 402);
        }

        return $next($request);
    }
}
