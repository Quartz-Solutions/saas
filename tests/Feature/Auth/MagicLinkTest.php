<?php

namespace Tests\Feature\Auth;

use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_page_can_be_rendered(): void
    {
        $this->get(route('auth.magic-link.create'))->assertOk();
    }

    public function test_authenticated_user_cannot_view_magic_link_request_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('auth.magic-link.create'))
            ->assertRedirect();
    }

    public function test_request_sends_a_notification_when_user_exists(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('auth.magic-link.store'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, MagicLinkNotification::class);
        $this->assertDatabaseCount('magic_login_tokens', 1);
        $this->assertDatabaseHas('magic_login_tokens', ['user_id' => $user->id]);
    }

    public function test_request_does_not_leak_when_email_unknown(): void
    {
        Notification::fake();

        $this->post(route('auth.magic-link.store'), ['email' => 'ghost@example.com'])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('magic_login_tokens', 0);
    }

    public function test_request_requires_valid_email(): void
    {
        $this->post(route('auth.magic-link.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_valid_signed_token_logs_user_in(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->post(route('auth.magic-link.store'), ['email' => $user->email]);

        $captured = null;
        Notification::assertSentTo($user, MagicLinkNotification::class, function ($notification) use (&$captured) {
            $captured = $notification->plainToken;

            return true;
        });

        $signedUrl = URL::temporarySignedRoute(
            'auth.magic-link.consume',
            now()->addMinutes(15),
            ['token' => $captured],
        );

        $this->get($signedUrl)
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(MagicLoginToken::query()->where('user_id', $user->id)->first()->consumed_at);
    }

    public function test_token_cannot_be_consumed_twice(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->post(route('auth.magic-link.store'), ['email' => $user->email]);

        $captured = null;
        Notification::assertSentTo($user, MagicLinkNotification::class, function ($notification) use (&$captured) {
            $captured = $notification->plainToken;

            return true;
        });

        $signedUrl = URL::temporarySignedRoute(
            'auth.magic-link.consume',
            now()->addMinutes(15),
            ['token' => $captured],
        );

        $this->get($signedUrl);
        $this->post(route('logout'));

        // Second use should fail.
        $this->get($signedUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unsigned_url_is_rejected(): void
    {
        // Bypass the signed middleware by calling the route URL directly without a signature.
        $this->get('/auth/magic-link/anything')
            ->assertForbidden();
    }

    public function test_unknown_token_redirects_with_error(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'auth.magic-link.consume',
            now()->addMinutes(15),
            ['token' => 'never-issued'],
        );

        $this->get($signedUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
