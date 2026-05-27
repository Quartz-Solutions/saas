<?php

namespace Tests\Feature\Billing\HitPay;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Checkout\RedirectCheckout;
use App\Support\Billing\HitPay\HitPayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HitPayCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.gateways.hitpay.api_key', 'test-key');
        config()->set('billing.gateways.hitpay.salt', 'test-salt');
        config()->set('billing.gateways.hitpay.mode', 'sandbox');

        Currency::firstOrCreate(
            ['code' => 'SGD'],
            ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2],
        );
    }

    public function test_initiate_checkout_creates_redirect_session(): void
    {
        Http::fake([
            '*payment-requests*' => Http::response([
                'id' => 'pr_test_abc123',
                'url' => 'https://sandbox.hit-pay.com/checkout/pr_test_abc123',
                'status' => 'pending',
            ], 200),
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create(['currency' => 'SGD', 'price_cents' => 4900]);

        $session = CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_ONE_TIME,
            'currency' => 'SGD',
            'amount_cents' => 4900,
        ]);

        $gateway = new HitPayGateway;
        $result = $gateway->initiateCheckout($session);

        $this->assertInstanceOf(RedirectCheckout::class, $result);
        $this->assertSame('pr_test_abc123', $result->gatewaySessionId);
        $this->assertSame('https://sandbox.hit-pay.com/checkout/pr_test_abc123', $result->url);

        $session->refresh();
        $this->assertSame('hitpay', $session->gateway);
        $this->assertSame('pr_test_abc123', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_REDIRECT, $session->result_kind);
        $this->assertSame(
            'https://sandbox.hit-pay.com/checkout/pr_test_abc123',
            $session->result_payload['url'] ?? null,
        );
    }

    public function test_initiate_checkout_is_idempotent_on_existing_session_id(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create(['currency' => 'SGD']);

        $session = CheckoutSession::factory()
            ->awaitingPayment('hitpay')
            ->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'intent' => CheckoutSession::INTENT_ONE_TIME,
                'currency' => 'SGD',
                'amount_cents' => 4900,
                'gateway_session_id' => 'pr_existing_xyz',
                'result_payload' => ['url' => 'https://sandbox.hit-pay.com/checkout/pr_existing_xyz'],
            ]);

        $gateway = new HitPayGateway;
        $result = $gateway->initiateCheckout($session);

        $this->assertSame('pr_existing_xyz', $result->gatewaySessionId);
        Http::assertNothingSent();
    }

    public function test_handle_webhook_completes_session_on_completed_status(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create(['currency' => 'SGD', 'price_cents' => 4900]);

        $session = CheckoutSession::factory()
            ->awaitingPayment('hitpay')
            ->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'intent' => CheckoutSession::INTENT_ONE_TIME,
                'currency' => 'SGD',
                'amount_cents' => 4900,
                'gateway_session_id' => 'pr_test_complete_1',
            ]);

        $payload = [
            'id' => 'pr_test_complete_1',
            'payment_id' => 'pay_charge_001',
            'status' => 'completed',
            'amount' => '49.00',
            'currency' => 'SGD',
            'reference_number' => $session->public_id,
        ];

        // Sign the payload using the legacy HMAC scheme so verify() passes.
        $signing = $payload;
        unset($signing['hmac']);
        ksort($signing);
        $concat = '';
        foreach ($signing as $k => $v) {
            $concat .= $k.'.'.$v;
        }
        $payload['hmac'] = hash_hmac('sha256', $concat, 'test-salt');

        $request = Request::create('/webhooks/hitpay', 'POST', $payload);
        $event = new WebhookEvent;
        $event->forceFill([
            'gateway' => 'hitpay',
            'gateway_event_id' => 'evt-hitpay-1',
            'event_type' => 'payment_request.completed',
            'payload' => $payload,
            'headers' => [],
            'status' => 'received',
            'processing_attempts' => 0,
            'received_at' => now(),
        ])->save();

        $gateway = new HitPayGateway;
        $gateway->handleWebhook($request, $event);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->subscription_id);
        $this->assertNotNull($session->invoice_id);
        $this->assertNotNull($session->completed_at);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'hitpay',
            'gateway_payment_id' => 'pay_charge_001',
            'amount_cents' => 4900,
        ]);

        $event->refresh();
        $this->assertSame('processed', $event->status);
    }
}
