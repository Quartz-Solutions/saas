<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('token:1');
    }

    public function test_me_returns_user_payload_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['profile:read']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.token.name', 'cli')
            ->assertJsonPath('data.token.abilities.0', 'profile:read');
    }

    public function test_me_rejects_token_without_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['users:read']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/me')
            ->assertForbidden();
    }

    public function test_me_accepts_wildcard_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['*']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_me_requires_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_tenants_returns_user_tenants(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);
        $token = $user->createToken('cli', ['tenants:read']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $tenant->slug)
            ->assertJsonPath('data.0.role', 'Owner');
    }

    public function test_rate_limiter_kicks_in_after_quota(): void
    {
        // Set the limit very low for the duration of this test.
        config(['api-abilities.rate_limit_per_minute' => 3]);

        $user = User::factory()->create();
        $token = $user->createToken('cli', ['*']);

        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer '.$token->plainTextToken,
                'Accept' => 'application/json',
            ])
                ->getJson('/api/v1/me')
                ->assertOk();
        }

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/me')
            ->assertStatus(429);
    }
}
