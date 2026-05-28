<?php

namespace App\Http\Controllers\API\V1\Concerns;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Base for every `/api/v1/*` controller.
 *
 * Adds two helpers controllers reach for on every action:
 *  - requireAbility() — enforces the per-route ability scope, honoring the `*`
 *    wildcard and the configured ability catalog.
 *  - currentApiTenant() — resolves the tenant bound by the `api.tenant`
 *    middleware, or 404s.
 */
abstract class ApiController extends Controller
{
    /**
     * Abort 403 unless the calling token has the given ability (or `*`).
     */
    protected function requireAbility(Request $request, string $ability): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->tokenCan($ability) || $user->tokenCan('*')) {
            return;
        }

        throw new AccessDeniedHttpException("Token lacks {$ability} ability.");
    }

    /**
     * Resolve the tenant bound by `api.tenant` middleware. Throws 404 if not.
     */
    protected function currentApiTenant(): Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException('Tenant not found.');
        }

        return $tenant;
    }

    /**
     * Authenticated user (never null inside `auth:sanctum`).
     */
    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $user;
    }

    /**
     * Per-page sanitizer shared by every list endpoint. Cap at 100.
     */
    protected function perPage(Request $request, int $default = 25): int
    {
        $perPage = (int) $request->integer('per_page', $default);

        return max(1, min($perPage, 100));
    }
}
