<?php

namespace Tests\Feature\Billing\Aps;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Aps\ApsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApsCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function configureAps(): void
    {
        config()->set('billing.gateways.aps.environment', 'sandbox');
        config()->set('billing.gateways.aps.merchant_identifier', 'MERCH123');
        config()->set('billing.gateways.aps.access_code', 'AC123');
        config()->set('billing.gateways.aps.sha_request_phrase', 'REQ_PHRASE');
        config()->set('billing.gateways.aps.sha_response_phrase', 'RES_PHRASE');
        config()->set('billing.gateways.aps.sha_type', 'sha256');
    }

    private function makeSession(int $amountCents = 2900, string $currency = 'USD'): CheckoutSession
    {
        Currency::firstOrCreate(
            ['code' => $currency],
            ['name' => $currency, 'symbol' => $currency, 'decimal_places' => $currency === 'KWD' ? 3 : 2],
        );

        $user = User::factory()->create(['email' => 'buyer@example.test']);
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create([
            'price_cents' => $amountCents,
            'currency' => $currency,
        ]);

        return CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'currency' => $currency,
            'amount_cents' => $amountCents,
        ]);
    }

    public function test_initiate_checkout_persists_form_post_session_with_signed_params(): void
    {
        $this->configureAps();
        $session = $this->makeSession(2900, 'USD');

        $gateway = app(ApsGateway::class);
        $result = $gateway->initiateCheckout($session);

        $session->refresh();
        $this->assertSame('aps', $session->gateway);
        $this->assertSame($session->public_id, $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_FORM_POST, $session->result_kind);

        $payload = $session->result_payload;
        $this->assertSame('https://sbcheckout.payfort.com/FortAPI/paymentPage', $payload['action']);
        $this->assertSame('POST', $payload['method']);

        $params = $payload['params'];
        $this->assertSame('PURCHASE', $params['service_command']);
        $this->assertSame('MERCH123', $params['merchant_identifier']);
        $this->assertSame('AC123', $params['access_code']);
        $this->assertSame($session->public_id, $params['merchant_reference']);
        $this->assertSame('2900', $params['amount']); // USD: cents == minor units
        $this->assertSame('USD', $params['currency']);
        $this->assertArrayHasKey('signature', $params);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $params['signature']);

        // The result object surfaces the same data the front-end will render.
        $this->assertSame(CheckoutSession::KIND_FORM_POST, $result->kind);
        $this->assertSame($session->public_id, $result->gatewaySessionId);
    }

    public function test_initiate_checkout_uses_three_decimal_amount_for_kwd(): void
    {
        $this->configureAps();
        $session = $this->makeSession(1000, 'KWD'); // 10.00 KWD stored as 1000 cents

        $gateway = app(ApsGateway::class);
        $gateway->initiateCheckout($session);

        $session->refresh();
        $params = $session->result_payload['params'];
        // 3-decimal currency: cents (×100) re-scaled to minor units (×1000) → ×10.
        $this->assertSame('10000', $params['amount']);
    }

    public function test_handle_webhook_routes_signed_success_through_checkout_service(): void
    {
        $this->configureAps();
        $session = $this->makeSession(2900, 'USD');

        // Move the session to awaiting_payment as initiateCheckout would.
        app(ApsGateway::class)->initiateCheckout($session);
        $session->refresh();

        // Build an APS notification payload + sign it with the response phrase.
        $payload = [
            'response_code' => '14000',
            'command' => 'PURCHASE',
            'merchant_reference' => $session->public_id,
            'fort_id' => '169000000000123456',
            'amount' => '2900',
            'currency' => 'USD',
            'merchant_identifier' => 'MERCH123',
            'access_code' => 'AC123',
        ];
        $payload['signature'] = $this->signResponse($payload, 'RES_PHRASE');

        $event = WebhookEvent::factory()->create([
            'gateway' => 'aps',
            'event_type' => 'aps.notification',
            'payload' => $payload,
            'status' => 'received',
        ]);

        $request = Request::create('/webhooks/aps', 'POST', $payload);

        $gateway = app(ApsGateway::class);
        $event = $gateway->handleWebhook($request, $event);

        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->subscription_id);
        $this->assertNotNull($session->invoice_id);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'aps',
            'gateway_payment_id' => '169000000000123456',
            'amount_cents' => 2900,
            'currency' => 'USD',
        ]);
    }

    /**
     * Mirror ApsGateway::signRequest using the response phrase. Mirrors the
     * filter rules: drop `signature` + empty values, sort ascending, concat
     * key=value with no separator, wrap with phrase on both ends.
     *
     * @param  array<string, mixed>  $params
     */
    private function signResponse(array $params, string $phrase): string
    {
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($k === 'signature' || $v === null || $v === '') {
                continue;
            }
            $filtered[(string) $k] = is_bool($v) ? ($v ? '1' : '0') : (string) $v;
        }
        ksort($filtered, SORT_STRING);
        $concat = '';
        foreach ($filtered as $k => $v) {
            $concat .= $k.'='.$v;
        }

        return hash('sha256', $phrase.$concat.$phrase);
    }
}
