<?php

namespace Tests\Feature\Billing\Geidea;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Geidea\GeideaGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeideaCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.gateways.geidea.environment', 'sandbox');
        config()->set('billing.gateways.geidea.public_key', 'pub_test_geidea');
        config()->set('billing.gateways.geidea.api_password', 'pwd_test_geidea');
    }

    public function test_initiate_checkout_creates_session_and_stores_redirect_result(): void
    {
        Http::fake([
            'api.merchant.staging.geidea.net/payment-intent/api/v2/direct/session' => Http::response([
                'session' => [
                    'id' => 'sess_geidea_123',
                    'redirectUrl' => 'https://hpp.geidea.net/checkout/sess_geidea_123',
                ],
            ], 200),
        ]);

        $session = $this->makeSession(amountCents: 2900, currency: 'SAR');

        $gateway = new GeideaGateway;
        $result = $gateway->initiateCheckout($session);

        $this->assertSame('redirect', $result->kind);
        $this->assertSame('sess_geidea_123', $result->gatewaySessionId);
        $this->assertSame('https://hpp.geidea.net/checkout/sess_geidea_123', $result->url);

        $fresh = $session->fresh();
        $this->assertSame('geidea', $fresh->gateway);
        $this->assertSame('sess_geidea_123', $fresh->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $fresh->status);
        $this->assertSame('redirect', $fresh->result_kind);
        $this->assertSame(['url' => 'https://hpp.geidea.net/checkout/sess_geidea_123'], $fresh->result_payload);

        Http::assertSent(function ($request) use ($session) {
            return $request->url() === 'https://api.merchant.staging.geidea.net/payment-intent/api/v2/direct/session'
                && $request['merchantReferenceId'] === $session->public_id
                && (float) $request['amount'] === 29.0
                && $request['currency'] === 'SAR'
                && $request['merchantPublicKey'] === 'pub_test_geidea';
        });
    }

    public function test_initiate_checkout_is_idempotent_when_session_already_awaiting_payment(): void
    {
        Http::fake();

        $session = $this->makeSession();
        $session->forceFill([
            'gateway' => 'geidea',
            'gateway_session_id' => 'sess_existing',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => 'redirect',
            'result_payload' => ['url' => 'https://hpp.geidea.net/checkout/sess_existing'],
        ])->save();

        $gateway = new GeideaGateway;
        $result = $gateway->initiateCheckout($session->fresh());

        $this->assertSame('sess_existing', $result->gatewaySessionId);
        $this->assertSame('https://hpp.geidea.net/checkout/sess_existing', $result->url);

        Http::assertNothingSent();
    }

    public function test_handle_webhook_completes_session_on_order_success(): void
    {
        $session = $this->makeSession(amountCents: 2900, currency: 'SAR');
        $session->forceFill([
            'gateway' => 'geidea',
            'gateway_session_id' => 'sess_geidea_xyz',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => 'redirect',
            'result_payload' => ['url' => 'https://hpp.geidea.net/checkout/sess_geidea_xyz'],
        ])->save();

        $payload = $this->signedPayload([
            'eventType' => 'Order.Success',
            'responseCode' => '000',
            'sessionId' => 'sess_geidea_xyz',
            'orderId' => 'order_999',
            'orderAmount' => 29.00,
            'orderCurrency' => 'SAR',
            'merchantReferenceId' => $session->public_id,
            'status' => 'Success',
            'timeStamp' => '2026-05-27T10:00:00Z',
        ]);

        $event = $this->makeWebhookEvent($payload);
        $request = Request::create('/webhooks/geidea', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');

        $gateway = new GeideaGateway;
        $gateway->handleWebhook($request, $event);

        $fresh = $session->fresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull($fresh->subscription_id);
        $this->assertNotNull($fresh->invoice_id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $fresh->subscription_id,
            'tenant_id' => $session->tenant_id,
            'gateway' => 'geidea',
            'status' => 'active',
            'unit_amount_cents' => 2900,
        ]);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $session->tenant_id,
            'gateway' => 'geidea',
            'gateway_payment_id' => 'order_999',
            'amount_cents' => 2900,
            'status' => 'succeeded',
        ]);

        $this->assertSame('processed', $event->fresh()->status);
    }

    private function makeSession(int $amountCents = 2900, string $currency = 'SAR'): CheckoutSession
    {
        Currency::firstOrCreate(
            ['code' => $currency],
            ['name' => $currency, 'symbol' => $currency, 'decimal_places' => 2],
        );

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create([
            'price_cents' => $amountCents,
            'currency' => $currency,
        ]);

        return CheckoutSession::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => $currency,
            'amount_cents' => $amountCents,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * Build a Geidea callback payload with a valid HMAC signature so
     * verifySignature() passes. Mirrors the concat order used in the driver.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function signedPayload(array $payload): array
    {
        $publicKey = (string) config('billing.gateways.geidea.public_key');
        $apiPassword = (string) config('billing.gateways.geidea.api_password');

        $signed = $publicKey
            .(string) $payload['orderAmount']
            .(string) $payload['orderCurrency']
            .(string) $payload['orderId']
            .(string) $payload['status']
            .(string) $payload['merchantReferenceId']
            .(string) $payload['timeStamp'];

        $payload['merchantPublicKey'] = $publicKey;
        $payload['signature'] = base64_encode(hash_hmac('sha256', $signed, $apiPassword, true));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeWebhookEvent(array $payload): WebhookEvent
    {
        $event = new WebhookEvent;
        $event->forceFill([
            'gateway' => 'geidea',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => (string) ($payload['eventType'] ?? 'unknown'),
            'payload' => $payload,
            'headers' => [],
            'status' => 'received',
            'processing_attempts' => 0,
            'received_at' => now(),
        ])->save();

        return $event->fresh();
    }
}
