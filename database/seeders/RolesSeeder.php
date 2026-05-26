<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the per-team default roles (Owner, Admin, Member) for every tenant
 * that exists when the seeder runs. New tenants get them lazily via
 * TenantService::ensureTenantRoles().
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Forget cached permissions so newly-inserted roles are visible.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Tenant::query()->withTrashed()->get() as $tenant) {
            setPermissionsTeamId($tenant->id);

            foreach (TenantService::ROLES as $role) {
                Role::findOrCreate($role, 'web');
            }
        }

        // Also create a non-team-scoped global super-admin role for Phase 4.
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
    }
}
