<?php

namespace Tests\Feature\Billing\Telr;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Billing\Checkout\RedirectCheckout;
use App\Support\Billing\Telr\TelrGateway;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelrCheckoutGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.gateways.telr.store_id' => '12345',
            'billing.gateways.telr.auth_key' => 'test_auth_key',
            'billing.gateways.telr.ipn_secret' => 'shh',
            'billing.gateways.telr.test_mode' => 1,
        ]);
    }

    /** @return array{0: User, 1: Tenant, 2: Plan, 3: CheckoutSession} */
    private function seedSession(): array
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme '.uniqid()]);
        $plan = Plan::factory()->create(['price_cents' => 2900, 'currency' => 'USD', 'name' => 'Pro']);

        $session = CheckoutSession::factory()->create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'currency' => 'USD',
            'amount_cents' => 2900,
        ]);

        return [$owner, $tenant, $plan, $session];
    }

    public function test_initiate_checkout_creates_redirect_result_and_persists_order_ref(): void
    {
        [, , , $session] = $this->seedSession();

        Http::fake([
            'secure.telr.com/*' => Http::response([
                'order' => [
                    'ref' => 'ORD-ABC123',
                    'url' => 'https://secure.telr.com/gateway/process.html?o=ORD-ABC123',
                ],
            ], 200),
        ]);

        $result = (new TelrGateway)->initiateCheckout($session->fresh());

        $this->assertInstanceOf(RedirectCheckout::class, $result);
        $this->assertSame('ORD-ABC123', $result->gatewaySessionId);

        $session->refresh();
        $this->assertSame('telr', $session->gateway);
        $this->assertSame('ORD-ABC123', $session->gateway_session_id);
        $this->assertSame(CheckoutSession::STATUS_AWAITING_PAYMENT, $session->status);
        $this->assertSame(CheckoutSession::KIND_REDIRECT, $session->result_kind);
        $this->assertSame(
            'https://secure.telr.com/gateway/process.html?o=ORD-ABC123',
            $session->result_payload['url'] ?? null,
        );
    }

    public function test_handle_webhook_authorised_completes_session_via_checkout_service(): void
    {
        [, $tenant, $plan, $session] = $this->seedSession();

        // Put the session in awaiting_payment with a known order ref.
        $session->forceFill([
            'gateway' => 'telr',
            'gateway_session_id' => 'ORD-ABC123',
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => CheckoutSession::KIND_REDIRECT,
            'result_payload' => ['url' => 'https://secure.telr.com/x'],
        ])->save();

        $payload = [
            'tran_store' => '12345',
            'tran_type' => 'sale',
            'tran_class' => 'paypage',
            'tran_test' => '1',
            'tran_ref' => 'ORD-ABC123',
            'tran_prevref' => '',
            'tran_firstref' => '',
            'tran_currency' => 'USD',
            'tran_amount' => '29.00',
            'tran_cartid' => $session->public_id,
            'tran_desc' => 'Pro',
            'tran_status' => 'A',
            'tran_authcode' => '123456',
            'tran_authmessage' => 'Approved',
        ];

        $payload['tran_check'] = sha1(implode(':', array_merge(
            ['shh'],
            [
                $payload['tran_store'],
                $payload['tran_type'],
                $payload['tran_class'],
                $payload['tran_test'],
                $payload['tran_ref'],
                $payload['tran_prevref'],
                $payload['tran_firstref'],
                $payload['tran_currency'],
                $payload['tran_amount'],
                $payload['tran_cartid'],
                $payload['tran_desc'],
                $payload['tran_status'],
                $payload['tran_authcode'],
                $payload['tran_authmessage'],
            ],
        )));

        $event = WebhookEvent::query()->create([
            'gateway' => 'telr',
            'gateway_event_id' => 'telr-'.$session->public_id,
            'event_type' => 'tran.A',
            'payload' => $payload,
            'status' => 'pending',
            'received_at' => now(),
        ]);

        $request = Request::create('/webhooks/telr', 'POST', $payload);

        (new TelrGateway)->handleWebhook($request, $event);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->subscription_id);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => 'telr',
            'checkout_session_id' => $session->id,
        ]);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'gateway' => 'telr',
            'gateway_payment_id' => 'ORD-ABC123',
            'amount_cents' => 2900,
            'currency' => 'USD',
        ]);
    }
}
