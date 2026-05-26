<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every admin route must reject regular users and accept Super Admins.
 * Authoritative gate test for the entire scope.
 */
class AdminGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_reach_admin(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        // Either redirect to verification notice (verified middleware) or
        // 403 (role middleware) — both keep them out of the admin scope.
        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_verified_non_admin_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_can_load_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('admin/dashboard'));
    }
}
