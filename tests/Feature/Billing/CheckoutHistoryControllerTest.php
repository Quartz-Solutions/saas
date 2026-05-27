<?php

namespace Tests\Feature\Billing;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function seedSessionFor($tenant, $user, string $status, string $planSlug = 'pro'): CheckoutSession
    {
        $plan = Plan::query()->where('slug', $planSlug)->firstOrFail();

        return CheckoutSession::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => $status,
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_test_'.uniqid(),
            'currency' => $plan->currency,
            'amount_cents' => $plan->price_cents,
            'expires_at' => now()->addMinutes(30),
            'completed_at' => $status === CheckoutSession::STATUS_COMPLETED ? now() : null,
        ]);
    }

    public function test_index_lists_only_current_tenants_sessions(): void
    {
        $this->seed(PlansSeeder::class);

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $this->seedSessionFor($tenant, $owner, CheckoutSession::STATUS_COMPLETED);
        $this->seedSessionFor($tenant, $owner, CheckoutSession::STATUS_AWAITING_PAYMENT);

        $strangerOwner = User::factory()->create();
        $strangerTenant = app(TenantService::class)->create($strangerOwner, ['name' => 'Other Co']);
        $this->seedSessionFor($strangerTenant, $strangerOwner, CheckoutSession::STATUS_COMPLETED);

        $this->actingAs($owner)
            ->get(route('tenants.billing.checkout-history', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('billing/checkout-history')
                ->has('sessions.data', 2)
            );
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.billing.checkout-history', ['tenantSlug' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_can_resume_is_true_for_non_terminal_with_future_expiry(): void
    {
        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $awaiting = $this->seedSessionFor($tenant, $owner, CheckoutSession::STATUS_AWAITING_PAYMENT);
        $completed = $this->seedSessionFor($tenant, $owner, CheckoutSession::STATUS_COMPLETED);

        $response = $this->actingAs($owner)
            ->get(route('tenants.billing.checkout-history', ['tenantSlug' => $tenant->slug]))
            ->assertOk();

        $data = collect($response->viewData('page')['props']['sessions']['data'])
            ->keyBy('public_id');

        $this->assertTrue($data[$awaiting->public_id]['can_resume']);
        $this->assertFalse($data[$completed->public_id]['can_resume']);
    }
}
