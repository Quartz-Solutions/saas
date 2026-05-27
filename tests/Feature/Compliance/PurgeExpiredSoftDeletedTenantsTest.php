<?php

namespace Tests\Feature\Compliance;

use App\Jobs\PurgeExpiredSoftDeletedTenants;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeExpiredSoftDeletedTenantsTest extends TestCase
{
    use RefreshDatabase;

    private function softDeletedTenantAgedDays(int $days): Tenant
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme'.uniqid()]);
        $tenant->delete();

        // Backdate the soft-delete timestamp via a raw update so we don't
        // depend on Eloquent timestamp munging.
        \Illuminate\Support\Facades\DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['deleted_at' => now()->subDays($days)]);

        return $tenant;
    }

    public function test_purges_tenants_soft_deleted_more_than_30_days_ago(): void
    {
        $old = $this->softDeletedTenantAgedDays(40);

        $purged = (new PurgeExpiredSoftDeletedTenants)->handle();

        $this->assertSame(1, $purged);
        $this->assertDatabaseMissing('tenants', ['id' => $old->id]);
    }

    public function test_keeps_tenants_soft_deleted_recently(): void
    {
        $recent = $this->softDeletedTenantAgedDays(5);

        $purged = (new PurgeExpiredSoftDeletedTenants)->handle();

        $this->assertSame(0, $purged);
        $this->assertSoftDeleted('tenants', ['id' => $recent->id]);
    }

    public function test_skips_tenants_that_are_not_soft_deleted(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Live']);

        (new PurgeExpiredSoftDeletedTenants)->handle();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'deleted_at' => null]);
    }

    public function test_writes_an_audit_log_entry_per_purge(): void
    {
        $tenant = $this->softDeletedTenantAgedDays(35);

        (new PurgeExpiredSoftDeletedTenants)->handle();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.tenant.gdpr_purged',
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
        ]);
    }
}
