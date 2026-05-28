<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\MemberRemoveRequest;
use App\Http\Requests\Tenants\MemberRoleUpdateRequest;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant-scope member roster + RBAC editor.
 *
 * Mounts under /t/{tenantSlug}/members. The owner (and tenant Admins) can:
 *   - View every member, their role, joined date, last seen.
 *   - Change a member's role (Owner / Admin / Member). The current owner's
 *     role is locked — ownership moves via the transfer flow, not by demote.
 *   - Remove a member from the tenant (cannot remove the owner).
 *
 * Spatie role assignment is team-scoped to the tenant via
 * `setPermissionsTeamId($tenant->id)`.
 */
class MembersController extends Controller
{
    public function __construct(private readonly TenantService $service) {}

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant();
        $user = $request->user();
        $isOwner = $user !== null && $tenant->owner_id === $user->id;

        setPermissionsTeamId($tenant->id);

        $memberships = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->with('user:id,name,email,avatar_path,last_login_at,suspended_at')
            ->orderBy('joined_at', 'desc')
            ->get();

        $rows = $memberships->map(function (TenantMembership $m) use ($tenant) {
            $u = $m->user;
            $isTenantOwner = $u?->id === $tenant->owner_id;

            return [
                'membership_id' => $m->id,
                'user_id' => $u?->id,
                'name' => $u?->name,
                'email' => $u?->email,
                'avatar_path' => $u?->avatar_path,
                'last_login_at' => $u?->last_login_at?->toIso8601String(),
                'suspended' => $u?->suspended_at !== null,
                'is_owner' => $isTenantOwner,
                'joined_at' => $m->joined_at?->toIso8601String(),
                // Role from Spatie — pivot already team-scoped above.
                'role' => $isTenantOwner
                    ? 'Owner'
                    : ($u?->getRoleNames()->first() ?? 'Member'),
            ];
        })->values()->all();

        return Inertia::render('tenants/members', [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'owner_id' => $tenant->owner_id,
            ],
            'members' => $rows,
            'roles' => TenantService::ROLES,
            'isOwner' => $isOwner,
            'currentUserId' => $user?->id,
        ]);
    }

    public function updateRole(MemberRoleUpdateRequest $request, string $tenantSlug, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant();

        // Owner role is locked — the owner can only change via transfer flow.
        if ($user->id === $tenant->owner_id) {
            return $this->flashBack(__('Use Transfer ownership to change the owner.'), 'error');
        }

        if (! $this->isMember($tenant, $user)) {
            abort(404);
        }

        $newRole = (string) $request->input('role');

        setPermissionsTeamId($tenant->id);
        // Strip any existing tenant-scoped role then assign the new one.
        // Spatie syncRoles is per-team thanks to setPermissionsTeamId.
        $user->syncRoles([$newRole]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->flashBack(__('Role updated.'));
    }

    public function destroy(MemberRemoveRequest $request, string $tenantSlug, User $user): RedirectResponse
    {
        $tenant = $this->currentTenant();

        if ($user->id === $tenant->owner_id) {
            return $this->flashBack(__('Cannot remove the workspace owner.'), 'error');
        }

        if ($user->id === $request->user()?->id) {
            return $this->flashBack(__('Use "Leave workspace" to remove yourself.'), 'error');
        }

        if (! $this->isMember($tenant, $user)) {
            abort(404);
        }

        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->delete();

        setPermissionsTeamId($tenant->id);
        $user->syncRoles([]); // drop tenant-scoped roles
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->flashBack(__('Member removed.'));
    }

    private function currentTenant(): Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }

    private function isMember(Tenant $tenant, User $user): bool
    {
        return TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function flashBack(string $message, string $type = 'success'): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
