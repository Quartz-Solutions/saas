<?php

namespace Tests\Feature\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersAdminControllerTest extends TestCase
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

    public function test_index_returns_users(): void
    {
        $admin = $this->makeSuperAdmin();
        $other = User::factory()->create(['name' => 'Other']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/users/index')
                ->has('users.data', 2)
                ->has('viewCounts.all')
            );

        unset($other);
    }

    public function test_show_returns_user_details(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create(['name' => 'Target']);

        $this->actingAs($admin)
            ->get(route('admin.users.show', ['user' => $user->id]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/users/show')
                ->where('user.id', $user->id)
                ->where('user.name', 'Target')
            );
    }

    public function test_suspend_clears_sessions_and_audits(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'sess-1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', ['user' => $user->id]), [
                'reason' => 'spam',
            ])
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->suspended_at);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'auditable_id' => $user->id,
            'action' => 'admin.user.suspended',
        ]);
    }

    public function test_cannot_self_suspend(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', ['user' => $admin->id]))
            ->assertForbidden();
    }

    public function test_restore_clears_suspension(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create(['suspended_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.users.restore', ['user' => $user->id]))
            ->assertRedirect();

        $this->assertNull($user->fresh()->suspended_at);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'action' => 'admin.user.restored',
        ]);
    }

    public function test_disable_two_factor_clears_secret(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => 'encrypted-secret',
            'two_factor_recovery_codes' => 'encrypted-codes',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->post(route('admin.users.disable-two-factor', ['user' => $user->id]))
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'action' => 'admin.user.2fa_disabled',
        ]);
    }

    public function test_revoke_sessions_deletes_session_rows(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'a', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
            ['id' => 'b', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.revoke-sessions', ['user' => $user->id]))
            ->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_revoke_tokens_deletes_personal_access_tokens(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();
        $user->createToken('test', ['read']);

        $this->actingAs($admin)
            ->post(route('admin.users.revoke-tokens', ['user' => $user->id]))
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_grant_and_revoke_super_admin(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.grant-super-admin', ['user' => $user->id]))
            ->assertRedirect();
        $this->assertTrue($user->fresh()->hasRole('Super Admin'));

        $this->actingAs($admin)
            ->post(route('admin.users.revoke-super-admin', ['user' => $user->id]))
            ->assertRedirect();
        $this->assertFalse($user->fresh()->hasRole('Super Admin'));
    }

    public function test_cannot_revoke_own_super_admin(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post(route('admin.users.revoke-super-admin', ['user' => $admin->id]))
            ->assertForbidden();
    }

    public function test_force_password_reset_flags_user(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.force-password-reset', ['user' => $user->id]))
            ->assertRedirect();

        $this->assertTrue((bool) $user->fresh()->force_password_reset);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'action' => 'admin.user.force_password_reset',
        ]);
    }

    public function test_gdpr_export_returns_payload(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.gdpr-export', ['user' => $user->id]));

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame($user->id, $payload['user']['id']);
        $this->assertArrayHasKey('login_history', $payload);
    }

    public function test_actions_blocked_for_non_super_admin(): void
    {
        $regular = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($regular)
            ->post(route('admin.users.suspend', ['user' => $target->id]))
            ->assertForbidden();
    }
}
