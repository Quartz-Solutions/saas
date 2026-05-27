<?php

namespace Tests\Feature\Billing\Billplz;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Billplz\BillplzGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BillplzCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.gateways.billplz.enabled', true);
        config()->set('billing.gateways.billplz.sandbox', true);
        config()->set('billing.gateways.billplz.api_key', 'sk_test_billplz');
        config()->set('billing.gateways.billplz.collection_id', 'abc123');
        config()->set('billing.gateways.billplz.x_signature_key', 'sigkey-test');
    }

    public function test_supports_subscriptions_is_false(): void
    {
        $gateway = new BillplzGateway;

        $this->assertFalse($gateway->supportsSubscriptions());
        $this->assertSame(['MYR'], $gateway->supportedCurrencies());
    }

    public function test_initiate_checkout_for_one_time_intent_creates_bill(): void
    {
        Http::fake([
            'billplz-sandbox.com/api/v3/bills' => Http::response([
                'id' => 'bill_abc_001',
                'url' => 'https://www.billplz-sandbox.com/bills/bill_abc_001',
                'paid' => false,
                'amount' => 2900,
            ], 200),
        ]);

        $session = $this->makeSession(CheckoutSession::INTENT_ONE_TIME);

        $gateway = new BillplzGateway;
        $result = $gateway->initiateCheckout($session);

        $this->assertSame(CheckoutSession::KIND_REDIRECT, $result->kind);
        $this->assertSame('bill_abc_001', $result->gatewaySessionId);

        $session->refresh();
        $this->assertSame('billplz', $session->gateway);
        $this->assertSame('bill_abc_001', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_REDIRECT, $session->result_kind);
        $this->assertSame(['url' => 'https://www.billplz-sandbox.com/bills/bill_abc_001'], $session->result_payload);

        Http::assertSent(function ($request) use ($session) {
            return str_contains($request->url(), '/v3/bills')
                && $request['collection_id'] === 'abc123'
                && (int) $request['amount'] === (int) $session->amount_cents
                && $request['reference_1'] === $session->public_id;
        });
    }

    public function test_initiate_checkout_for_subscription_intent_throws(): void
    {
        Http::fake();

        $session = $this->makeSession(CheckoutSession::INTENT_SUBSCRIPTION);

        $gateway = new BillplzGateway;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Billplz does not support subscriptions');

        $gateway->initiateCheckout($session);
    }

    public function test_handle_webhook_on_paid_bill_routes_through_checkout_service_complete(): void
    {
        $session = $this->makeSession(CheckoutSession::INTENT_ONE_TIME);
        $session->forceFill([
            'gateway' => 'billplz',
            'gateway_session_id' => 'bill_paid_001',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => CheckoutSession::KIND_REDIRECT,
            'result_payload' => ['url' => 'https://www.billplz-sandbox.com/bills/bill_paid_001'],
        ])->save();

        // Build a Billplz-shaped form-encoded callback. Signature is computed
        // against the actual config'd x_signature_key so verifyCallback passes.
        $params = [
            'id' => 'bill_paid_001',
            'collection_id' => 'abc123',
            'paid' => 'true',
            'state' => 'paid',
            'amount' => '2900',
            'paid_amount' => '2900',
            'email' => 'buyer@example.com',
            'name' => 'Buyer',
            'transaction_id' => 'txn_xyz',
            'transaction_status' => 'completed',
        ];
        $params['x_signature'] = $this->billplzSignature($params, 'sigkey-test');

        $request = Request::create('/webhooks/billplz', 'POST', $params);

        $event = new WebhookEvent;
        $event->forceFill([
            'gateway' => 'billplz',
            'gateway_event_id' => 'bill_paid_001',
            'event_type' => 'bill.paid',
            'payload' => $params,
            'status' => 'received',
            'processing_attempts' => 0,
            'received_at' => now(),
        ])->save();

        $gateway = new BillplzGateway;
        $gateway->handleWebhook($request, $event);

        $event->refresh();
        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->subscription_id);
        $this->assertNotNull($session->invoice_id);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'billplz',
            'gateway_payment_id' => 'txn_xyz',
            'amount_cents' => 2900,
            'currency' => 'MYR',
            'status' => 'succeeded',
        ]);
    }

    private function makeSession(string $intent): CheckoutSession
    {
        Currency::firstOrCreate(
            ['code' => 'MYR'],
            ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2],
        );

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id, 'currency' => 'MYR']);
        $plan = Plan::factory()->create([
            'price_cents' => 2900,
            'currency' => 'MYR',
            'billing_period' => 'month',
        ]);

        return CheckoutSession::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => $intent,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => 'MYR',
            'amount_cents' => 2900,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /**
     * Compute the SHA-512 HMAC signature Billplz expects for callbacks
     * (sorted "key value" pairs joined by "|").
     *
     * @param  array<string, mixed>  $params
     */
    private function billplzSignature(array $params, string $key): string
    {
        unset($params['x_signature']);
        ksort($params);
        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = $k.' '.(is_scalar($v) || $v === null ? (string) $v : json_encode($v));
        }

        return hash_hmac('sha512', implode('|', $pairs), $key);
    }
}
