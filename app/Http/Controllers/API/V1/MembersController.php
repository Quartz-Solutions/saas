<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Resources\MemberResource;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant member roster.
 *
 * @group Tenant membership
 *
 * @authenticated
 */
class MembersController extends ApiController
{
    /**
     * Paginated member list. Ability: `members:read`.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'members:read');

        $tenant = $this->currentApiTenant();

        setPermissionsTeamId($tenant->id);

        $paginator = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->with(['user', 'tenant'])
            ->orderBy('joined_at', 'desc')
            ->paginate($this->perPage($request));

        return MemberResource::collection($paginator)->response();
    }

    /**
     * Change a member's role. Ability: `members:write`. Owner/Admin only.
     * Owner role is locked — use POST /transfer-ownership instead.
     */
    public function updateRole(Request $request, string $slug, int $userId): JsonResponse
    {
        $this->requireAbility($request, 'members:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        $data = Validator::make($request->all(), [
            'role' => ['required', 'string', Rule::in(TenantService::ROLES)],
        ])->validate();

        $member = $this->resolveMember($tenant, $userId);

        if ($member->id === $tenant->owner_id) {
            return response()->json([
                'message' => 'Owner role is locked. Use POST /tenants/{slug}/transfer-ownership.',
            ], 422);
        }

        setPermissionsTeamId($tenant->id);
        $member->syncRoles([$data['role']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $member->id)
            ->with(['user', 'tenant'])
            ->firstOrFail();

        return MemberResource::make($membership)->response();
    }

    /**
     * Remove a member from the tenant. Ability: `members:write`.
     * Cannot remove the owner.
     */
    public function destroy(Request $request, string $slug, int $userId): JsonResponse
    {
        $this->requireAbility($request, 'members:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        $member = $this->resolveMember($tenant, $userId);

        if ($member->id === $tenant->owner_id) {
            return response()->json([
                'message' => 'Cannot remove the workspace owner.',
            ], 422);
        }

        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $member->id)
            ->delete();

        setPermissionsTeamId($tenant->id);
        $member->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([], 204);
    }

    private function resolveMember(Tenant $tenant, int $userId): User
    {
        $user = User::query()->whereKey($userId)->first();

        if ($user === null) {
            abort(404, "User [{$userId}] not found.");
        }

        $isMember = $tenant->owner_id === $user->id
            || TenantMembership::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->exists();

        if (! $isMember) {
            abort(404, 'User is not a member of this tenant.');
        }

        return $user;
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
