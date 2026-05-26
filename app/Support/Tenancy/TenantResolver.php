<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Resolves the "current tenant" from an incoming request.
 *
 * Path-based resolution (`/t/{slug}/...`) is the default in v1. Subdomain and
 * custom-domain implementations will be added in a later phase without
 * touching `SetCurrentTenant` middleware.
 */
interface TenantResolver
{
    /**
     * Return the tenant identified by the request, or null when the request
     * is not within a tenant scope (e.g. `/account/...`, `/admin/...`).
     */
    public function resolve(Request $request): ?Tenant;
}
