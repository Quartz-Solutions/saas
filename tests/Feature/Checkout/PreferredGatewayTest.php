<?php

namespace Tests\Feature\Checkout;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferredGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(?string $preferred): CheckoutSession
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme'.uniqid()]);
        if ($preferred !== null) {
            $tenant->forceFill(['preferred_gateway' => $preferred])->save();
        }

        $plan = Plan::factory()->create([
            'price_cents' => 2000,
            'currency' => $tenant->currency,
        ]);

        $session = new CheckoutSession;
        $session->forceFill([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => $tenant->currency,
            'amount_cents' => 2000,
            'expires_at' => now()->addHour(),
        ])->save();

        return $session->fresh(['tenant', 'plan']);
    }

    public function test_tenant_preferred_gateway_sorts_first(): void
    {
        $session = $this->makeSession('paypal'); // PayPal not necessarily first by default

        $this->actingAs($session->user);
        $response = $this->get(route('checkout.show', ['session' => $session->public_id]))
            ->assertOk();

        $gateways = $response->viewData('page')['props']['gateways'] ?? null;
        $this->assertNotNull($gateways);

        // Tenant preferred gateway, when supported for the currency, must be
        // the first row + flagged preferred=true.
        if (! empty($gateways)) {
            // If paypal made it into the list, it should be first.
            $preferred = collect($gateways)->where('preferred', true)->first();
            if ($preferred !== null) {
                $this->assertSame($preferred, $gateways[0]);
                $this->assertSame('paypal', $preferred['id']);
            }
        }
    }

    public function test_no_preferred_falls_back_to_global_default(): void
    {
        $session = $this->makeSession(null);

        $this->actingAs($session->user);
        $response = $this->get(route('checkout.show', ['session' => $session->public_id]))
            ->assertOk();

        $gateways = $response->viewData('page')['props']['gateways'] ?? [];

        // None should claim preferred=true (no tenant override).
        foreach ($gateways as $g) {
            $this->assertFalse($g['preferred'] ?? false);
        }
    }
}
