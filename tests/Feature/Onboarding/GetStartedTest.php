<?php

namespace Tests\Feature\Onboarding;

use App\Models\CheckoutSession;
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
                ->where('selectedPlanSlug', null)
            );
    }

    public function test_show_accepts_plan_query_param_for_preselection(): void
    {
        $this->seed(PlansSeeder::class);

        $this->get('/get-started?plan=pro')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('onboarding/get-started')
                ->where('selectedPlanSlug', 'pro')
            );
    }

    public function test_show_ignores_unknown_plan_query_param(): void
    {
        $this->seed(PlansSeeder::class);

        $this->get('/get-started?plan=ghost-plan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('onboarding/get-started')
                ->where('selectedPlanSlug', null)
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

    public function test_paid_plan_creates_pending_checkout_session(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->post('/get-started', [
            'name' => 'Olivia',
            'email' => 'olivia@example.test',
            'password' => 'Sup3r-Strong-P@ss!',
            'password_confirmation' => 'Sup3r-Strong-P@ss!',
            'tenant_name' => 'Olivia Co',
            'plan_slug' => 'pro',
        ]);

        $tenant = Tenant::query()->where('slug', 'olivia-co')->firstOrFail();
        $user = User::query()->where('email', 'olivia@example.test')->firstOrFail();

        // /get-started for a paid plan creates a pending CheckoutSession and
        // redirects to /checkout/{public_id} where the user picks a gateway.
        $session = CheckoutSession::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect('/checkout/'.$session->public_id);
        $this->assertSame(CheckoutSession::STATUS_PENDING, $session->status);
        $this->assertSame($tenant->id, $session->tenant_id);
        $this->assertSame(2900, $session->amount_cents);

        // No Subscription yet — gateway hasn't been picked + paid.
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
