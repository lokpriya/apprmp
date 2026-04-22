<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDataFreeze
{
    public function handle(Request $request, Closure $next): Response
    {
        $model = $request->route('household') ?? $request->route('institution');

        if ($model && $model->isFrozen()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This record is frozen and cannot be modified.'], 423);
            }
            return back()->withErrors(['frozen' => 'यो रेकर्ड बन्द गरिएको छ र परिमार्जन गर्न सकिँदैन।']);
        }

        return $next($request);
    }
}
