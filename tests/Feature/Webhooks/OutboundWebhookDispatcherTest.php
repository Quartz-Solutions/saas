<?php

namespace Tests\Feature\Webhooks;

use App\Events\TenantMemberInvited;
use App\Jobs\DeliverWebhookJob;
use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use App\Support\Webhooks\OutboundWebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutboundWebhookDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_fans_out_to_matching_active_endpoints(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $match = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['tenant.member.invited'],
            'is_active' => true,
        ]);

        // Wrong event — should be skipped.
        OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['payment.succeeded'],
            'is_active' => true,
        ]);

        // Inactive — should be skipped.
        OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['tenant.member.invited'],
            'is_active' => false,
        ]);

        $deliveries = app(OutboundWebhookDispatcher::class)
            ->dispatch('tenant.member.invited', ['foo' => 'bar'], $tenant);

        $this->assertCount(1, $deliveries);
        $this->assertSame($match->id, $deliveries[0]->outbound_webhook_id);
        Queue::assertPushed(DeliverWebhookJob::class, 1);
    }

    public function test_signature_is_hmac_sha256_of_payload_with_secret(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $webhook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['tenant.member.invited'],
            'secret' => 'whsec_test123',
        ]);

        $deliveries = app(OutboundWebhookDispatcher::class)
            ->dispatch('tenant.member.invited', ['x' => 1], $tenant);

        /** @var OutboundWebhookDelivery $delivery */
        $delivery = $deliveries[0];

        $expected = hash_hmac(
            'sha256',
            json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'whsec_test123'
        );

        $this->assertSame($expected, $delivery->signature);
    }

    public function test_listener_subscribed_to_tenant_member_invited_dispatches_webhook(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['tenant.member.invited'],
            'is_active' => true,
        ]);

        app(TenantService::class)->invite($tenant, $owner, $invitee->email, 'Member', false);

        $this->assertDatabaseCount('outbound_webhook_deliveries', 1);
        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_tenant_member_invited_event_is_dispatched(): void
    {
        Queue::fake();
        Event::fake([TenantMemberInvited::class]);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        app(TenantService::class)->invite($tenant, $owner, 'guest@example.com', 'Member', false);

        Event::assertDispatched(TenantMemberInvited::class);
    }

    public function test_delivery_job_marks_delivery_succeeded_on_2xx(): void
    {
        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);
        $webhook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['tenant.member.invited'],
        ]);

        $deliveries = app(OutboundWebhookDispatcher::class)
            ->dispatch('tenant.member.invited', ['hi' => 'there'], $tenant);

        $delivery = OutboundWebhookDelivery::find($deliveries[0]->id);
        $this->assertSame(OutboundWebhookDelivery::STATUS_SUCCEEDED, $delivery->status);
        $this->assertSame(200, (int) $delivery->response_code);
    }
}
