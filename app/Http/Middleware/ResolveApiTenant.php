<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolve the tenant for `/api/v1/tenants/{slug}/...` and enforce membership.
 *
 * Mirrors the SPA `SetCurrentTenant` + `EnsureTenantMembership` pair, but
 * sourced from the `slug` API route parameter instead of `tenantSlug`. Aborts
 * 404 if the slug doesn't exist (or the user can't see it), 403 if the user
 * is authenticated but not a member.
 */
class ResolveApiTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');

        if (! is_string($slug) || $slug === '') {
            throw new NotFoundHttpException('Tenant slug missing.');
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            throw new NotFoundHttpException("Resource [tenants/{$slug}] not found.");
        }

        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        $isMember = $tenant->owner_id === $user->id
            || $tenant->memberships()->where('user_id', $user->id)->exists();

        if (! $isMember) {
            throw new AccessDeniedHttpException('You are not a member of this tenant.');
        }

        setPermissionsTeamId($tenant->id);
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
