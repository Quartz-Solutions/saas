<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/tenants
 *
 * Returns the tenants the authenticated user belongs to. Requires ability
 * `tenants:read` (or `*`).
 *
 * @group Tenants
 *
 * @authenticated
 */
class TenantsController extends Controller
{
    /**
     * List tenants visible to the authenticated user.
     *
     * @response 200 {
     *   "data": [
     *     { "id": 1, "slug": "acme", "name": "Acme Inc", "role": "Owner" }
     *   ]
     * }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        abort_unless(
            $user->tokenCan('tenants:read') || $user->tokenCan('*'),
            403,
            'Token lacks tenants:read ability.',
        );

        $tenants = $user->tenants()
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'role' => $t->owner_id === $user->id ? 'Owner' : 'Member',
                'currency' => $t->currency,
                'locale' => $t->locale,
                'timezone' => $t->timezone,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $tenants]);
    }
}
