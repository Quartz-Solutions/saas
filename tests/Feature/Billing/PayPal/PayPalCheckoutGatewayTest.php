<?php

namespace Tests\Feature\Billing\PayPal;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\PayPal\PayPalGateway;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.gateways.paypal.enabled', true);
        config()->set('billing.gateways.paypal.mode', 'sandbox');
        config()->set('billing.gateways.paypal.client_id', 'test_client');
        config()->set('billing.gateways.paypal.client_secret', 'test_secret');
        config()->set('billing.gateways.paypal.webhook_id', 'wh_test_123');
    }

    public function test_initiate_checkout_creates_one_time_order_and_persists_redirect_result(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $plan = Plan::factory()->create([
            'price_cents' => 4900,
            'currency' => 'USD',
        ]);

        $session = CheckoutSession::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_ONE_TIME,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => 'USD',
            'amount_cents' => 4900,
            'expires_at' => now()->addMinutes(30),
        ]);

        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'tok_test',
                'expires_in' => 3600,
            ], 200),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-PP-001',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api.sandbox.paypal.com/v2/checkout/orders/ORDER-PP-001'],
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-PP-001'],
                ],
            ], 201),
        ]);

        $gateway = new PayPalGateway;
        $result = $gateway->initiateCheckout($session);

        $this->assertSame(CheckoutSession::KIND_REDIRECT, $result->kind);
        $this->assertSame('ORDER-PP-001', $result->gatewaySessionId);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-PP-001', $result->url);

        $session->refresh();
        $this->assertSame('paypal', $session->gateway);
        $this->assertSame('ORDER-PP-001', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_REDIRECT, $session->result_kind);
        $this->assertSame(
            ['url' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-PP-001'],
            $session->result_payload,
        );
    }

    public function test_handle_webhook_completes_checkout_on_order_approved(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $plan = Plan::factory()->create([
            'price_cents' => 4900,
            'currency' => 'USD',
        ]);

        $session = CheckoutSession::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_ONE_TIME,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'gateway' => 'paypal',
            'gateway_session_id' => 'ORDER-PP-001',
            'currency' => 'USD',
            'amount_cents' => 4900,
            'expires_at' => now()->addMinutes(30),
            'result_kind' => CheckoutSession::KIND_REDIRECT,
            'result_payload' => ['url' => 'https://example.test/approve'],
        ]);

        $eventBody = [
            'id' => 'WH-EVT-001',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'ORDER-PP-001',
                'status' => 'APPROVED',
                'purchase_units' => [[
                    'reference_id' => $session->public_id,
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => '49.00',
                    ],
                ]],
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '49.00',
                ],
            ],
        ];

        Http::fake([
            '*/v1/oauth2/token' => Http::response([
                'access_token' => 'tok_test',
                'expires_in' => 3600,
            ], 200),
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $request = Request::create(
            '/webhooks/paypal',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($eventBody),
        );

        $event = WebhookEvent::factory()->create([
            'gateway' => 'paypal',
            'gateway_event_id' => 'WH-EVT-001',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'payload' => $eventBody,
            'status' => 'received',
        ]);

        $gateway = new PayPalGateway;
        $processed = $gateway->handleWebhook($request, $event);

        $this->assertSame('processed', $processed->status);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->subscription_id);
        $this->assertNotNull($session->invoice_id);

        $this->assertDatabaseHas('invoices', [
            'id' => $session->invoice_id,
            'tenant_id' => $tenant->id,
            'gateway' => 'paypal',
            'amount_paid_cents' => 4900,
            'currency' => 'USD',
        ]);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $session->invoice_id,
            'gateway' => 'paypal',
            'amount_cents' => 4900,
            'status' => 'succeeded',
        ]);
    }
}
