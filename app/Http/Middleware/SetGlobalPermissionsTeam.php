<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resets Spatie's runtime team scope to NULL so global roles (e.g.
 * Super Admin) resolve correctly. Must run BEFORE the `role:Super Admin`
 * middleware. Without this, whichever tenant_id happens to be set when
 * the request arrives leaks into the role lookup and yields false
 * negatives on global-role checks.
 */
class SetGlobalPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        setPermissionsTeamId(null);

        return $next($request);
    }
}
