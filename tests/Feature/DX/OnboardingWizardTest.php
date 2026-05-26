<?php

namespace Tests\Feature\DX;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_tenant_owner_sees_onboarding_signal(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->get(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('dashboard')
                ->where('currentTenant.is_owner', true)
                ->where('currentTenant.onboarded_at', null)
            );
    }

    public function test_onboarded_tenant_skips_wizard(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        // Simulate the user finishing the wizard.
        app(TenantService::class)->update($tenant, [
            'settings' => ['onboarded_at' => now()->toIso8601String()],
        ]);

        $this->actingAs($owner)
            ->get(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('dashboard')
                ->where('currentTenant.is_owner', true)
                ->where(
                    'currentTenant.onboarded_at',
                    fn ($v) => is_string($v) && $v !== ''
                )
            );
    }

    public function test_complete_endpoint_marks_tenant_as_onboarded(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->post(route('tenants.onboarding.complete', ['tenantSlug' => $tenant->slug]))
            ->assertRedirect();

        $tenant->refresh();
        $this->assertArrayHasKey('onboarded_at', $tenant->settings);
        $this->assertNotEmpty($tenant->settings['onboarded_at']);
    }

    public function test_non_owner_cannot_complete_onboarding(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        app(TenantService::class)->invite($tenant, $owner, $member->email, 'Member');

        $this->actingAs($member)
            ->post(route('tenants.onboarding.complete', ['tenantSlug' => $tenant->slug]))
            ->assertForbidden();
    }
}
