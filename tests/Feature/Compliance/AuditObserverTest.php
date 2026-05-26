<?php

namespace Tests\Feature\Compliance;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_update_records_diff(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id, 'name' => 'Acme']);

        AuditLog::query()->delete();

        $tenant->update(['name' => 'Acme Inc.']);

        $log = AuditLog::query()
            ->where('auditable_type', Tenant::class)
            ->where('auditable_id', $tenant->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($log, 'Expected an audit log row for tenant update');
        $this->assertSame(['name' => 'Acme'], $log->old_values);
        $this->assertSame(['name' => 'Acme Inc.'], $log->new_values);
    }

    public function test_tenant_create_records_event(): void
    {
        AuditLog::query()->delete();

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', Tenant::class)
                ->where('auditable_id', $tenant->id)
                ->where('action', 'created')
                ->exists(),
        );
    }

    public function test_user_update_filters_to_auditable_fields(): void
    {
        $user = User::factory()->create(['name' => 'Old']);
        AuditLog::query()->delete();

        // password is filtered out by globalSkip; locale is filtered by allowList
        $user->update([
            'name' => 'New',
            'locale' => 'fr',
        ]);

        $log = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertArrayNotHasKey('locale', $log->new_values);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }
}
