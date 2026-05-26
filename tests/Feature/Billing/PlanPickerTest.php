<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_picker_renders_with_plans_from_db(): void
    {
        $this->seed(PlansSeeder::class);

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->get(route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('billing/plans')
                ->has('plans', 3)
                ->where('plans.0.slug', 'free')
                ->where('subscription', null)
            );
    }

    public function test_plan_picker_requires_membership(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_plan_picker_404s_on_unknown_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/t/does-not-exist/billing/plans')
            ->assertNotFound();
    }

    public function test_plan_picker_requires_auth(): void
    {
        $this->get('/t/acme/billing/plans')->assertRedirect();
    }

    public function test_subscribe_to_free_plan_records_subscription(): void
    {
        $this->seed(PlansSeeder::class);

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->post(route('tenants.billing.subscribe', ['tenantSlug' => $tenant->slug]), [
                'plan' => 'free',
                'gateway' => 'stripe',
            ])
            ->assertRedirect(route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]));

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'unit_amount_cents' => 0,
        ]);
    }

    public function test_subscribe_validates_plan_slug(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->from(route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]))
            ->post(route('tenants.billing.subscribe', ['tenantSlug' => $tenant->slug]), [
                'plan' => 'bogus',
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_subscribe_requires_owner_or_admin_role(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $member = User::factory()->create();
        app(TenantService::class)->invite($tenant, $owner, $member->email, 'Member', autoAttach: true);

        $this->actingAs($member)
            ->post(route('tenants.billing.subscribe', ['tenantSlug' => $tenant->slug]), [
                'plan' => 'free',
            ])
            ->assertForbidden();
    }
}
