<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_unflagged_user_is_not_bounced(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('account.tenants.index', absolute: false))
            ->assertOk();
    }

    public function test_flagged_user_is_bounced_to_security_settings(): void
    {
        $user = User::factory()->create([
            'force_password_reset' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('account.tenants.index', absolute: false))
            ->assertRedirect(route('security.edit', absolute: false));
    }

    public function test_flagged_user_can_still_hit_password_update(): void
    {
        $user = User::factory()->create([
            'force_password_reset' => true,
            'email_verified_at' => now(),
        ]);

        // Hits an allow-listed route; should NOT bounce to /settings/security.
        // (We don't care if the password update itself succeeds — only that
        // our middleware lets the request through.)
        $response = $this->actingAs($user)
            ->put(route('user-password.update', absolute: false), [
                'current_password' => 'wrong',
                'password' => 'new-password-1!',
                'password_confirmation' => 'new-password-1!',
            ]);

        // The key assertion: not redirected to security.edit by our middleware.
        if ($response->isRedirect()) {
            $this->assertStringNotContainsString('/settings/security', $response->headers->get('Location') ?? '');
        }
    }

    public function test_flagged_user_can_logout(): void
    {
        $user = User::factory()->create([
            'force_password_reset' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect();
    }

    public function test_password_change_clears_the_flag(): void
    {
        $user = User::factory()->create([
            'force_password_reset' => true,
            'email_verified_at' => now(),
        ]);

        // Direct model save (e.g. via Fortify's UpdatesUserPasswords action,
        // or the emailed reset flow).
        $user->forceFill(['password' => Hash::make('a-new-password-1!')])->save();

        $this->assertFalse((bool) $user->fresh()->force_password_reset);
    }

    public function test_unrelated_save_does_not_clear_the_flag(): void
    {
        $user = User::factory()->create(['force_password_reset' => true, 'name' => 'Old']);
        $user->update(['name' => 'New']);

        $this->assertTrue((bool) $user->fresh()->force_password_reset);
    }
}
