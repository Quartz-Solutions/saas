<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogsControllerTest extends TestCase
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

    public function test_index_lists_audit_logs(): void
    {
        $admin = $this->makeSuperAdmin();
        AuditLog::factory()->create(['action' => 'created']);
        AuditLog::factory()->create(['action' => 'updated']);

        $this->actingAs($admin)
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/audit/index')
                ->has('auditLogs.data', 2)
            );
    }

    public function test_index_filter_by_action(): void
    {
        $admin = $this->makeSuperAdmin();
        AuditLog::factory()->create(['action' => 'created']);
        AuditLog::factory()->create(['action' => 'updated']);

        $this->actingAs($admin)
            ->get(route('admin.audit.index', ['filter' => ['action' => 'updated']]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('auditLogs.data', 1)
                ->where('auditLogs.data.0.action', 'updated')
            );
    }

    public function test_index_filter_by_user_and_tenant(): void
    {
        $admin = $this->makeSuperAdmin();
        $actor = User::factory()->create();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        AuditLog::factory()->create([
            'action' => 'created',
            'user_id' => $actor->id,
            'tenant_id' => $tenant->id,
        ]);
        AuditLog::factory()->create(['action' => 'created']);

        $this->actingAs($admin)
            ->get(route('admin.audit.index', [
                'filter' => [
                    'user_id' => $actor->id,
                    'tenant_id' => $tenant->id,
                ],
            ]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->has('auditLogs.data', 1)
                ->where('auditLogs.data.0.user.id', $actor->id)
            );
    }

    public function test_audit_logs_blocked_for_non_admin(): void
    {
        $regular = User::factory()->create();

        $this->actingAs($regular)
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }
}
