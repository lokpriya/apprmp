<?php

namespace App\Http\Middleware;

use App\Models\LocalGovernment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authenticated users bypass maintenance mode (for admin verification)
        if (auth()->check()) {
            return $next($request);
        }

        $lg     = LocalGovernment::first();
        $config = $lg?->maintenance_config ?? [];

        if ($config['is_active'] ?? false) {
            return response()->view('maintenance', [
                'lg'     => $lg,
                'config' => $config,
            ], 503);
        }

        return $next($request);
    }
}
