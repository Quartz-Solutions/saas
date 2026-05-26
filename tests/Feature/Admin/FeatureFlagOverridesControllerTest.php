<?php

namespace Tests\Feature\Admin;

use App\Models\FeatureFlag;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeatureFlagOverridesControllerTest extends TestCase
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

    public function test_store_creates_tenant_override(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag = FeatureFlag::factory()->create();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($admin)
            ->post(
                route('admin.feature-flags.overrides.store', ['feature_flag' => $flag->id]),
                [
                    'tenant_id' => $tenant->id,
                    'enabled' => '1',
                    'reason' => 'beta tester',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas('feature_flag_overrides', [
            'feature_flag_id' => $flag->id,
            'tenant_id' => $tenant->id,
            'enabled' => true,
            'created_by_id' => $admin->id,
        ]);
    }

    public function test_store_requires_either_tenant_or_user(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag = FeatureFlag::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.feature-flags.show', ['feature_flag' => $flag->id]))
            ->post(
                route('admin.feature-flags.overrides.store', ['feature_flag' => $flag->id]),
                ['enabled' => '1']
            )
            ->assertSessionHasErrors('tenant_id');
    }

    public function test_update_toggles_existing_override(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag = FeatureFlag::factory()->create();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $override = FeatureFlagOverride::factory()->create([
            'feature_flag_id' => $flag->id,
            'tenant_id' => $tenant->id,
            'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->patch(
                route('admin.feature-flags.overrides.update', [
                    'feature_flag' => $flag->id,
                    'override' => $override->id,
                ]),
                ['enabled' => '0']
            )
            ->assertRedirect();

        $this->assertDatabaseHas('feature_flag_overrides', [
            'id' => $override->id,
            'enabled' => false,
        ]);
    }

    public function test_destroy_removes_override(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag = FeatureFlag::factory()->create();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $override = FeatureFlagOverride::factory()->create([
            'feature_flag_id' => $flag->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.feature-flags.overrides.destroy', [
                'feature_flag' => $flag->id,
                'override' => $override->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('feature_flag_overrides', ['id' => $override->id]);
    }

    public function test_override_routes_404_on_mismatched_flag(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag1 = FeatureFlag::factory()->create();
        $flag2 = FeatureFlag::factory()->create();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $override = FeatureFlagOverride::factory()->create([
            'feature_flag_id' => $flag1->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.feature-flags.overrides.destroy', [
                'feature_flag' => $flag2->id,
                'override' => $override->id,
            ]))
            ->assertNotFound();
    }

    public function test_overrides_blocked_for_non_admin(): void
    {
        $regular = User::factory()->create();
        $flag = FeatureFlag::factory()->create();

        $this->actingAs($regular)
            ->post(
                route('admin.feature-flags.overrides.store', ['feature_flag' => $flag->id]),
                ['enabled' => '1', 'tenant_id' => 1]
            )
            ->assertForbidden();
    }
}
