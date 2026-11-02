<?php

namespace App\Http\Middleware;

use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:shramdaan` — the route exists only where the module is switched on.
 *
 * 404 rather than 403: in a municipality that has not taken the module up, its
 * pages should not appear to exist at all.
 */
class RequireModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(Modules::enabled($module, $request->user()?->local_government_id), 404);

        return $next($request);
    }
}
