<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\ImpersonationService;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantsAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_index_lists_tenants_with_owner(): void
    {
        $admin = $this->makeSuperAdmin();
        $owner = User::factory()->create(['name' => 'Owner One']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($admin)
            ->get(route('admin.tenants.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/tenants/index')
                ->has('tenants.data', 1)
                ->where('tenants.data.0.slug', $tenant->slug)
                ->where('tenants.data.0.owner.email', $owner->email)
            );
    }

    public function test_index_filter_by_status(): void
    {
        $admin = $this->makeSuperAdmin();
        $owner = User::factory()->create();
        $active = app(TenantService::class)->create($owner, ['name' => 'Active']);
        $trial = app(TenantService::class)->create($owner, ['name' => 'Trialing']);
        $trial->forceFill(['status' => 'trialing'])->save();

        $this->actingAs($admin)
            ->get(route('admin.tenants.index', ['filter' => ['status' => 'trialing']]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('tenants.data', 1)
                ->where('tenants.data.0.slug', $trial->slug)
            );

        unset($active);
    }

    public function test_show_returns_tenant_details(): void
    {
        $admin = $this->makeSuperAdmin();
        $owner = User::factory()->create(['name' => 'Tenant Owner']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($admin)
            ->get(route('admin.tenants.show', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/tenants/show')
                ->where('tenant.slug', $tenant->slug)
                ->where('tenant.owner.name', 'Tenant Owner')
            );
    }

    public function test_impersonation_round_trip(): void
    {
        $admin = $this->makeSuperAdmin();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        // Start impersonating tenant owner.
        $this->actingAs($admin)
            ->post(route('admin.tenants.impersonate', ['tenant' => $tenant->id]))
            ->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));

        $this->assertAuthenticatedAs($owner);
        $this->assertSame($admin->id, session(ImpersonationService::SESSION_KEY));
        $this->assertDatabaseHas('impersonation_logs', [
            'impersonator_id' => $admin->id,
            'impersonated_id' => $owner->id,
            'ended_at' => null,
        ]);

        // Stop impersonating.
        $this->post(route('admin.stop-impersonating'))
            ->assertRedirect(route('admin.tenants.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(ImpersonationService::SESSION_KEY));
        $this->assertDatabaseMissing('impersonation_logs', [
            'impersonator_id' => $admin->id,
            'impersonated_id' => $owner->id,
            'ended_at' => null,
        ]);
    }

    public function test_impersonation_rejected_for_regular_user(): void
    {
        $regular = User::factory()->create();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($regular)
            ->post(route('admin.tenants.impersonate', ['tenant' => $tenant->id]))
            ->assertForbidden();

        $this->assertAuthenticatedAs($regular);
    }

    public function test_stop_impersonating_noop_when_no_session(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post(route('admin.stop-impersonating'))
            ->assertForbidden(); // FormRequest::authorize returns false.
    }
}
