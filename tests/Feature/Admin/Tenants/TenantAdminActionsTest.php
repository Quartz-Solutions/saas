<?php

namespace Tests\Feature\Admin\Tenants;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantAdminActionsTest extends TestCase
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

    private function makeTenant(string $name = 'Acme'): Tenant
    {
        return app(TenantService::class)->create(
            User::factory()->create(),
            ['name' => $name],
        );
    }

    public function test_suspend_marks_tenant_and_audits(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->makeTenant();

        $this->actingAs($admin)
            ->from(route('admin.tenants.show', ['tenant' => $tenant->id]))
            ->post(route('admin.tenants.suspend', ['tenant' => $tenant->id]), [
                'reason' => 'fraud',
            ])
            ->assertRedirect();

        $this->assertSame('suspended', $tenant->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'action' => 'admin.tenant.suspended',
        ]);
    }

    public function test_restore_lifts_suspension(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->makeTenant();
        $tenant->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($admin)
            ->post(route('admin.tenants.restore', ['tenantId' => $tenant->id]))
            ->assertRedirect(route('admin.tenants.show', ['tenant' => $tenant->id]));

        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'admin.tenant.restored',
        ]);
    }

    public function test_destroy_soft_deletes_tenant(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->makeTenant();

        $this->actingAs($admin)
            ->delete(route('admin.tenants.destroy', ['tenant' => $tenant->id]))
            ->assertRedirect(route('admin.tenants.index'));

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'admin.tenant.deleted',
        ]);
    }

    public function test_force_delete_requires_matching_slug(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->makeTenant();
        $tenant->delete();

        // Wrong slug fails.
        $this->actingAs($admin)
            ->delete(route('admin.tenants.force-delete', ['tenantId' => $tenant->id]), [
                'confirm_slug' => 'wrong',
            ])
            ->assertSessionHasErrors('confirm_slug');
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);

        // Correct slug succeeds.
        $this->actingAs($admin)
            ->delete(route('admin.tenants.force-delete', ['tenantId' => $tenant->id]), [
                'confirm_slug' => $tenant->slug,
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $tenant->id,
            'action' => 'admin.tenant.force_deleted',
        ]);
    }

    public function test_gdpr_export_returns_json_blob(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenant = $this->makeTenant();

        $response = $this->actingAs($admin)
            ->get(route('admin.tenants.gdpr-export', ['tenant' => $tenant->id]));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename="tenant-'.$tenant->slug.'-export.json"');
        $payload = $response->json();
        $this->assertEquals($tenant->id, $payload['tenant']['id']);
        $this->assertArrayHasKey('audit_log', $payload);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'admin.tenant.gdpr_exported',
        ]);
    }

    public function test_actions_are_forbidden_for_non_super_admins(): void
    {
        $regular = User::factory()->create();
        $tenant = $this->makeTenant();

        $this->actingAs($regular)
            ->post(route('admin.tenants.suspend', ['tenant' => $tenant->id]))
            ->assertForbidden();

        $this->actingAs($regular)
            ->delete(route('admin.tenants.destroy', ['tenant' => $tenant->id]))
            ->assertForbidden();
    }
}
