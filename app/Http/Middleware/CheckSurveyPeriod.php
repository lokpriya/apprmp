<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSurveyPeriod
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only restrict enumerators on write operations
        if (!$user || $user->role !== 'enumerator') {
            return $next($request);
        }

        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $lg = $user->localGovernment;
        if ($lg && $lg->isSurveyFrozen()) {
            $config  = $lg->survey_config ?? [];
            $message = $config['freeze_message'] ?? 'सर्वेक्षण अवधि समाप्त भएकोले नयाँ डाटा थप्न वा परिमार्जन गर्न सकिँदैन।';
            return back()->with('error', $message);
        }

        return $next($request);
    }
}
