<?php

use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Sentry\Laravel\Integration as SentryIntegration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
            'tenant.member' => EnsureTenantMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry: env-gated — only wires the reportable handler when a DSN is
        // configured. Without a DSN the SDK already no-ops, but explicitly
        // skipping registration keeps the exception pipeline free of Sentry
        // traces in unconfigured environments (dev, CI, test).
        if (filled(env('SENTRY_LARAVEL_DSN') ?? env('SENTRY_DSN'))) {
            SentryIntegration::handles($exceptions);
        }
    })->create();
