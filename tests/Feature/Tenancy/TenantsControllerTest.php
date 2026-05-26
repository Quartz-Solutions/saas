<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_tenants_index_lists_user_memberships(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $this->actingAs($user)
            ->get(route('account.tenants.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('account/tenants')
                ->where('tenants.0.slug', $tenant->slug)
            );
    }

    public function test_account_tenants_store_creates_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.tenants.store'), ['name' => 'Beta Co'])
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Beta Co',
            'owner_id' => $user->id,
        ]);
    }

    public function test_account_tenants_store_validates_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.tenants.index'))
            ->post(route('account.tenants.store'), [
                'name' => 'Bad',
                'slug' => 'Has Spaces!',
            ])
            ->assertRedirect(route('account.tenants.index'))
            ->assertSessionHasErrors('slug');
    }

    public function test_tenant_settings_visible_to_member(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->get(route('tenants.settings', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('tenants/settings')
                ->where('tenant.slug', $tenant->slug)
                ->where('tenant.is_owner', true)
            );
    }

    public function test_tenant_settings_404s_unknown_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/t/does-not-exist/settings')
            ->assertNotFound();
    }

    public function test_tenant_routes_forbid_non_member(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($outsider)
            ->get(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_tenant_settings_update_changes_attributes(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->patch(route('tenants.settings.update', ['tenantSlug' => $tenant->slug]), [
                'name' => 'Acme Renamed',
                'slug' => 'acme-renamed',
                'timezone' => 'America/New_York',
                'currency' => 'EUR',
                'locale' => 'en',
            ])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertSame('Acme Renamed', $tenant->name);
        $this->assertSame('acme-renamed', $tenant->slug);
        $this->assertSame('EUR', $tenant->currency);
    }

    public function test_tenant_destroy_only_by_owner(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        app(TenantService::class)->invite($tenant, $owner, $member->email, 'Member');

        $this->actingAs($member)
            ->delete(route('tenants.settings.destroy', ['tenantSlug' => $tenant->slug]))
            ->assertForbidden();

        $this->assertNotSoftDeleted('tenants', ['id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete(route('tenants.settings.destroy', ['tenantSlug' => $tenant->slug]))
            ->assertRedirect();

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_dashboard_redirects_to_current_tenant(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));
    }
}
