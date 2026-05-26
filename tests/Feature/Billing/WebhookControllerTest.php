<?php

namespace Tests\Feature\Billing;

use App\Models\WebhookEvent;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Stripe\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_gateway_returns_404(): void
    {
        $this->postJson('/webhooks/unknown', ['id' => 'evt_test'])
            ->assertNotFound();
    }

    public function test_persists_event_before_dispatch(): void
    {
        $this->registerStripeWithoutSignatureVerification();

        $payload = [
            'id' => 'evt_test_123',
            'type' => 'invoice.paid',
            'data' => ['object' => ['id' => 'in_unmatched']],
        ];

        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertOk();

        $this->assertDatabaseHas('webhook_events', [
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_test_123',
            'event_type' => 'invoice.paid',
        ]);

        $event = WebhookEvent::query()->where('gateway_event_id', 'evt_test_123')->first();
        $this->assertNotNull($event);
        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_rejects_unsigned_when_signature_required(): void
    {
        $this->registerStripeWithSignatureRequired('whsec_test');

        $response = $this->postJson('/webhooks/stripe', [
            'id' => 'evt_no_sig',
            'type' => 'invoice.paid',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'invalid_signature']);

        $this->assertDatabaseHas('webhook_events', [
            'gateway_event_id' => 'evt_no_sig',
            'status' => 'failed',
        ]);
    }

    public function test_idempotent_re_persist_on_duplicate_event_id(): void
    {
        $this->registerStripeWithoutSignatureVerification();

        $payload = [
            'id' => 'evt_dup',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => ['id' => 'sub_none']],
        ];

        $this->postJson('/webhooks/stripe', $payload)->assertOk();
        $this->postJson('/webhooks/stripe', $payload)->assertOk();

        $this->assertSame(1, WebhookEvent::query()->where('gateway_event_id', 'evt_dup')->count());

        $event = WebhookEvent::query()->where('gateway_event_id', 'evt_dup')->first();
        $this->assertGreaterThan(0, $event->processing_attempts);
    }

    private function registerStripeWithoutSignatureVerification(): void
    {
        $registry = app(GatewayRegistry::class);

        // Swap in a Stripe driver with NO webhook secret — handleWebhook
        // skips signature verification when the secret is empty, which is
        // exactly what we want for unit-level tests of the router.
        $client = $this->app->make(StripeClient::class);
        $stripe = new StripeGateway($client, '', 300);

        $registry->register($stripe);
    }

    private function registerStripeWithSignatureRequired(string $secret): void
    {
        $registry = app(GatewayRegistry::class);
        $client = $this->app->make(StripeClient::class);
        $stripe = new StripeGateway($client, $secret, 300);

        $registry->register($stripe);
    }
}
