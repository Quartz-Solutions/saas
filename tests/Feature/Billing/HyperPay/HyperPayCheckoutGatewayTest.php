<?php

namespace Tests\Feature\Billing\HyperPay;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\HyperPay\HyperPayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HyperPayCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookKeyHex;

    protected function setUp(): void
    {
        parent::setUp();

        // 32-byte AES-256-GCM key as 64 hex chars.
        $this->webhookKeyHex = bin2hex(random_bytes(32));

        config()->set('billing.gateways.hyperpay.environment', 'test');
        config()->set('billing.gateways.hyperpay.access_token', 'tok_test_hyperpay');
        config()->set('billing.gateways.hyperpay.entity_id_card', str_repeat('a', 32));
        config()->set('billing.gateways.hyperpay.entity_id_mada', str_repeat('b', 32));
        config()->set('billing.gateways.hyperpay.webhook_secret', $this->webhookKeyHex);
    }

    public function test_initiate_checkout_creates_session_with_widget_result(): void
    {
        Http::fake([
            'eu-test.oppwa.com/v1/checkouts' => Http::response([
                'id' => 'check_abc',
                'result' => ['code' => '000.200.100', 'description' => 'created'],
            ], 200),
        ]);

        $session = $this->makeSession(amountCents: 2900, currency: 'SAR');

        $gateway = new HyperPayGateway;
        $result = $gateway->initiateCheckout($session);

        $this->assertSame('widget', $result->kind);
        $this->assertSame('check_abc', $result->gatewaySessionId);
        $this->assertSame('https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=check_abc', $result->scriptUrl);
        $this->assertSame('VISA MASTER MADA', $result->widgetConfig['brands']);
        $this->assertStringContainsString($session->public_id, $result->widgetConfig['shopperResultUrl']);

        $fresh = $session->fresh();
        $this->assertSame('hyperpay', $fresh->gateway);
        $this->assertSame('check_abc', $fresh->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $fresh->status);
        $this->assertSame('widget', $fresh->result_kind);
        $this->assertSame(
            'https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=check_abc',
            $fresh->result_payload['script_url'],
        );
        $this->assertSame('VISA MASTER MADA', $fresh->result_payload['widget_config']['brands']);

        Http::assertSent(function ($request) use ($session) {
            return $request->url() === 'https://eu-test.oppwa.com/v1/checkouts'
                && $request['merchantTransactionId'] === $session->public_id
                && $request['amount'] === '29.00'
                && $request['currency'] === 'SAR'
                && $request['paymentType'] === 'DB'
                && $request['createRegistration'] === 'true';
        });
    }

    public function test_initiate_checkout_is_idempotent_when_session_already_awaiting_payment(): void
    {
        Http::fake();

        $session = $this->makeSession();
        $session->forceFill([
            'gateway' => 'hyperpay',
            'gateway_session_id' => 'check_existing',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => 'widget',
            'result_payload' => [
                'script_url' => 'https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=check_existing',
                'widget_config' => ['brands' => 'VISA MASTER MADA', 'shopperResultUrl' => 'x'],
            ],
            'expires_at' => now()->addMinutes(20),
        ])->save();

        $gateway = new HyperPayGateway;
        $result = $gateway->initiateCheckout($session->fresh());

        $this->assertSame('check_existing', $result->gatewaySessionId);
        Http::assertNothingSent();
    }

    public function test_handle_webhook_completes_session_on_payment_success(): void
    {
        $session = $this->makeSession(amountCents: 2900, currency: 'SAR');
        $session->forceFill([
            'gateway' => 'hyperpay',
            'gateway_session_id' => 'check_xyz',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => 'widget',
            'result_payload' => [
                'script_url' => 'https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=check_xyz',
                'widget_config' => ['brands' => 'VISA MASTER MADA', 'shopperResultUrl' => 'x'],
            ],
        ])->save();

        $payload = [
            'type' => 'PAYMENT',
            'payload' => [
                'id' => 'pay_888',
                'ndc' => 'check_xyz',
                'paymentType' => 'DB',
                'amount' => '29.00',
                'currency' => 'SAR',
                'merchantTransactionId' => $session->public_id,
                'result' => ['code' => '000.100.110', 'description' => 'Request successfully processed'],
                'registrationId' => 'reg_xyz_001',
            ],
        ];

        $request = $this->encryptedRequest($payload);
        $event = $this->makeWebhookEvent();

        $gateway = new HyperPayGateway;
        $gateway->handleWebhook($request, $event);

        $fresh = $session->fresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull($fresh->subscription_id);
        $this->assertNotNull($fresh->invoice_id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $fresh->subscription_id,
            'tenant_id' => $session->tenant_id,
            'gateway' => 'hyperpay',
            'gateway_subscription_id' => 'reg_xyz_001',
            'status' => 'active',
            'unit_amount_cents' => 2900,
        ]);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $session->tenant_id,
            'gateway' => 'hyperpay',
            'gateway_payment_id' => 'pay_888',
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

    private function makeWebhookEvent(): WebhookEvent
    {
        $event = new WebhookEvent;
        $event->forceFill([
            'gateway' => 'hyperpay',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'PAYMENT',
            'payload' => [],
            'headers' => [],
            'status' => 'received',
            'processing_attempts' => 0,
            'received_at' => now(),
        ])->save();

        return $event->fresh();
    }

    /**
     * Build an Illuminate Request whose body + headers will decrypt back
     * to the given payload through HyperPay's AES-256-GCM scheme.
     *
     * @param  array<string, mixed>  $payload
     */
    private function encryptedRequest(array $payload): Request
    {
        $key = hex2bin($this->webhookKeyHex);
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            json_encode($payload),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        $body = bin2hex($ciphertext);
        $request = Request::create('/webhooks/hyperpay', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Initialization-Vector', bin2hex($iv));
        $request->headers->set('X-Authentication-Tag', bin2hex($tag));

        return $request;
    }
}
