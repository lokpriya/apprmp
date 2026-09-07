<?php

/**
 * Sentry Laravel SDK configuration.
 *
 * Tuned for two constraints that the stock config does not assume:
 *
 * 1. PRIVACY. This app holds household survey PII collected by local
 *    governments. Nothing identifying may leave the server, so PII is off and
 *    every event passes through App\Support\SentryScrubber, which drops request
 *    bodies, query strings and cookies outright.
 *
 * 2. SHARED HOSTING. All deployments run on a CloudLinux cPanel account with
 *    per-account CPU, memory and Entry Process limits. The Sentry PHP transport
 *    sends over HTTP synchronously, so anything sent on a *successful* request
 *    is pure overhead charged against those limits. Performance tracing,
 *    profiling and metrics are therefore all disabled: only actual errors are
 *    transmitted. Timeouts are short so a Sentry outage degrades into a lost
 *    report rather than a hung request.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // Leave SENTRY_LARAVEL_DSN unset to disable reporting entirely (the SDK
    // becomes a no-op). That is the correct state for local development.
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Set per deployment so a report says which site it came from: the four
    // installations share one codebase and their bugs are rarely the same bug.
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT'),

    'org_id' => env('SENTRY_ORG_ID') === null ? null : (int) env('SENTRY_ORG_ID'),

    // Report every error. The free tier allows 5,000 events a month; if that is
    // ever exhausted, fix the noisiest error rather than lowering this.
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    // ── Per-request overhead: all disabled ───────────────────────────────────
    // These sample *successful* requests, not just failures. On a shared account
    // with Entry Process limits that cost is paid on every page view.
    'traces_sample_rate' => 0.0,
    'profiles_sample_rate' => 0.0,
    'enable_logs' => false,
    'enable_metrics' => false,

    'strict_trace_continuation' => false,
    'log_flush_threshold' => env('SENTRY_LOG_FLUSH_THRESHOLD') === null ? null : (int) env('SENTRY_LOG_FLUSH_THRESHOLD'),
    'logs_channel_level' => env('SENTRY_LOG_LEVEL', env('LOG_LEVEL', 'error')),

    // Never attach user emails, names, IP addresses or request bodies.
    // Hard-coded rather than env-driven: this is a data-protection obligation,
    // not a setting someone should be able to flip from a .env file.
    'send_default_pii' => false,

    /**
     * Final scrub before transmission. Must stay a [class, method] array and not
     * a closure — production runs `php artisan config:cache`, which cannot
     * serialise closures and would fail the deploy.
     */
    'before_send' => [\App\Support\SentryScrubber::class, 'handle'],

    /**
     * Expected conditions, not defects. Reporting these would bury real errors
     * and burn the monthly quota: a 404 from a crawler and a rejected form are
     * both normal traffic.
     */
    'ignore_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException::class,
    ],

    'ignore_transactions' => [
        '/up',
        '/healthz',
    ],

    /**
     * Breadcrumbs are attached to an error report, so they cost nothing on a
     * successful request. SQL *statements* are kept because they are how you
     * find a broken query; SQL *bindings* stay off because those are the actual
     * household values.
     */
    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
        'notifications' => false,
    ],

    // Tracing is off via traces_sample_rate above; these are belt and braces so
    // that enabling tracing later is a deliberate act rather than an accident.
    'tracing' => [
        'queue_job_transactions' => false,
        'queue_jobs' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'sql_origin' => false,
        'views' => false,
        'livewire' => false,
        'http_client_requests' => false,
        'cache' => false,
        'redis_commands' => false,
        'notifications' => false,
        'missing_routes' => false,
        'continue_after_response' => false,
        'default_integrations' => false,
    ],

    /**
     * A Sentry outage must never become an outage here. The transport is
     * synchronous, so without these a slow ingest endpoint would hold a PHP
     * worker open and eat into the account's Entry Process allowance — the exact
     * resource that took the sites down for 48 hours.
     */
    'http_connect_timeout' => 2,
    'http_timeout' => 2,

];
