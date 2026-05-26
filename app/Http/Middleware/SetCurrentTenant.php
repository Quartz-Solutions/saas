<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SetCurrentTenant
{
    public function __construct(private readonly TenantResolver $resolver) {}

    /**
     * Resolve the current tenant, scope Spatie roles to that tenant, share it
     * with Inertia. Aborts 404 when the URL claims a tenant slug that does
     * not exist. Membership enforcement happens in a separate middleware so
     * test setups can choose their layering.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        $slug = $request->route('tenantSlug');

        if ($tenant === null && is_string($slug) && $slug !== '') {
            throw new NotFoundHttpException("Tenant [{$slug}] not found.");
        }

        if ($tenant !== null) {
            setPermissionsTeamId($tenant->id);

            app()->instance('currentTenant', $tenant);

            $user = $request->user();
            if ($user !== null
                && $user->currentTenant?->id !== $tenant->id
                && $user->tenants()->whereKey($tenant->id)->exists()
            ) {
                $user->forceFill(['current_tenant_id' => $tenant->id])->save();
            }

            Inertia::share([
                'currentTenant' => fn () => [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                    'logo_path' => $tenant->logo_path,
                    'timezone' => $tenant->timezone,
                    'currency' => $tenant->currency,
                    'locale' => $tenant->locale,
                    'status' => $tenant->status,
                ],
            ]);
        } else {
            Inertia::share(['currentTenant' => null]);
        }

        return $next($request);
    }
}
