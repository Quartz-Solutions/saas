<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(string $token): array
    {
        app('auth')->forgetGuards();

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    public function test_show_requires_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_show_rejects_wrong_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['billing:read']);

        $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Token lacks profile:read ability.');
    }

    public function test_show_returns_shape(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);
        $token = $user->createToken('cli', ['profile:read']);

        $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'email_verified_at', 'locale', 'timezone', 'token' => ['id', 'name', 'abilities']],
            ])
            ->assertJsonPath('data.name', 'Ada')
            ->assertJsonPath('data.token.abilities.0', 'profile:read');
    }

    public function test_update_requires_token(): void
    {
        $this->patchJson('/api/v1/me', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_update_rejects_without_write_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['profile:read']);

        $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->patchJson('/api/v1/me', ['name' => 'New Name'])
            ->assertForbidden();
    }

    public function test_update_applies_changes(): void
    {
        $user = User::factory()->create(['name' => 'Old']);
        $token = $user->createToken('cli', ['profile:write']);

        $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->patchJson('/api/v1/me', ['name' => 'New', 'timezone' => 'Europe/Berlin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.timezone', 'Europe/Berlin');

        $this->assertSame('New', $user->fresh()->name);
    }

    public function test_email_change_requires_auth_ability_and_uses_auth_bucket(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $token = $user->createToken('cli', ['profile:write']);

        $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->postJson('/api/v1/me/email-change', ['email' => 'new@example.com'])
            ->assertAccepted()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.verification_sent', true);

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_email_change_short_circuits_when_unchanged(): void
    {
        $user = User::factory()->create(['email' => 'same@example.com']);
        $token = $user->createToken('cli', ['profile:write']);

        $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->postJson('/api/v1/me/email-change', ['email' => 'same@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Email unchanged.');
    }

    public function test_revoke_all_sessions_clears_db_sessions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['profile:write']);

        DB::table('sessions')->insert([
            'id' => 'sess-a', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit', 'payload' => '', 'last_activity' => time(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'sess-b', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->postJson('/api/v1/me/sessions/revoke-all')
            ->assertOk()
            ->assertJsonPath('data.revoked_count', 2);
    }

    public function test_rate_limit_headers_present(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['profile:read']);

        $response = $this->withHeaders($this->authHeaders($token->plainTextToken))
            ->getJson('/api/v1/me');

        $response->assertOk();
        $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Remaining'));
    }
}
