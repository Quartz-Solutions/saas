<?php

namespace Tests\Feature\Billing\PayTabs;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\PayTabs\PayTabsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayTabsCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('billing.gateways.paytabs.profile_id', '12345');
        Config::set('billing.gateways.paytabs.server_key', 'SK-TEST-KEY');
        Config::set('billing.gateways.paytabs.region', 'SAU');
    }

    public function test_initiate_checkout_stores_tran_ref_and_returns_redirect(): void
    {
        Http::fake([
            'secure.paytabs.sa/payment/request' => Http::response([
                'tran_ref' => 'TST2020901234567',
                'redirect_url' => 'https://secure.paytabs.sa/payment/page/abc',
            ], 200),
        ]);

        $session = $this->makeSession();
        $gateway = new PayTabsGateway;

        $result = $gateway->initiateCheckout($session);

        $this->assertSame(CheckoutSession::KIND_REDIRECT, $result->kind);
        $this->assertSame('TST2020901234567', $result->gatewaySessionId);
        $this->assertSame('https://secure.paytabs.sa/payment/page/abc', $result->url);

        $session->refresh();
        $this->assertSame('paytabs', $session->gateway);
        $this->assertSame('TST2020901234567', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_REDIRECT, $session->result_kind);
        $this->assertSame(
            ['url' => 'https://secure.paytabs.sa/payment/page/abc'],
            $session->result_payload,
        );
    }

    public function test_handle_webhook_authorised_completes_checkout(): void
    {
        $session = $this->makeSession([
            'gateway' => 'paytabs',
            'gateway_session_id' => 'TST2020901234567',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => CheckoutSession::KIND_REDIRECT,
            'result_payload' => ['url' => 'https://secure.paytabs.sa/payment/page/abc'],
        ]);

        $payload = [
            'tran_ref' => 'TST2020901234567',
            'cart_id' => $session->public_id,
            'cart_amount' => '29.00',
            'cart_currency' => 'SAR',
            'payment_result' => [
                'response_status' => 'A',
                'response_code' => '100',
                'response_message' => 'Authorised',
            ],
        ];

        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, 'SK-TEST-KEY');

        $request = Request::create(
            '/webhooks/paytabs',
            'POST',
            [],
            [],
            [],
            ['HTTP_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $rawBody,
        );

        $event = WebhookEvent::factory()->create([
            'gateway' => 'paytabs',
            'event_type' => 'paytabs.ipn',
        ]);

        $gateway = new PayTabsGateway;
        $event = $gateway->handleWebhook($request, $event);

        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->subscription_id);
        $this->assertNotNull($session->invoice_id);

        $subscription = Subscription::find($session->subscription_id);
        $this->assertNotNull($subscription);
        $this->assertSame('paytabs', $subscription->gateway);
        $this->assertSame('TST2020901234567', $subscription->gateway_subscription_id);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'paytabs',
            'gateway_payment_id' => 'TST2020901234567',
            'amount_cents' => 2900,
            'currency' => 'SAR',
        ]);
    }

    private function makeSession(array $overrides = []): CheckoutSession
    {
        Currency::firstOrCreate(
            ['code' => 'SAR'],
            ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'decimal_places' => 2],
        );

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id, 'currency' => 'SAR']);
        $plan = Plan::factory()->create([
            'currency' => 'SAR',
            'price_cents' => 2900,
            'billing_period' => 'month',
            'billing_interval' => 1,
        ]);

        return CheckoutSession::factory()->create(array_merge([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'currency' => 'SAR',
            'amount_cents' => 2900,
        ], $overrides));
    }
}
