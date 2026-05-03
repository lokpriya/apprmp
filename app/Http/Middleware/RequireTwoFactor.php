<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) return $next($request);

        // Roles that must have 2FA configured before using the app
        if ($user->twoFactorRequiredForRole() && !$user->hasTwoFactorEnabled()) {
            // Allow access to 2FA setup routes and logout so they're not locked out
            if ($request->routeIs('two-factor.setup-page', 'two-factor.setup', 'two-factor.confirm', 'two-factor.disable', 'two-factor.recovery-codes', 'logout', 'profile.*')) {
                return $next($request);
            }
            return redirect()->route('two-factor.setup-page')
                ->with('warning', 'आपको भूमिकाका लागि दुई-चरण प्रमाणीकरण (2FA) अनिवार्य छ। सेटअप गर्नुहोस्।');
        }

        return $next($request);
    }
}
