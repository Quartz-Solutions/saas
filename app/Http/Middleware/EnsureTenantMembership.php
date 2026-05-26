<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Refuse the request when the authenticated user is not a member of the
 * tenant scoped by `SetCurrentTenant`. Must run AFTER `SetCurrentTenant`.
 */
class EnsureTenantMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $slug = $request->route('tenantSlug');

        if ($tenant === null) {
            if (is_string($slug) && $slug !== '') {
                throw new NotFoundHttpException("Tenant [{$slug}] not found.");
            }

            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! $user->tenants()->whereKey($tenant->id)->exists()) {
            throw new AccessDeniedHttpException('You are not a member of this tenant.');
        }

        return $next($request);
    }
}
