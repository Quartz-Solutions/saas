<?php

namespace Tests\Feature\Admin\Tenants;

use App\Jobs\DeliverWebhookJob;
use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebhookDeliveryRetryTest extends TestCase
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

    public function test_retry_re_queues_failed_delivery(): void
    {
        Queue::fake();

        $admin = $this->makeSuperAdmin();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $webhook = OutboundWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_id' => $owner->id,
            'url' => 'https://example.test/hook',
            'secret' => 'whsec_test',
            'events' => ['user.created'],
            'is_active' => true,
        ]);

        $delivery = OutboundWebhookDelivery::query()->create([
            'outbound_webhook_id' => $webhook->id,
            'event_type' => 'user.created',
            'event_id' => 'evt_'.uniqid(),
            'payload' => ['test' => true],
            'signature' => 'sig',
            'attempt' => 4,
            'status' => OutboundWebhookDelivery::STATUS_ABANDONED,
            'failed_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tenants.webhook-deliveries.retry', [
                'tenant' => $tenant->id,
                'delivery' => $delivery->id,
            ]))
            ->assertRedirect();

        Queue::assertPushed(DeliverWebhookJob::class, fn ($job) => $job->deliveryId === $delivery->id);

        $this->assertSame(OutboundWebhookDelivery::STATUS_PENDING, $delivery->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'admin.tenant.webhook_delivery_retried',
            'auditable_id' => $delivery->id,
        ]);
    }

    public function test_retry_rejected_when_delivery_belongs_to_other_tenant(): void
    {
        $admin = $this->makeSuperAdmin();
        $tenantA = app(TenantService::class)->create(User::factory()->create(), ['name' => 'A']);
        $tenantB = app(TenantService::class)->create(User::factory()->create(), ['name' => 'B']);

        $webhook = OutboundWebhook::query()->create([
            'tenant_id' => $tenantB->id,
            'created_by_id' => $tenantB->owner_id,
            'url' => 'https://example.test/hook',
            'secret' => 'whsec_test',
            'events' => [],
            'is_active' => true,
        ]);
        $delivery = OutboundWebhookDelivery::query()->create([
            'outbound_webhook_id' => $webhook->id,
            'event_type' => 'user.created',
            'event_id' => 'evt_'.uniqid(),
            'payload' => [],
            'signature' => 'sig',
            'attempt' => 1,
            'status' => OutboundWebhookDelivery::STATUS_FAILED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tenants.webhook-deliveries.retry', [
                'tenant' => $tenantA->id,
                'delivery' => $delivery->id,
            ]))
            ->assertNotFound();
    }

    public function test_non_super_admin_cannot_retry(): void
    {
        $regular = User::factory()->create();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $webhook = OutboundWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_id' => $owner->id,
            'url' => 'https://example.test/hook',
            'secret' => 'whsec_test',
            'events' => [],
            'is_active' => true,
        ]);
        $delivery = OutboundWebhookDelivery::query()->create([
            'outbound_webhook_id' => $webhook->id,
            'event_type' => 'user.created',
            'event_id' => 'evt_'.uniqid(),
            'payload' => [],
            'signature' => 'sig',
            'attempt' => 1,
            'status' => OutboundWebhookDelivery::STATUS_FAILED,
        ]);

        $this->actingAs($regular)
            ->post(route('admin.tenants.webhook-deliveries.retry', [
                'tenant' => $tenant->id,
                'delivery' => $delivery->id,
            ]))
            ->assertForbidden();
    }
}
