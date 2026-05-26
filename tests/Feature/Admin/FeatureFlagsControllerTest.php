<?php

namespace Tests\Feature\Admin;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeatureFlagsControllerTest extends TestCase
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

    public function test_index_lists_flags(): void
    {
        $admin = $this->makeSuperAdmin();
        FeatureFlag::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.feature-flags.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/feature-flags/index')
                ->has('featureFlags.data', 3)
            );
    }

    public function test_show_returns_flag_with_no_overrides(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag = FeatureFlag::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.feature-flags.show', ['feature_flag' => $flag->id]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/feature-flags/show')
                ->where('featureFlag.key', $flag->key)
                ->has('overrides', 0)
            );
    }

    public function test_store_creates_flag(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from(route('admin.feature-flags.index'))
            ->post(route('admin.feature-flags.store'), [
                'key' => 'billing.dunning',
                'name' => 'Billing dunning',
                'description' => 'Retry failed payments',
                'enabled_globally' => '1',
            ])
            ->assertRedirect(route('admin.feature-flags.index'));

        $this->assertDatabaseHas('feature_flags', [
            'key' => 'billing.dunning',
            'enabled_globally' => true,
        ]);
    }

    public function test_store_rejects_duplicate_key(): void
    {
        $admin = $this->makeSuperAdmin();
        FeatureFlag::factory()->create(['key' => 'existing.flag']);

        $this->actingAs($admin)
            ->from(route('admin.feature-flags.index'))
            ->post(route('admin.feature-flags.store'), [
                'key' => 'existing.flag',
                'name' => 'Duplicate',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_store_rejects_invalid_key_format(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from(route('admin.feature-flags.index'))
            ->post(route('admin.feature-flags.store'), [
                'key' => 'Has Spaces!',
                'name' => 'Bad',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_update_modifies_flag(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag = FeatureFlag::factory()->create(['enabled_globally' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.feature-flags.update', ['feature_flag' => $flag->id]), [
                'key' => $flag->key,
                'name' => 'Renamed',
                'enabled_globally' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('feature_flags', [
            'id' => $flag->id,
            'name' => 'Renamed',
            'enabled_globally' => true,
        ]);
    }

    public function test_destroy_deletes_flag(): void
    {
        $admin = $this->makeSuperAdmin();
        $flag = FeatureFlag::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.feature-flags.destroy', ['feature_flag' => $flag->id]))
            ->assertRedirect(route('admin.feature-flags.index'));

        $this->assertDatabaseMissing('feature_flags', ['id' => $flag->id]);
    }

    public function test_writes_rejected_for_non_admin(): void
    {
        $regular = User::factory()->create();

        $this->actingAs($regular)
            ->post(route('admin.feature-flags.store'), [
                'key' => 'whatever',
                'name' => 'X',
            ])
            ->assertForbidden();
    }
}
