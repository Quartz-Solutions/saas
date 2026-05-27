<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            if ($user !== null) {
                $user->unsetRelation('roles')->unsetRelation('permissions');

                if ($user->currentTenant?->id !== $tenant->id
                    && $user->tenants()->whereKey($tenant->id)->exists()
                ) {
                    $user->forceFill(['current_tenant_id' => $tenant->id])->save();
                }

                // Bump the membership's last_seen_at on every tenant-scoped
                // request. Throttle to once every 5 minutes per user/tenant
                // pair so we don't write on every navigation.
                if (! $request->isMethod('OPTIONS')) {
                    DB::table('tenant_memberships')
                        ->where('user_id', $user->id)
                        ->where('tenant_id', $tenant->id)
                        ->where(function ($q) {
                            $q->whereNull('last_seen_at')
                                ->orWhere('last_seen_at', '<', now()->subMinutes(5));
                        })
                        ->update(['last_seen_at' => now()]);
                }
            }

            $authUser = $request->user();
            $isOwner = $authUser !== null && $tenant->owner_id === $authUser->id;
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $onboardedAt = $settings['onboarded_at'] ?? null;

            Inertia::share([
                'currentTenant' => fn () => [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                    'logo_path' => $tenant->logo_path,
                    'logo_url' => $tenant->logo_path
                        ? Storage::disk('public')->url($tenant->logo_path)
                        : null,
                    'timezone' => $tenant->timezone,
                    'currency' => $tenant->currency,
                    'locale' => $tenant->locale,
                    'status' => $tenant->status,
                    'is_owner' => $isOwner,
                    'created_at' => $tenant->created_at?->toIso8601String(),
                    'onboarded_at' => $onboardedAt,
                ],
            ]);
        } else {
            Inertia::share(['currentTenant' => null]);
        }

        return $next($request);
    }
}
