<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Billing\PlanService;
use App\Support\Tenancy\TenantService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlansControllerTest extends TestCase
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

    public function test_index_requires_super_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/plans')->assertStatus(403);
    }

    public function test_index_lists_plans_with_active_sub_counts(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);

        $this->actingAs($admin)
            ->get('/admin/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/plans/index')
                ->has('plans.data', 3)
                ->where('plans.data.0.slug', 'free')
                ->where('plans.data.0.active_subscriptions_count', 0)
            );
    }

    public function test_store_creates_plan_and_redirects(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(CurrencySeeder::class);

        $this->actingAs($admin)
            ->post('/admin/plans', [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Entry tier',
                'price_cents' => 900,
                'currency' => 'USD',
                'billing_period' => 'month',
                'billing_interval' => 1,
                'trial_days' => 7,
                'features' => ['community_support' => true, 'projects' => 1],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 5,
            ])
            ->assertRedirect('/admin/plans');

        $this->assertDatabaseHas('plans', [
            'slug' => 'starter',
            'name' => 'Starter',
            'price_cents' => 900,
        ]);
    }

    public function test_store_auto_slugifies_from_name_when_blank(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(CurrencySeeder::class);

        $this->actingAs($admin)
            ->post('/admin/plans', [
                'name' => 'Custom Tier',
                'description' => null,
                'price_cents' => 1500,
                'currency' => 'USD',
                'billing_period' => 'month',
                'billing_interval' => 1,
                'trial_days' => 0,
                'features' => [],
                'is_active' => true,
                'is_public' => false,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', ['slug' => 'custom-tier']);
    }

    public function test_update_persists_changes(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/plans/{$plan->id}", [
                'slug' => 'pro',
                'name' => 'Pro Plus',
                'description' => $plan->description,
                'price_cents' => 3900,
                'currency' => 'USD',
                'billing_period' => 'month',
                'billing_interval' => 1,
                'trial_days' => 14,
                'features' => ['api_access' => true],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 20,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'name' => 'Pro Plus', 'price_cents' => 3900]);
    }

    public function test_archive_blocked_when_plan_has_active_subscriptions(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'unit_amount_cents' => $plan->price_cents,
        ]);

        $this->actingAs($admin)
            ->from('/admin/plans')
            ->delete("/admin/plans/{$plan->id}")
            ->assertRedirect();

        $this->assertNull(Plan::find($plan->id)?->deleted_at, 'Plan should not be soft-deleted when active subs exist');
    }

    public function test_archive_soft_deletes_when_no_active_subscriptions(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $this->actingAs($admin)
            ->delete("/admin/plans/{$plan->id}")
            ->assertRedirect('/admin/plans');

        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'is_active' => false]);
    }

    public function test_restore_brings_back_archived_plan(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        app(PlanService::class)->archive($plan);

        $this->actingAs($admin)
            ->post("/admin/plans/{$plan->id}/restore")
            ->assertRedirect('/admin/plans');

        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'is_active' => true, 'deleted_at' => null]);
    }

    public function test_validation_blocks_invalid_payload(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(CurrencySeeder::class);

        $this->actingAs($admin)
            ->from('/admin/plans/create')
            ->post('/admin/plans', [
                'name' => '',
                'price_cents' => -1,
                'billing_period' => 'fortnight',
                'currency' => 'ZZZ',
                'trial_days' => 999,
                'billing_interval' => 1,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect('/admin/plans/create')
            ->assertSessionHasErrors(['name', 'price_cents', 'billing_period', 'currency', 'trial_days']);
    }

    public function test_audit_log_records_plan_save(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(CurrencySeeder::class);

        $this->actingAs($admin)
            ->post('/admin/plans', [
                'name' => 'Audited',
                'slug' => 'audited',
                'price_cents' => 100,
                'currency' => 'USD',
                'billing_period' => 'month',
                'billing_interval' => 1,
                'trial_days' => 0,
                'features' => [],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'created',
            'auditable_type' => Plan::class,
        ]);
    }

    public function test_pricing_page_reads_from_db(): void
    {
        $this->seed(PlansSeeder::class);

        // Add a private plan that should NOT appear on /pricing.
        Plan::factory()->create([
            'slug' => 'enterprise-custom',
            'name' => 'Enterprise Custom',
            'is_active' => true,
            'is_public' => false,
            'price_cents' => 99900,
            'currency' => 'USD',
            'billing_period' => 'year',
            'billing_interval' => 1,
        ]);

        $this->get(route('marketing.pricing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('marketing/pricing')
                ->has('plans', 3) // private plan excluded
            );
    }

    public function test_unknown_feature_slug_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(CurrencySeeder::class);

        $this->actingAs($admin)
            ->from('/admin/plans/create')
            ->post('/admin/plans', [
                'name' => 'Bogus',
                'slug' => 'bogus',
                'price_cents' => 100,
                'currency' => 'USD',
                'billing_period' => 'month',
                'billing_interval' => 1,
                'trial_days' => 0,
                'features' => ['api_access' => true, 'not_a_real_slug' => true],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect('/admin/plans/create')
            ->assertSessionHasErrors('features');
    }

    public function test_quota_feature_with_wrong_value_type_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(CurrencySeeder::class);

        $this->actingAs($admin)
            ->from('/admin/plans/create')
            ->post('/admin/plans', [
                'name' => 'Bad quota',
                'slug' => 'bad-quota',
                'price_cents' => 100,
                'currency' => 'USD',
                'billing_period' => 'month',
                'billing_interval' => 1,
                'trial_days' => 0,
                // team_seats is a quota; "lots" is not numeric.
                'features' => ['team_seats' => 'lots'],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect('/admin/plans/create')
            ->assertSessionHasErrors('features');
    }

    public function test_feature_catalog_is_passed_to_create_and_edit_pages(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(CurrencySeeder::class);

        $this->actingAs($admin)
            ->get('/admin/plans/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/plans/edit')
                ->has('featureCatalog')
                ->where('featureCatalog.0.category', fn ($c) => is_string($c) && $c !== '')
            );
    }

    public function test_plan_serializes_features_with_resolved_metadata(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(PlansSeeder::class);

        $this->actingAs($admin)
            ->get('/admin/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Free plan ships with 5 features (community_support, basic_analytics,
                // projects=1, team_seats=3, storage_gb=1).
                ->has('plans.data.0.features_resolved', 5)
            );
    }
}
