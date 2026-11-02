<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role'           => \App\Http\Middleware\EnsureRole::class,
            'ward.scope'     => \App\Http\Middleware\ScopeToWard::class,
            'data.freeze'    => \App\Http\Middleware\CheckDataFreeze::class,
            'maintenance'    => \App\Http\Middleware\CheckMaintenance::class,
            'license'        => \App\Http\Middleware\CheckLicense::class,
            'survey.period'  => \App\Http\Middleware\CheckSurveyPeriod::class,
            'public.access'  => \App\Http\Middleware\CheckPublicAccess::class,
            '2fa.require'    => \App\Http\Middleware\RequireTwoFactor::class,
            'module'         => \App\Http\Middleware\RequireModule::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report unhandled exceptions to Sentry. Without a SENTRY_LARAVEL_DSN in
        // the environment this is a no-op, so local development is unaffected
        // and a deployment that has not been given a DSN simply keeps logging to
        // storage/logs as before.
        Integration::handles($exceptions);
    })->create();
