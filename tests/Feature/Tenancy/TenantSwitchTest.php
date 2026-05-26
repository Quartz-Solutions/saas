<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_switch_tenants(): void
    {
        $user = User::factory()->create();
        $a = app(TenantService::class)->create($user, ['name' => 'Tenant A']);
        $b = app(TenantService::class)->create($user, ['name' => 'Tenant B']);

        $this->actingAs($user)
            ->post(route('tenants.switch', ['tenant' => $b->slug]))
            ->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $b->slug]));

        $this->assertSame($b->id, $user->fresh()->current_tenant_id);
        $this->assertNotSame($a->id, $user->fresh()->current_tenant_id);
    }

    public function test_non_member_cannot_switch_to_tenant(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($outsider)
            ->post(route('tenants.switch', ['tenant' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_switch_returns_404_for_unknown_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tenants.switch', ['tenant' => 'nope-nope']))
            ->assertNotFound();
    }
}
