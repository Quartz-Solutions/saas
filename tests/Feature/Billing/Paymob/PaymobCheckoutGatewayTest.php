<?php

namespace Tests\Feature\Billing\Paymob;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Checkout\IframeCheckout;
use App\Support\Billing\Paymob\PaymobGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PaymobCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.gateways.paymob.region' => 'eg',
            'billing.gateways.paymob.secret_key' => 'sk_test_paymob',
            'billing.gateways.paymob.public_key' => 'pk_test_paymob',
            'billing.gateways.paymob.hmac_secret' => 'hmac_test_secret',
            'billing.gateways.paymob.integration_id_card' => '12345',
        ]);

        Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'symbol' => 'E£', 'decimal_places' => 2],
        );
    }

    public function test_supports_subscriptions_is_false(): void
    {
        $gateway = new PaymobGateway;

        $this->assertFalse($gateway->supportsSubscriptions());
        $this->assertContains('EGP', $gateway->supportedCurrencies());
        $this->assertContains('USD', $gateway->supportedCurrencies());
    }

    public function test_initiate_checkout_for_one_time_creates_intention_and_iframe_session(): void
    {
        Http::fake([
            '*/v1/intention' => Http::response([
                'id' => 'int_xyz_123',
                'client_secret' => 'csec_abc_456',
            ], 200),
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create([
            'name' => 'Pro Monthly',
            'currency' => 'EGP',
            'price_cents' => 50000,
        ]);

        $session = CheckoutSession::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_ONE_TIME,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => 'EGP',
            'amount_cents' => 50000,
            'expires_at' => now()->addMinutes(30),
        ]);

        $gateway = new PaymobGateway;
        $result = $gateway->initiateCheckout($session->fresh());

        $this->assertInstanceOf(IframeCheckout::class, $result);
        $this->assertSame('int_xyz_123', $result->gatewaySessionId);
        $this->assertStringContainsString('clientSecret=csec_abc_456', $result->iframeUrl);
        $this->assertStringContainsString('publicKey=pk_test_paymob', $result->iframeUrl);

        $session->refresh();
        $this->assertSame('paymob', $session->gateway);
        $this->assertSame('int_xyz_123', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_IFRAME, $session->result_kind);
        $this->assertArrayHasKey('iframe_url', $session->result_payload);
        $this->assertStringContainsString('/unifiedcheckout/', $session->result_payload['iframe_url']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/intention')
                && $request->hasHeader('Authorization', 'Bearer sk_test_paymob');
        });
    }

    public function test_initiate_checkout_throws_for_subscription_intent(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create(['currency' => 'EGP', 'price_cents' => 50000]);

        $session = CheckoutSession::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => 'EGP',
            'amount_cents' => 50000,
            'expires_at' => now()->addMinutes(30),
        ]);

        $gateway = new PaymobGateway;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Paymob does not support subscriptions natively');

        $gateway->initiateCheckout($session->fresh());
    }

    public function test_handle_webhook_routes_successful_transaction_to_checkout_service(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create([
            'currency' => 'EGP',
            'price_cents' => 50000,
            'billing_period' => 'month',
            'billing_interval' => 1,
        ]);

        $session = CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_ONE_TIME,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'gateway' => 'paymob',
            'gateway_session_id' => 'int_xyz_123',
            'currency' => 'EGP',
            'amount_cents' => 50000,
            'result_kind' => CheckoutSession::KIND_IFRAME,
            'result_payload' => ['iframe_url' => 'https://accept.paymob.com/unifiedcheckout/?x=1'],
        ]);

        $obj = [
            'id' => 987654,
            'amount_cents' => 50000,
            'currency' => 'EGP',
            'success' => true,
            'is_voided' => false,
            'is_refunded' => false,
            'pending' => false,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_standalone_payment' => true,
            'integration_id' => 12345,
            'has_parent_transaction' => false,
            'error_occured' => false,
            'created_at' => '2026-05-27T10:00:00.000000',
            'owner' => 1,
            'order' => [
                'id' => 11223344,
                'shipping_data' => ['intention_id' => 'int_xyz_123'],
            ],
            'source_data' => [
                'pan' => '2346',
                'sub_type' => 'MasterCard',
                'type' => 'card',
            ],
            'payment_key_claims' => [
                'extra' => ['intention_id' => 'int_xyz_123'],
            ],
        ];

        $hmac = $this->computeHmac($obj, 'hmac_test_secret');

        $request = Request::create(
            "/webhooks/paymob?hmac={$hmac}",
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'TRANSACTION', 'obj' => $obj]),
        );

        $event = WebhookEvent::factory()->create([
            'gateway' => 'paymob',
            'event_type' => 'TRANSACTION',
            'payload' => $request->json()->all(),
        ]);

        $gateway = new PaymobGateway;
        $gateway->handleWebhook($request, $event);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->invoice_id);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'paymob',
            'gateway_payment_id' => '987654',
            'amount_cents' => 50000,
            'currency' => 'EGP',
        ]);

        $event->refresh();
        $this->assertSame('processed', $event->status);
        $this->assertSame('987654', $event->gateway_event_id);
    }

    /**
     * Build the HMAC-SHA512 that Paymob would attach to the callback for the
     * given `obj` payload. Mirrors the ordered field list in PaymobGateway.
     */
    private function computeHmac(array $obj, string $secret): string
    {
        $fields = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order.id',
            'owner',
            'pending',
            'source_data.pan',
            'source_data.sub_type',
            'source_data.type',
            'success',
        ];

        $concat = '';
        foreach ($fields as $path) {
            $segments = explode('.', $path);
            $cursor = $obj;
            foreach ($segments as $seg) {
                $cursor = is_array($cursor) && array_key_exists($seg, $cursor) ? $cursor[$seg] : null;
                if ($cursor === null) {
                    break;
                }
            }

            if ($cursor === null) {
                $concat .= '';
            } elseif (is_bool($cursor)) {
                $concat .= $cursor ? 'true' : 'false';
            } else {
                $concat .= (string) $cursor;
            }
        }

        return hash_hmac('sha512', $concat, $secret);
    }
}
