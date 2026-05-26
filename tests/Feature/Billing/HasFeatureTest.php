<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Billing\PlanService;
use App\Support\Tenancy\TenantService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_has_feature_for_boolean(): void
    {
        $this->seed(PlansSeeder::class);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

        $this->assertTrue($pro->hasFeature('api_access'));
        $this->assertTrue($pro->hasFeature('webhooks'));
        $this->assertFalse($pro->hasFeature('sso_saml'));
        $this->assertFalse($pro->hasFeature('not_a_real_feature'));
    }

    public function test_plan_has_feature_for_quota_with_limit(): void
    {
        $this->seed(PlansSeeder::class);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

        $this->assertTrue($pro->hasFeature('team_seats'));      // 20
        $this->assertTrue($pro->hasFeature('projects'));        // -1 unlimited
    }

    public function test_plan_has_feature_returns_false_for_zero_quota(): void
    {
        $plan = Plan::factory()->create([
            'features' => ['team_seats' => 0],
        ]);

        $this->assertFalse($plan->hasFeature('team_seats'));
    }

    public function test_feature_limit_returns_int_or_null(): void
    {
        $this->seed(PlansSeeder::class);
        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();

        $this->assertSame(3, $free->featureLimit('team_seats'));
        $this->assertSame(20, $pro->featureLimit('team_seats'));
        $this->assertNull($enterprise->featureLimit('team_seats')); // -1 → null

        // Not included
        $this->assertSame(0, $free->featureLimit('api_access'));
    }

    public function test_feature_limit_treats_boolean_as_unlimited(): void
    {
        // A boolean feature has no numeric cap.
        $plan = Plan::factory()->create([
            'features' => ['api_access' => true],
        ]);

        $this->assertNull($plan->featureLimit('api_access'));
    }

    public function test_features_with_metadata_renders_quotas_with_pluralization(): void
    {
        $this->seed(PlansSeeder::class);
        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();
        $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();

        $freeNames = array_column($free->featuresWithMetadata(), 'name');
        $proNames = array_column($pro->featuresWithMetadata(), 'name');
        $entNames = array_column($enterprise->featuresWithMetadata(), 'name');

        $this->assertContains('1 project', $freeNames);
        $this->assertContains('3 team members', $freeNames);
        $this->assertContains('Unlimited projects', $proNames);
        $this->assertContains('20 team members', $proNames);
        $this->assertContains('Unlimited team members', $entNames);
    }

    public function test_features_with_metadata_drops_unknown_slugs(): void
    {
        $plan = Plan::factory()->create([
            'features' => ['api_access' => true, 'ghost_feature' => true],
        ]);

        $resolved = $plan->featuresWithMetadata();

        $this->assertCount(1, $resolved);
        $this->assertSame('api_access', $resolved[0]['slug']);
    }

    public function test_tenant_has_feature_via_active_subscription(): void
    {
        $this->seed(PlansSeeder::class);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'currency' => 'USD',
            'unit_amount_cents' => $pro->price_cents,
        ]);

        $tenant->refresh();
        $this->assertTrue($tenant->hasFeature('api_access'));
        $this->assertFalse($tenant->hasFeature('sso_saml'));
    }

    public function test_tenant_feature_limit_returns_plan_limit(): void
    {
        $this->seed(PlansSeeder::class);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'currency' => 'USD',
            'unit_amount_cents' => $pro->price_cents,
        ]);

        $this->assertSame(20, $tenant->fresh()->featureLimit('team_seats'));
        $this->assertNull($tenant->fresh()->featureLimit('projects')); // -1 → null
    }

    public function test_tenant_can_use_more_respects_limit(): void
    {
        $this->seed(PlansSeeder::class);
        $free = Plan::query()->where('slug', 'free')->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $free->id,
            'status' => 'active',
            'gateway' => 'free',
            'currency' => 'USD',
            'unit_amount_cents' => 0,
        ]);

        // Free plan allows 3 team_seats.
        $this->assertTrue($tenant->fresh()->canUseMore('team_seats', 0));
        $this->assertTrue($tenant->fresh()->canUseMore('team_seats', 2));
        $this->assertFalse($tenant->fresh()->canUseMore('team_seats', 3));
    }

    public function test_tenant_can_use_more_returns_true_for_unlimited(): void
    {
        $this->seed(PlansSeeder::class);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'gateway' => 'stripe',
            'currency' => 'USD',
            'unit_amount_cents' => $pro->price_cents,
        ]);

        // Pro has projects = -1 (unlimited).
        $this->assertTrue($tenant->fresh()->canUseMore('projects', 999_999));
    }

    public function test_tenant_has_feature_returns_false_without_active_subscription(): void
    {
        $this->seed(PlansSeeder::class);

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->assertFalse($tenant->hasFeature('api_access'));
        $this->assertSame(0, $tenant->featureLimit('team_seats'));
    }

    public function test_tenant_ignores_canceled_subscriptions(): void
    {
        $this->seed(PlansSeeder::class);
        $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $pro->id,
            'status' => 'canceled',
            'gateway' => 'stripe',
            'currency' => 'USD',
            'unit_amount_cents' => $pro->price_cents,
        ]);

        $this->assertFalse($tenant->fresh()->hasFeature('api_access'));
    }

    public function test_plan_service_sanitizes_unknown_slugs_on_save(): void
    {
        $this->seed(CurrencySeeder::class);

        $plan = app(PlanService::class)->save(null, [
            'name' => 'Mix',
            'slug' => 'mix',
            'price_cents' => 100,
            'currency' => 'USD',
            'billing_period' => 'month',
            'billing_interval' => 1,
            'trial_days' => 0,
            'features' => [
                'api_access' => true,
                'totally_made_up' => true,        // unknown → dropped
                'webhooks' => true,
                'team_seats' => 25,
                'projects' => -1,
                'storage_gb' => 0,                // zero → dropped
            ],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ]);

        $features = (array) $plan->features;
        $this->assertArrayHasKey('api_access', $features);
        $this->assertArrayHasKey('webhooks', $features);
        $this->assertArrayHasKey('team_seats', $features);
        $this->assertArrayHasKey('projects', $features);
        $this->assertSame(25, $features['team_seats']);
        $this->assertSame(-1, $features['projects']);
        $this->assertArrayNotHasKey('totally_made_up', $features);
        $this->assertArrayNotHasKey('storage_gb', $features);
    }

    public function test_migration_converts_flat_array_of_slugs_to_map(): void
    {
        $plan = Plan::factory()->create([
            'features' => ['api_access', 'webhooks', 'ghost'],
        ]);

        $migration = require database_path('migrations/2026_05_27_002741_convert_plan_features_strings_to_slugs.php');
        $migration->up();

        $this->assertSame(
            ['api_access' => true, 'webhooks' => true],
            (array) $plan->fresh()->features,
        );
    }

    public function test_migration_converts_flat_array_of_display_strings(): void
    {
        $plan = Plan::factory()->create([
            'features' => [
                'API access',           // → api_access => true
                'Outbound webhooks',    // → webhooks => true
                'Plain text nope',      // dropped
            ],
        ]);

        $migration = require database_path('migrations/2026_05_27_002741_convert_plan_features_strings_to_slugs.php');
        $migration->up();

        $this->assertSame(
            ['api_access' => true, 'webhooks' => true],
            (array) $plan->fresh()->features,
        );
    }

    public function test_migration_keeps_existing_map_shape(): void
    {
        $plan = Plan::factory()->create([
            'features' => ['api_access' => true, 'team_seats' => 10, 'ghost' => true],
        ]);

        $migration = require database_path('migrations/2026_05_27_002741_convert_plan_features_strings_to_slugs.php');
        $migration->up();

        $this->assertSame(
            ['api_access' => true, 'team_seats' => 10],
            (array) $plan->fresh()->features,
        );
    }
}
