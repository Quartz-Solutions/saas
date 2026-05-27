<?php

namespace Tests\Feature\Billing\Ipay88;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Ipay88\Ipay88Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class Ipay88CheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT_CODE = 'TEST_MC';

    private const MERCHANT_KEY = 'TEST_MK';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.gateways.ipay88.merchant_code', self::MERCHANT_CODE);
        config()->set('billing.gateways.ipay88.merchant_key', self::MERCHANT_KEY);
        config()->set('billing.gateways.ipay88.country', 'MY');
        config()->set('billing.gateways.ipay88.environment', 'sandbox');
        config()->set('billing.gateways.ipay88.signature_type', 'SHA256');
    }

    private function makeSession(int $amountCents = 2900, string $currency = 'MYR'): CheckoutSession
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
            'name' => 'Pro',
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

    public function test_initiate_checkout_persists_form_post_with_signed_params(): void
    {
        $session = $this->makeSession(2900, 'MYR');

        $gateway = new Ipay88Gateway;
        $result = $gateway->initiateCheckout($session);

        $this->assertSame(CheckoutSession::KIND_FORM_POST, $result->kind);
        $this->assertSame($session->public_id, $result->gatewaySessionId);
        $this->assertSame('https://sandbox.ipay88.com.my/epayment/entry.asp', $result->action);
        $this->assertSame('POST', $result->method);

        $params = $result->params;
        $this->assertSame(self::MERCHANT_CODE, $params['MerchantCode']);
        $this->assertSame($session->public_id, $params['RefNo']);
        $this->assertSame('29.00', $params['Amount']);
        $this->assertSame('MYR', $params['Currency']);
        $this->assertSame('SHA256', $params['SignatureType']);

        $expectedSignature = strtoupper(hash(
            'sha256',
            self::MERCHANT_KEY.self::MERCHANT_CODE.$session->public_id.'2900'.'MYR',
        ));
        $this->assertSame($expectedSignature, $params['Signature']);

        $fresh = $session->fresh();
        $this->assertSame('ipay88', $fresh->gateway);
        $this->assertSame($session->public_id, $fresh->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $fresh->status);
        $this->assertSame(CheckoutSession::KIND_FORM_POST, $fresh->result_kind);
        $this->assertSame('https://sandbox.ipay88.com.my/epayment/entry.asp', $fresh->result_payload['action']);
    }

    public function test_handle_webhook_success_completes_session_via_checkout_service(): void
    {
        $session = $this->makeSession(2900, 'MYR');
        $session->forceFill([
            'gateway' => 'ipay88',
            'gateway_session_id' => $session->public_id,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => CheckoutSession::KIND_FORM_POST,
            'result_payload' => ['action' => 'x', 'method' => 'POST', 'params' => []],
        ])->save();

        $payload = [
            'MerchantCode' => self::MERCHANT_CODE,
            'PaymentId' => '2',
            'RefNo' => $session->public_id,
            'Amount' => '29.00',
            'Currency' => 'MYR',
            'Status' => '1',
            'TransId' => 'T1234567890',
            'AuthCode' => '000000',
        ];

        $payload['Signature'] = strtoupper(hash(
            'sha256',
            self::MERCHANT_KEY
                .$payload['MerchantCode']
                .$payload['PaymentId']
                .$payload['RefNo']
                .'2900'
                .$payload['Currency']
                .$payload['Status'],
        ));

        $event = new WebhookEvent;
        $event->forceFill([
            'gateway' => 'ipay88',
            'gateway_event_id' => 'ipay88-'.$session->public_id,
            'event_type' => 'backend.success',
            'payload' => $payload,
            'headers' => [],
            'status' => 'received',
            'processing_attempts' => 0,
            'received_at' => now(),
        ])->save();

        $request = Request::create('/webhooks/ipay88', 'POST', $payload);

        (new Ipay88Gateway)->handleWebhook($request, $event->fresh());

        $fresh = $session->fresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull($fresh->subscription_id);
        $this->assertNotNull($fresh->invoice_id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $fresh->subscription_id,
            'tenant_id' => $session->tenant_id,
            'gateway' => 'ipay88',
            'unit_amount_cents' => 2900,
        ]);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $session->tenant_id,
            'gateway' => 'ipay88',
            'gateway_payment_id' => 'T1234567890',
            'amount_cents' => 2900,
            'currency' => 'MYR',
        ]);

        $this->assertSame('processed', $event->fresh()->status);
    }
}
