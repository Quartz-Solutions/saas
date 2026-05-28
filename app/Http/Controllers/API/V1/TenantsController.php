<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Controllers\API\V1\Concerns\HandlesIdempotency;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Tenant CRUD.
 *
 * @group Tenants
 *
 * @authenticated
 */
class TenantsController extends ApiController
{
    use HandlesIdempotency;

    public function __construct(private readonly TenantService $service) {}

    /**
     * List tenants the caller can see.
     *
     * Ability: `tenants:read`.
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'tenants:read');

        $user = $this->actor($request);
        $tenants = $user->tenants()->orderBy('name')->get();

        return TenantResource::collection($tenants)->response();
    }

    /**
     * Show one tenant by slug. Ability: `tenants:read`. Requires membership.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'tenants:read');

        $tenant = $this->currentApiTenant();

        return TenantResource::make($tenant)->response();
    }

    /**
     * Create a tenant. The caller becomes the Owner.
     * Ability: `tenants:write`.
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'tenants:write');

        return $this->withIdempotency($request, function () use ($request) {
            $data = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:120'],
                'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Tenant::class, 'slug')],
                'currency' => ['nullable', 'string', 'size:3'],
                'locale' => ['nullable', 'string', 'max:8'],
                'timezone' => ['nullable', 'string', 'max:64'],
            ])->validate();

            $tenant = $this->service->create($this->actor($request), $data);

            return TenantResource::make($tenant)->response()->setStatusCode(201);
        });
    }

    /**
     * Update tenant attributes. Ability: `tenants:write`. Owner or Admin only.
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'tenants:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Tenant::class, 'slug')->ignore($tenant->id)],
            'locale' => ['sometimes', 'nullable', 'string', 'max:8'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'preferred_gateway' => ['sometimes', 'nullable', 'string', 'max:64'],
        ])->validate();

        $tenant = $this->service->update($tenant, $data);

        return TenantResource::make($tenant)->response();
    }

    /**
     * Soft-delete the tenant. Ability: `tenants:write`. Owner only.
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'tenants:write');

        $tenant = $this->currentApiTenant();

        if ($tenant->owner_id !== $this->actor($request)->id) {
            abort(403, 'Only the owner can delete the tenant.');
        }

        $this->service->softDelete($tenant);

        return response()->json([], 204);
    }

    private function assertOwnerOrAdmin(Request $request, Tenant $tenant): void
    {
        $user = $this->actor($request);

        if ($tenant->owner_id === $user->id) {
            return;
        }

        setPermissionsTeamId($tenant->id);
        if ($user->hasAnyRole(['Owner', 'Admin'])) {
            return;
        }

        abort(403, 'Owner or Admin role required.');
    }
}
