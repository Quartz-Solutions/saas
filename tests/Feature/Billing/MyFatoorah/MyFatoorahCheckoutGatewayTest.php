<?php

namespace Tests\Feature\Billing\MyFatoorah;

use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\MyFatoorah\MyFatoorahGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyFatoorahCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.gateways.myfatoorah.environment', 'test');
        config()->set('billing.gateways.myfatoorah.country', 'kuwait');
        config()->set('billing.gateways.myfatoorah.api_token', 'token_test');
        config()->set('billing.gateways.myfatoorah.webhook_secret', 'whsec_test');

        Currency::firstOrCreate(
            ['code' => 'KWD'],
            ['name' => 'Kuwaiti Dinar', 'symbol' => 'KWD', 'decimal_places' => 3],
        );
    }

    public function test_initiate_checkout_persists_redirect_session_and_invoice_id(): void
    {
        Http::fake([
            'apitest.myfatoorah.com/v2/SendPayment' => Http::response([
                'IsSuccess' => true,
                'Message' => 'Invoice created successfully!',
                'Data' => [
                    'InvoiceId' => 4250,
                    'InvoiceURL' => 'https://demo.myfatoorah.com/KWT/ie/01083102494250',
                    'CustomerReference' => null,
                    'UserDefinedField' => null,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create([
            'price_cents' => 5000, // 5.000 KWD
            'currency' => 'KWD',
        ]);

        $session = CheckoutSession::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'currency' => 'KWD',
            'amount_cents' => 5000,
        ]);

        $result = app(MyFatoorahGateway::class)->initiateCheckout($session);

        $this->assertSame('redirect', $result->kind);
        $this->assertSame('4250', $result->gatewaySessionId);
        $this->assertSame('https://demo.myfatoorah.com/KWT/ie/01083102494250', $result->url);

        $session->refresh();
        $this->assertSame('myfatoorah', $session->gateway);
        $this->assertSame('4250', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_REDIRECT, $session->result_kind);
        $this->assertSame(
            'https://demo.myfatoorah.com/KWT/ie/01083102494250',
            $session->result_payload['url'] ?? null,
        );

        // Verify the major-unit decimal conversion for 3-decimal currencies:
        // 5000 fils → 5.000 KWD on the wire.
        Http::assertSent(function ($request) {
            return $request->url() === 'https://apitest.myfatoorah.com/v2/SendPayment'
                && $request['NotificationOption'] === 'LNK'
                && $request['DisplayCurrencyIso'] === 'KWD'
                && (float) $request['InvoiceValue'] === 5.0;
        });
    }

    public function test_initiate_checkout_is_idempotent_when_session_already_has_gateway_session_id(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create(['currency' => 'KWD', 'price_cents' => 5000]);

        $session = CheckoutSession::factory()
            ->awaitingPayment('myfatoorah')
            ->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'currency' => 'KWD',
                'amount_cents' => 5000,
                'gateway_session_id' => '4250',
                'result_payload' => ['url' => 'https://demo.myfatoorah.com/KWT/ie/cached'],
            ]);

        $result = app(MyFatoorahGateway::class)->initiateCheckout($session);

        $this->assertSame('4250', $result->gatewaySessionId);
        $this->assertSame('https://demo.myfatoorah.com/KWT/ie/cached', $result->url);

        Http::assertNothingSent();
    }

    public function test_handle_webhook_paid_completes_checkout_via_checkout_service(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $user->id]);
        $plan = Plan::factory()->create(['currency' => 'KWD', 'price_cents' => 5000]);

        $session = CheckoutSession::factory()
            ->awaitingPayment('myfatoorah')
            ->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'currency' => 'KWD',
                'amount_cents' => 5000,
                'gateway_session_id' => '4250',
            ]);

        // Note: existing signedString() canonicalises Invoice.Id → key `Id`
        // and Invoice.Status → key `Status`, so we mirror the same flat keys
        // in the payload Data block alongside the descriptive aliases.
        $data = [
            'Id' => 4250,
            'InvoiceId' => 4250,
            'Status' => 'Paid',
            'InvoiceStatus' => 'Paid',
            'CustomerReference' => $session->public_id,
            'UserDefinedField' => $session->public_id,
            'ExternalIdentifier' => null,
            'TransactionId' => '08102494250-1',
            'TransactionAmount' => 5.000,
            'TransactionCurrencyIso' => 'KWD',
        ];
        $payload = ['EventType' => 'TransactionsStatusChanged', 'Data' => $data];

        $signed = $this->buildSignedString([
            'Id', 'Status', 'CustomerReference', 'UserDefinedField', 'ExternalIdentifier',
        ], $data);
        $signature = base64_encode(hash_hmac('sha256', $signed, 'whsec_test', true));

        $request = Request::create(
            '/webhooks/myfatoorah',
            'POST',
            [],
            [],
            [],
            ['HTTP_MYFATOORAH_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );

        $event = WebhookEvent::factory()->create([
            'gateway' => 'myfatoorah',
            'event_type' => 'TransactionsStatusChanged',
            'payload' => $payload,
        ]);

        app(MyFatoorahGateway::class)->handleWebhook($request, $event);

        $event->refresh();
        $this->assertSame('processed', $event->status);
        $this->assertNotNull($event->processed_at);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->subscription_id);
        $this->assertNotNull($session->invoice_id);

        $subscription = Subscription::find($session->subscription_id);
        $this->assertSame('myfatoorah', $subscription->gateway);
        $this->assertSame('KWD', $subscription->currency);

        $this->assertDatabaseHas('payments', [
            'gateway' => 'myfatoorah',
            'gateway_payment_id' => '08102494250-1',
            'amount_cents' => 5000,
            'currency' => 'KWD',
        ]);
    }

    /**
     * Build the comma-separated key=value signature string the same way the
     * production gateway does — mirror, not duplicate logic.
     *
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $data
     */
    private function buildSignedString(array $keys, array $data): string
    {
        $parts = [];
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            $parts[] = $key.'='.($value === null ? '' : (string) $value);
        }

        return implode(',', $parts);
    }
}
