<?php

namespace Tests\Feature\Checkout;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTenant(): array
    {
        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme '.uniqid()]);
        $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

        return [$owner, $tenant];
    }

    public function test_start_requires_authentication(): void
    {
        $this->post('/checkout/start', ['plan_slug' => 'pro'])
            ->assertRedirect('/login');
    }

    public function test_start_with_free_plan_fast_paths_to_dashboard(): void
    {
        [$user, $tenant] = $this->userWithTenant();

        $response = $this->actingAs($user)
            ->post('/checkout/start', ['plan_slug' => 'free']);

        $response->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));

        // CheckoutSession was created + immediately completed
        $session = CheckoutSession::query()
            ->where('user_id', $user->id)
            ->where('plan_id', Plan::query()->where('slug', 'free')->value('id'))
            ->firstOrFail();

        $this->assertSame(CheckoutSession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertNotNull($session->subscription_id);

        // Free Subscription created
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'unit_amount_cents' => 0,
            'checkout_session_id' => $session->id,
        ]);
    }

    public function test_start_with_paid_plan_creates_pending_session_and_redirects(): void
    {
        [$user, $tenant] = $this->userWithTenant();

        $response = $this->actingAs($user)
            ->post('/checkout/start', ['plan_slug' => 'pro']);

        $session = CheckoutSession::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(CheckoutSession::STATUS_PENDING, $session->status);
        $this->assertSame(2000, $session->amount_cents);
        $this->assertNull($session->gateway);

        $response->assertRedirect('/checkout/'.$session->public_id);
    }

    public function test_show_renders_gateway_picker(): void
    {
        [$user] = $this->userWithTenant();
        $session = $this->actingAs($user)
            ->post('/checkout/start', ['plan_slug' => 'pro']);

        $publicId = CheckoutSession::query()->where('user_id', $user->id)->value('public_id');

        $this->actingAs($user)
            ->get('/checkout/'.$publicId)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('checkout/show')
                ->where('session.public_id', $publicId)
                ->where('session.status', CheckoutSession::STATUS_PENDING)
                ->has('gateways')
            );
    }

    public function test_show_403s_for_other_users_sessions(): void
    {
        [$user] = $this->userWithTenant();
        $this->actingAs($user)->post('/checkout/start', ['plan_slug' => 'pro']);
        $publicId = CheckoutSession::query()->where('user_id', $user->id)->value('public_id');

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->get('/checkout/'.$publicId)
            ->assertStatus(403);
    }

    public function test_validation_rejects_unknown_plan(): void
    {
        [$user] = $this->userWithTenant();

        $this->actingAs($user)
            ->from('/pricing')
            ->post('/checkout/start', ['plan_slug' => 'ghost-plan'])
            ->assertRedirect('/pricing')
            ->assertSessionHasErrors('plan_slug');
    }

    public function test_show_with_canceled_query_reverts_awaiting_session_to_pending(): void
    {
        [$user] = $this->userWithTenant();
        $this->actingAs($user)->post('/checkout/start', ['plan_slug' => 'pro']);
        $session = CheckoutSession::query()->where('user_id', $user->id)->firstOrFail();

        // Simulate a driver that already initiated checkout (e.g. Stripe).
        $session->forceFill([
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_test_stale',
            'result_kind' => CheckoutSession::KIND_REDIRECT,
            'result_payload' => ['url' => 'https://checkout.stripe.com/x'],
        ])->save();

        $this->actingAs($user)
            ->get('/checkout/'.$session->public_id.'?canceled=1')
            ->assertRedirect('/checkout/'.$session->public_id);

        $session->refresh();
        $this->assertSame(CheckoutSession::STATUS_PENDING, $session->status);
        $this->assertNull($session->gateway);
        $this->assertNull($session->gateway_session_id);
        $this->assertNull($session->result_kind);
        $this->assertNull($session->result_payload);
    }

    public function test_cancel_marks_session_canceled(): void
    {
        [$user, $tenant] = $this->userWithTenant();
        $this->actingAs($user)->post('/checkout/start', ['plan_slug' => 'pro']);
        $publicId = CheckoutSession::query()->where('user_id', $user->id)->value('public_id');

        $this->actingAs($user)
            ->post('/checkout/'.$publicId.'/cancel')
            ->assertRedirect(route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]));

        $this->assertSame(
            CheckoutSession::STATUS_CANCELED,
            CheckoutSession::query()->where('public_id', $publicId)->value('status'),
        );
    }

    public function test_status_endpoint_returns_json(): void
    {
        [$user] = $this->userWithTenant();
        $this->actingAs($user)->post('/checkout/start', ['plan_slug' => 'pro']);
        $publicId = CheckoutSession::query()->where('user_id', $user->id)->value('public_id');

        $this->actingAs($user)
            ->getJson('/checkout/'.$publicId.'/status')
            ->assertOk()
            ->assertJson(['status' => CheckoutSession::STATUS_PENDING]);
    }

    public function test_return_after_completion_redirects_to_dashboard(): void
    {
        [$user, $tenant] = $this->userWithTenant();
        $this->actingAs($user)->post('/checkout/start', ['plan_slug' => 'pro']);
        $publicId = CheckoutSession::query()->where('user_id', $user->id)->value('public_id');

        // Simulate webhook completion: mark the session done.
        CheckoutSession::query()
            ->where('public_id', $publicId)
            ->update([
                'status' => CheckoutSession::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

        $this->actingAs($user)
            ->get('/checkout/'.$publicId.'/return')
            ->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));
    }

    public function test_return_before_completion_renders_processing_page(): void
    {
        [$user] = $this->userWithTenant();
        $this->actingAs($user)->post('/checkout/start', ['plan_slug' => 'pro']);
        $publicId = CheckoutSession::query()->where('user_id', $user->id)->value('public_id');

        $this->actingAs($user)
            ->get('/checkout/'.$publicId.'/return')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('checkout/processing')
                ->where('pollUrl', route('checkout.status', ['session' => $publicId]))
            );
    }

    public function test_expire_stale_job_marks_pending_sessions_expired(): void
    {
        [$user] = $this->userWithTenant();
        $this->actingAs($user)->post('/checkout/start', ['plan_slug' => 'pro']);

        // Backdate the session's expires_at to the past.
        CheckoutSession::query()->where('user_id', $user->id)->update([
            'expires_at' => now()->subMinutes(1),
        ]);

        app(CheckoutService::class)->expireStale();

        $this->assertSame(
            CheckoutSession::STATUS_EXPIRED,
            CheckoutSession::query()->where('user_id', $user->id)->value('status'),
        );
    }

    public function test_get_started_flows_through_checkout(): void
    {
        $this->seed(PlansSeeder::class);

        $this->post('/get-started', [
            'name' => 'Olivia',
            'email' => 'olivia-co@example.test',
            'password' => 'Sup3r-Strong-P@ss!',
            'password_confirmation' => 'Sup3r-Strong-P@ss!',
            'tenant_name' => 'Olivia Co',
            'plan_slug' => 'pro',
        ])->assertRedirect();

        // For a paid plan, /get-started redirects to /checkout/{public_id}
        $user = User::query()->where('email', 'olivia-co@example.test')->firstOrFail();
        $session = CheckoutSession::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(CheckoutSession::STATUS_PENDING, $session->status);
    }

    public function test_get_started_with_free_plan_skips_checkout(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->post('/get-started', [
            'name' => 'Frank',
            'email' => 'frank-free@example.test',
            'password' => 'Sup3r-Strong-P@ss!',
            'password_confirmation' => 'Sup3r-Strong-P@ss!',
            'tenant_name' => 'Frank Co',
            'plan_slug' => 'free',
        ]);

        $user = User::query()->where('email', 'frank-free@example.test')->firstOrFail();
        $tenant = Tenant::query()->where('owner_id', $user->id)->firstOrFail();

        // Lands on dashboard, not /checkout — free fast-path.
        $response->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));

        // Subscription created
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'unit_amount_cents' => 0,
        ]);
    }
}
