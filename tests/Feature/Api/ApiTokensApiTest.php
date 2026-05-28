<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokensApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $plain): array
    {
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'];
    }

    public function test_index_requires_token(): void
    {
        $this->getJson('/api/v1/me/api-tokens')->assertUnauthorized();
    }

    public function test_index_rejects_wrong_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['billing:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/me/api-tokens')
            ->assertForbidden();
    }

    public function test_index_lists_caller_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['profile:read']);
        $user->createToken('mobile', ['profile:read']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/me/api-tokens')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_destroy_refuses_to_revoke_calling_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['*']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->deleteJson('/api/v1/me/api-tokens/'.$token->accessToken->id)
            ->assertStatus(422);
    }

    public function test_destroy_removes_other_token(): void
    {
        $user = User::factory()->create();
        $caller = $user->createToken('cli', ['*']);
        $other = $user->createToken('mobile', ['profile:read']);

        $this->withHeaders($this->headers($caller->plainTextToken))
            ->deleteJson('/api/v1/me/api-tokens/'.$other->accessToken->id)
            ->assertNoContent();

        $this->assertCount(1, $user->fresh()->tokens);
    }
}
