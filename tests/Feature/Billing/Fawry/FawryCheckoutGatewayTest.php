<?php

namespace Tests\Feature\Billing\Fawry;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Fawry\FawryGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FawryCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('billing.gateways.fawry.merchant_code', 'MERCH_TEST');
        Config::set('billing.gateways.fawry.secure_key', 'SECURE_TEST_KEY');
        Config::set('billing.gateways.fawry.environment', 'staging');
    }

    public function test_initiate_checkout_returns_kiosk_ref_and_persists_reference(): void
    {
        Http::fake([
            'atfawry.fawrystaging.com/ECommerceWeb/Fawry/payments/charge' => Http::response([
                'type' => 'ChargeResponse',
                'referenceNumber' => '9900000123',
                'merchantRefNumber' => 'cs_test',
                'expirationTime' => 1700000000000,
                'statusCode' => 200,
                'statusDescription' => 'Operation done successfully',
            ], 200),
        ]);

        $session = $this->makeSession();
        $gateway = new FawryGateway;

        $result = $gateway->initiateCheckout($session);

        $this->assertSame(CheckoutSession::KIND_KIOSK_REF, $result->kind);
        $this->assertSame('9900000123', $result->gatewaySessionId);
        $this->assertSame('9900000123', $result->reference);
        $this->assertSame('https://www.fawry.com/howto', $result->instructionsUrl);

        $session->refresh();
        $this->assertSame('fawry', $session->gateway);
        $this->assertSame('9900000123', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_KIOSK_REF, $session->result_kind);
        $this->assertSame('9900000123', $session->result_payload['reference']);
        $this->assertSame('https://www.fawry.com/howto', $session->result_payload['instructions_url']);
    }

    public function test_handle_webhook_paid_completes_checkout(): void
    {
        $session = $this->makeSession([
            'gateway' => 'fawry',
            'gateway_session_id' => '9900000123',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => CheckoutSession::KIND_KIOSK_REF,
            'result_payload' => [
                'reference' => '9900000123',
                'instructions_url' => 'https://www.fawry.com/howto',
            ],
        ]);

        $payload = [
            'fawryRefNumber' => '9900000123',
            'merchantRefNumber' => $session->public_id,
            'paymentAmount' => 50.00,
            'orderAmount' => 50.00,
            'orderStatus' => 'PAID',
            'paymentStatus' => 'PAID',
            'paymentMethod' => 'PAYATFAWRY',
            'paymentReferenceNumber' => '8800001234',
        ];

        $concat = implode('', [
            (string) $payload['fawryRefNumber'],
            (string) $payload['merchantRefNumber'],
            number_format((float) $payload['paymentAmount'], 2, '.', ''),
            number_format((float) $payload['orderAmount'], 2, '.', ''),
            (string) $payload['orderStatus'],
            (string) $payload['paymentMethod'],
            (string) $payload['paymentReferenceNumber'],
        ]);
        $payload['signature'] = hash('sha256', $concat.'SECURE_TEST_KEY');

        $request = Request::create(
            '/webhooks/fawry',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $event = WebhookEvent::factory()->create([
            'gateway' => 'fawry',
            'event_type' => 'fawry.notification',
        ]);

        $gateway = new FawryGateway;
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
        $this->assertSame('fawry', $subscription->gateway);
        $this->assertSame('9900000123', $subscription->gateway_subscription_id);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'fawry',
            'gateway_payment_id' => '9900000123',
            'amount_cents' => 5000,
            'currency' => 'EGP',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(array $overrides = []): CheckoutSession
    {
        Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'symbol' => 'E£', 'decimal_places' => 2],
        );

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id, 'currency' => 'EGP']);
        $plan = Plan::factory()->create([
            'currency' => 'EGP',
            'price_cents' => 5000,
            'billing_period' => 'month',
            'billing_interval' => 1,
        ]);

        return CheckoutSession::factory()->create(array_merge([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'currency' => 'EGP',
            'amount_cents' => 5000,
        ], $overrides));
    }
}
