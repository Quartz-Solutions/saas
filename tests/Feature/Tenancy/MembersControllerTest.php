<?php

namespace Tests\Feature\Tenancy;

use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MembersControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ensureRoles(int $tenantId): void
    {
        setPermissionsTeamId($tenantId);
        Role::findOrCreate('Owner', 'web');
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Member', 'web');
    }

    public function test_index_lists_tenant_members(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $this->ensureRoles($tenant->id);
        $member = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('tenants.members.index', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('tenants/members')
                ->where('isOwner', true)
                ->has('members', 2), // owner + manually added member
            );
    }

    public function test_owner_can_change_member_role(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $this->ensureRoles($tenant->id);
        $member = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'joined_at' => now(),
        ]);
        setPermissionsTeamId($tenant->id);
        $member->assignRole('Member');

        $this->actingAs($owner)
            ->patch(route('tenants.members.role', [
                'tenantSlug' => $tenant->slug,
                'user' => $member->id,
            ]), ['role' => 'Admin'])
            ->assertRedirect();

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');
        $this->assertTrue($member->hasRole('Admin'));
    }

    public function test_owner_role_is_locked(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $this->ensureRoles($tenant->id);

        $this->actingAs($owner)
            ->patch(route('tenants.members.role', [
                'tenantSlug' => $tenant->slug,
                'user' => $owner->id,
            ]), ['role' => 'Member'])
            ->assertRedirect();

        setPermissionsTeamId($tenant->id);
        $owner->unsetRelation('roles');
        $this->assertTrue($owner->hasRole('Owner'));
    }

    public function test_non_owner_cannot_change_roles(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $this->ensureRoles($tenant->id);
        $member = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($member)
            ->patch(route('tenants.members.role', [
                'tenantSlug' => $tenant->slug,
                'user' => $member->id,
            ]), ['role' => 'Admin'])
            ->assertForbidden();
    }

    public function test_owner_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $this->ensureRoles($tenant->id);
        $member = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->delete(route('tenants.members.destroy', [
                'tenantSlug' => $tenant->slug,
                'user' => $member->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_owner_cannot_remove_themselves(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $this->ensureRoles($tenant->id);

        $this->actingAs($owner)
            ->delete(route('tenants.members.destroy', [
                'tenantSlug' => $tenant->slug,
                'user' => $owner->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
        ]);
    }
}
