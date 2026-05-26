<?php

namespace Tests\Feature\Auth;

use App\Models\LoginHistory;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Auth\SocialProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force-enable the providers regardless of env config.
        $registry = $this->app->make(SocialProviderRegistry::class);
        $registry->register('google', 'Google', 'google');
        $registry->register('github', 'GitHub', 'github');
    }

    protected function fakeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = $overrides['id'] ?? 'remote-12345';
        $user->name = $overrides['name'] ?? 'Remote User';
        $user->email = $overrides['email'] ?? 'remote@example.com';
        $user->avatar = $overrides['avatar'] ?? 'https://example.com/avatar.png';
        $user->token = $overrides['token'] ?? 'access-token';
        $user->refreshToken = $overrides['refreshToken'] ?? 'refresh-token';
        $user->expiresIn = $overrides['expiresIn'] ?? 3600;
        $user->user = $overrides['raw'] ?? ['provider_specific' => true];

        return $user;
    }

    public function test_redirect_route_kicks_off_socialite(): void
    {
        $driver = Mockery::mock(SocialiteProvider::class);
        $driver->shouldReceive('redirect')->andReturn(redirect('https://provider.example/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/redirect')
            ->assertRedirect('https://provider.example/auth');
    }

    public function test_unknown_provider_is_rejected_at_routing(): void
    {
        $this->get('/auth/twitch/redirect')->assertNotFound();
    }

    public function test_callback_creates_user_when_none_exists(): void
    {
        $driver = Mockery::mock(SocialiteProvider::class);
        $driver->shouldReceive('user')->andReturn($this->fakeSocialiteUser());

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', ['email' => 'remote@example.com']);
        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_user_id' => 'remote-12345',
        ]);

        $user = User::query()->where('email', 'remote@example.com')->first();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at, 'provider-vouched email should be pre-verified');
        $this->assertDatabaseHas('login_history', [
            'user_id' => $user->id,
            'outcome' => 'succeeded',
            'method' => 'social_google',
        ]);
    }

    public function test_callback_links_to_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $driver = Mockery::mock(SocialiteProvider::class);
        $driver->shouldReceive('user')->andReturn($this->fakeSocialiteUser([
            'email' => 'taken@example.com',
        ]));

        Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

        $this->get('/auth/github/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count(), 'should not duplicate the user');
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $existing->id,
            'provider' => 'github',
        ]);
        $this->assertAuthenticatedAs($existing->fresh());
    }

    public function test_callback_returns_existing_social_account_user(): void
    {
        $user = User::factory()->create();
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'remote-12345',
        ]);

        $driver = Mockery::mock(SocialiteProvider::class);
        $driver->shouldReceive('user')->andReturn($this->fakeSocialiteUser());

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame(1, SocialAccount::query()->where('provider_user_id', 'remote-12345')->count());
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_callback_records_failure_when_socialite_throws(): void
    {
        $driver = Mockery::mock(SocialiteProvider::class);
        $driver->shouldReceive('user')->andThrow(new \RuntimeException('oauth boom'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, LoginHistory::query()
            ->where('outcome', 'failed')
            ->where('method', 'social_google')
            ->count());
    }
}
