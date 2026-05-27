<?php

namespace Tests\Feature\Admin;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckoutSessionsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function seedSession(string $status = CheckoutSession::STATUS_AWAITING_PAYMENT): CheckoutSession
    {
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme '.uniqid()]);

        return CheckoutSession::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => $status,
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_test_'.uniqid(),
            'currency' => $plan->currency,
            'amount_cents' => $plan->price_cents,
            'result_kind' => CheckoutSession::KIND_REDIRECT,
            'result_payload' => ['url' => 'https://checkout.stripe.com/x'],
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function test_index_requires_super_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/checkout-sessions')->assertStatus(403);
    }

    public function test_index_lists_sessions(): void
    {
        $this->seedSession();
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/checkout-sessions')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/checkout-sessions/index')
                ->has('sessions.data', 1)
                ->where('sessions.data.0.status', CheckoutSession::STATUS_AWAITING_PAYMENT)
                ->has('stats.awaiting_payment')
                ->where('stats.awaiting_payment', 1)
            );
    }

    public function test_index_filters_by_status(): void
    {
        $this->seedSession(CheckoutSession::STATUS_COMPLETED);
        $this->seedSession(CheckoutSession::STATUS_AWAITING_PAYMENT);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/checkout-sessions?filter[status]=completed')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/checkout-sessions/index')
                ->has('sessions.data', 1)
                ->where('sessions.data.0.status', CheckoutSession::STATUS_COMPLETED)
            );
    }

    public function test_show_renders_detail(): void
    {
        $session = $this->seedSession();
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/checkout-sessions/'.$session->public_id)
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/checkout-sessions/show')
                ->where('session.public_id', $session->public_id)
                ->where('session.gateway_session_id', $session->gateway_session_id)
            );
    }

    public function test_force_cancel_marks_non_terminal_session_canceled(): void
    {
        $session = $this->seedSession();
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/checkout-sessions/'.$session->public_id)
            ->post('/admin/checkout-sessions/'.$session->public_id.'/force-cancel')
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $session->id,
            'status' => CheckoutSession::STATUS_CANCELED,
            'cancel_reason' => 'admin_force_cancel',
        ]);
    }

    public function test_force_cancel_no_ops_on_terminal_session(): void
    {
        $session = $this->seedSession(CheckoutSession::STATUS_COMPLETED);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/checkout-sessions/'.$session->public_id)
            ->post('/admin/checkout-sessions/'.$session->public_id.'/force-cancel')
            ->assertRedirect();

        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $session->id,
            'status' => CheckoutSession::STATUS_COMPLETED,
        ]);
    }
}
