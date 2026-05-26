<?php

namespace Tests\Feature\Onboarding;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetStartedTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_renders_public_layout_with_plans(): void
    {
        $this->seed(PlansSeeder::class);

        $this->get('/get-started')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('onboarding/get-started')
                ->has('plans', 3)
                ->where('plans.0.slug', 'free')
            );
    }

    public function test_redirects_authenticated_users_away(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/get-started')
            ->assertRedirect();
    }

    public function test_free_plan_signup_creates_user_tenant_subscription_and_lands_on_dashboard(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->post('/get-started', [
            'name' => 'Olivia',
            'email' => 'olivia@example.test',
            'password' => 'Sup3r-Strong-P@ss!',
            'password_confirmation' => 'Sup3r-Strong-P@ss!',
            'tenant_name' => 'Olivia Co',
            'plan_slug' => 'free',
        ]);

        $tenant = Tenant::query()->where('slug', 'olivia-co')->firstOrFail();
        $response->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));

        $user = User::query()->where('email', 'olivia@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        $this->assertSame($user->id, $tenant->owner_id);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'unit_amount_cents' => 0,
        ]);
    }

    public function test_validation_rejects_missing_fields(): void
    {
        $this->seed(PlansSeeder::class);

        $this->from('/get-started')
            ->post('/get-started', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'mismatch',
                'tenant_name' => '',
                'plan_slug' => 'ghost-plan',
            ])
            ->assertRedirect('/get-started')
            ->assertSessionHasErrors(['name', 'email', 'password', 'tenant_name', 'plan_slug']);
    }

    public function test_paid_plan_with_stripe_disabled_falls_back_to_billing_page(): void
    {
        $this->seed(PlansSeeder::class);
        // Stripe disabled by default (no secret in test env).
        config(['billing.gateways.stripe.enabled' => false]);

        $response = $this->post('/get-started', [
            'name' => 'Olivia',
            'email' => 'olivia@example.test',
            'password' => 'Sup3r-Strong-P@ss!',
            'password_confirmation' => 'Sup3r-Strong-P@ss!',
            'tenant_name' => 'Olivia Co',
            'plan_slug' => 'pro',
            'gateway' => 'stripe',
        ]);

        $tenant = Tenant::query()->where('slug', 'olivia-co')->firstOrFail();
        // User + tenant get created, then we bail to /t/{slug}/billing/plans
        // with a flashed message because Stripe wasn't configured.
        $response->assertRedirect(route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]));

        $this->assertDatabaseHas('users', ['email' => 'olivia@example.test']);
        $this->assertDatabaseHas('tenants', ['slug' => 'olivia-co']);
        // No paid subscription yet — checkout never happened.
        $this->assertDatabaseMissing('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'unit_amount_cents' => 2900,
        ]);
    }

    public function test_logs_in_user_after_signup(): void
    {
        $this->seed(PlansSeeder::class);

        $this->post('/get-started', [
            'name' => 'Olivia',
            'email' => 'olivia2@example.test',
            'password' => 'Sup3r-Strong-P@ss!',
            'password_confirmation' => 'Sup3r-Strong-P@ss!',
            'tenant_name' => 'Olivia Co Two',
            'plan_slug' => 'free',
        ])->assertRedirect();

        $user = User::query()->where('email', 'olivia2@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->currentTenant->slug, $user->fresh()->currentTenant->slug);
    }

    public function test_unused_subscription_factory_assertion(): void
    {
        // Keeps Subscription model import live + sanity-checks factory.
        $sub = Subscription::factory()->make();
        $this->assertNotNull($sub->status);
    }
}
