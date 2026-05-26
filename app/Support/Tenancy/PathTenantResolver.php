<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Resolves the current tenant from the URL path.
 *
 * Expects routes mounted under `/t/{tenantSlug}/...` — the slug is taken from
 * the named `tenantSlug` route parameter. Falls back to the first path segment
 * when the route parameter is unavailable (e.g. before the route is matched).
 */
class PathTenantResolver implements TenantResolver
{
    public function resolve(Request $request): ?Tenant
    {
        $slug = $request->route('tenantSlug');

        if (! is_string($slug) || $slug === '') {
            $segments = $request->segments();

            // /t/{slug}/...
            if (count($segments) >= 2 && $segments[0] === 't') {
                $slug = $segments[1];
            }
        }

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }
}
