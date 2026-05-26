<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionsAdminControllerTest extends TestCase
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

    private function seedTenantWithSubscription(string $planSlug = 'pro', string $status = 'active'): Subscription
    {
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', $planSlug)->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme '.uniqid()]);

        return Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_'.uniqid(),
            'currency' => $plan->currency,
            'unit_amount_cents' => $plan->price_cents,
            'quantity' => 1,
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_index_requires_super_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/subscriptions')->assertStatus(403);
    }

    public function test_index_lists_subscriptions(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedTenantWithSubscription();

        $this->actingAs($admin)
            ->get('/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/subscriptions/index')
                ->has('subscriptions.data', 1)
                ->where('subscriptions.data.0.id', $sub->id)
                ->has('stats.mrr_cents')
                ->has('plans')
            );
    }

    public function test_status_filter_narrows_results(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seedTenantWithSubscription('pro', 'active');
        $this->seedTenantWithSubscription('pro', 'trialing');
        $this->seedTenantWithSubscription('pro', 'canceled');

        $this->actingAs($admin)
            ->get('/admin/subscriptions?filter[status]=active')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('subscriptions.data', 1)
                ->where('subscriptions.data.0.status', 'active')
            );
    }

    public function test_search_matches_tenant_owner_email(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $owner = User::factory()->create(['email' => 'needle@example.com']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Findable']);
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'unit_amount_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'gateway' => 'stripe',
        ]);

        // A red-herring subscription that shouldn't match.
        $this->seedTenantWithSubscription();

        $this->actingAs($admin)
            ->get('/admin/subscriptions?search=needle@example.com')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('subscriptions.data', 1)
                ->where('subscriptions.data.0.tenant.owner.email', 'needle@example.com')
            );
    }

    public function test_stats_compute_mrr_normalized_to_monthly(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);

        $proPlan = Plan::query()->where('slug', 'pro')->firstOrFail();
        // Force monthly cadence on Pro for the test math: 2900 cents/month.

        // A yearly enterprise sub that should normalize to /12.
        $enterprisePlan = Plan::query()->where('slug', 'enterprise')->firstOrFail();
        $enterprisePlan->forceFill([
            'billing_period' => 'year',
            'billing_interval' => 1,
            'price_cents' => 12000,
        ])->save();

        $owner1 = User::factory()->create();
        $tenant1 = app(TenantService::class)->create($owner1, ['name' => 'T1']);
        Subscription::factory()->create([
            'tenant_id' => $tenant1->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'unit_amount_cents' => 2900,
            'currency' => 'USD',
            'gateway' => 'stripe',
        ]);

        $owner2 = User::factory()->create();
        $tenant2 = app(TenantService::class)->create($owner2, ['name' => 'T2']);
        Subscription::factory()->create([
            'tenant_id' => $tenant2->id,
            'plan_id' => $enterprisePlan->id,
            'status' => 'active',
            'unit_amount_cents' => 12000,
            'currency' => 'USD',
            'gateway' => 'stripe',
        ]);

        $this->actingAs($admin)
            ->get('/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Pro (2900/month) + Enterprise (12000/year ÷ 12 = 1000) = 3900.
                ->where('stats.mrr_cents', 3900)
                ->where('stats.active', 2)
            );
    }

    public function test_show_returns_subscription_with_related_records(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedTenantWithSubscription();

        $invoice = Invoice::factory()->create([
            'tenant_id' => $sub->tenant_id,
            'subscription_id' => $sub->id,
            'status' => 'paid',
            'total_cents' => 2900,
            'currency' => 'USD',
        ]);

        Payment::factory()->create([
            'tenant_id' => $sub->tenant_id,
            'invoice_id' => $invoice->id,
            'status' => 'succeeded',
            'amount_cents' => 2900,
            'currency' => 'USD',
            'gateway' => 'stripe',
        ]);

        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'customer.subscription.updated',
            'payload' => ['data' => ['object' => ['id' => $sub->gateway_subscription_id]]],
            'status' => 'processed',
        ]);

        $this->actingAs($admin)
            ->get("/admin/subscriptions/{$sub->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/subscriptions/show')
                ->where('subscription.id', $sub->id)
                ->has('invoices', 1)
                ->has('payments', 1)
                ->has('webhookEvents', 1)
                ->has('plans')
            );
    }

    public function test_export_streams_csv(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedTenantWithSubscription();

        $response = $this->actingAs($admin)
            ->get('/admin/subscriptions/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('id,tenant,plan,status,gateway', $csv);
        $this->assertStringContainsString((string) $sub->id, $csv);
    }
}
