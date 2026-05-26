<?php

namespace Tests\Feature\Schema;

use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_rules_cast_to_array(): void
    {
        $flag = FeatureFlag::factory()->create([
            'rules' => ['plans' => ['pro', 'enterprise'], 'percent_rollout' => 25],
        ]);

        $fresh = $flag->fresh();
        $this->assertIsArray($fresh->rules);
        $this->assertSame(25, $fresh->rules['percent_rollout']);
    }

    public function test_audit_log_polymorphic_to_any_model(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $log = AuditLog::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
            'action' => 'updated',
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
        ]);

        $this->assertTrue($log->auditable->is($tenant));
        $this->assertTrue($log->user->is($user));
        $this->assertSame('Old', $log->old_values['name']);
        $this->assertSame('New', $log->new_values['name']);
    }

    public function test_audit_log_user_and_tenant_can_be_null(): void
    {
        $log = AuditLog::factory()->create([
            'user_id' => null,
            'tenant_id' => null,
            'action' => 'system.cleanup',
        ]);

        $this->assertNull($log->user);
        $this->assertNull($log->tenant);
    }

    public function test_webhook_event_dedupes_on_gateway_event_id(): void
    {
        WebhookEvent::factory()->create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_123',
        ]);

        $this->expectException(QueryException::class);

        WebhookEvent::factory()->create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_123',
        ]);
    }

    public function test_webhook_event_payload_round_trips(): void
    {
        $event = WebhookEvent::factory()->create([
            'payload' => ['object' => 'subscription', 'data' => ['id' => 'sub_xyz']],
        ]);

        $fresh = $event->fresh();
        $this->assertSame('sub_xyz', $fresh->payload['data']['id']);
    }
}
