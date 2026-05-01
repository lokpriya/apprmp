<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'role'        => \App\Http\Middleware\EnsureRole::class,
            'ward.scope'  => \App\Http\Middleware\ScopeToWard::class,
            'data.freeze'    => \App\Http\Middleware\CheckDataFreeze::class,
            'maintenance'    => \App\Http\Middleware\CheckMaintenance::class,
            'survey.period'  => \App\Http\Middleware\CheckSurveyPeriod::class,
            'public.access'  => \App\Http\Middleware\CheckPublicAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
