<?php

use App\Http\Middleware\ApiRateLimitHeaders;
use App\Http\Middleware\EnforcePasswordReset;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\ResolveApiTenant;
use App\Http\Middleware\SetCmsLocale;
use App\Http\Middleware\SetCurrentTenant;
use App\Http\Middleware\SetGlobalPermissionsTeam;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration as SentryIntegration;
use Sentry\SentrySdk;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'cms_locale']);

        // CMS redirects run *before* route matching so unmatched paths
        // (e.g. /old-blog) still get a 30x rather than a 404.
        $middleware->prepend(HandleRedirects::class);

        // Gateway webhooks are signed at the application layer
        // (gateway-specific HMAC) — CSRF tokens are meaningless for
        // server-to-server POSTs.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            EnforcePasswordReset::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Middleware aliases. `cms.locale` is applied per-route to the
        // public marketing surface only so authed scopes (/admin,
        // /settings, /t/*) keep their own locale handling.
        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
            'tenant.member' => EnsureTenantMembership::class,
            'api.tenant' => ResolveApiTenant::class,
            'api.rate' => ApiRateLimitHeaders::class,
            'admin.scope' => SetGlobalPermissionsTeam::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'cms.locale' => SetCmsLocale::class,
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

        // Public REST API envelope (api.md §3.3). Every unhandled error on
        // `/api/*` returns the documented JSON shape — controllers can stay
        // free of try/catch boilerplate. 5xx responses include a `trace_id`
        // (Sentry event id when configured, otherwise a generated ULID-style
        // string) so support can correlate with logs.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Let Laravel handle its own JSON-native exceptions consistently
            // (validation → 422, auth → 401, etc.). We only normalize the
            // remainder of the surface.
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
            ) {
                return null;
            }

            $status = method_exists($e, 'getStatusCode')
                ? (int) $e->getStatusCode()
                : 500;

            $payload = [
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Server error.',
            ];

            if ($status >= 500) {
                $payload['message'] = 'Server error.';
                $payload['trace_id'] = SentrySdk::getCurrentHub()->getLastEventId()
                    ?? bin2hex(random_bytes(8));
            }

            return response()->json($payload, $status);
        });
    })->create();
