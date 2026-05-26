<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookEndpointsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_tenant_endpoints(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_id' => $user->id,
            'events' => ['tenant.member.invited'],
        ]);

        $this->actingAs($user)
            ->get("/t/{$tenant->slug}/settings/webhooks")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('tenants/webhooks')
                ->has('endpoints', 1)
                ->has('available_events')
            );
    }

    public function test_store_creates_endpoint_with_signing_secret(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $response = $this->actingAs($user)
            ->from("/t/{$tenant->slug}/settings/webhooks")
            ->post("/t/{$tenant->slug}/settings/webhooks", [
                'url' => 'https://example.com/hook',
                'description' => 'Primary hook',
                'events' => ['tenant.member.invited', 'payment.succeeded'],
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('outbound_webhooks', [
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/hook',
        ]);

        $secret = $response->getSession()->get('webhook_secret.plain_text');
        $this->assertIsString($secret);
        $this->assertStringStartsWith('whsec_', $secret);
    }

    public function test_store_rejects_non_https_or_invalid_url(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $this->actingAs($user)
            ->from("/t/{$tenant->slug}/settings/webhooks")
            ->post("/t/{$tenant->slug}/settings/webhooks", [
                'url' => 'ftp://nope',
                'events' => ['tenant.member.invited'],
            ])
            ->assertRedirect("/t/{$tenant->slug}/settings/webhooks")
            ->assertSessionHasErrors('url');
    }

    public function test_store_rejects_unknown_event(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $this->actingAs($user)
            ->from("/t/{$tenant->slug}/settings/webhooks")
            ->post("/t/{$tenant->slug}/settings/webhooks", [
                'url' => 'https://example.com/hook',
                'events' => ['bogus.event'],
            ])
            ->assertRedirect("/t/{$tenant->slug}/settings/webhooks")
            ->assertSessionHasErrors('events.0');
    }

    public function test_update_changes_attributes(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);
        $webhook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'events' => ['tenant.member.invited'],
            'url' => 'https://example.com/old',
        ]);

        $this->actingAs($user)
            ->patch("/t/{$tenant->slug}/settings/webhooks/{$webhook->id}", [
                'url' => 'https://example.com/new',
                'events' => ['payment.succeeded'],
                'is_active' => 0,
            ])
            ->assertRedirect();

        $webhook->refresh();
        $this->assertSame('https://example.com/new', $webhook->url);
        $this->assertSame(['payment.succeeded'], (array) $webhook->events);
        $this->assertFalse((bool) $webhook->is_active);
    }

    public function test_destroy_deletes_endpoint(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);
        $webhook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->delete("/t/{$tenant->slug}/settings/webhooks/{$webhook->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('outbound_webhooks', ['id' => $webhook->id]);
    }

    public function test_rotate_secret_changes_secret_and_reveals_it(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);
        $webhook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'secret' => 'whsec_old',
        ]);

        $response = $this->actingAs($user)
            ->post("/t/{$tenant->slug}/settings/webhooks/{$webhook->id}/rotate-secret")
            ->assertRedirect();

        $webhook->refresh();
        $this->assertNotSame('whsec_old', $webhook->secret);
        $this->assertSame($webhook->secret, $response->getSession()->get('webhook_secret.plain_text'));
    }

    public function test_test_fire_queues_a_delivery(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);
        $webhook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->post("/t/{$tenant->slug}/settings/webhooks/{$webhook->id}/test-fire")
            ->assertRedirect();

        $this->assertDatabaseHas('outbound_webhook_deliveries', [
            'outbound_webhook_id' => $webhook->id,
            'event_type' => 'test.ping',
        ]);

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_non_member_cannot_touch_webhooks(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($outsider)
            ->get("/t/{$tenant->slug}/settings/webhooks")
            ->assertForbidden();
    }
}
