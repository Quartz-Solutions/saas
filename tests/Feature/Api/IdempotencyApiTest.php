<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeating_a_create_with_same_idempotency_key_returns_cached_response(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['tenants:write']);

        $headers = [
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
            'Idempotency-Key' => 'unique-key-123',
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/tenants', ['name' => 'Idem Co'])
            ->assertCreated();

        $second = $this->withHeaders($headers)
            ->postJson('/api/v1/tenants', ['name' => 'Different name'])
            ->assertCreated();

        // Second request returns the cached body and replay marker.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('true', $second->headers->get('Idempotent-Replay'));
        $this->assertSame(1, Tenant::query()->where('name', 'Idem Co')->count());
        $this->assertSame(0, Tenant::query()->where('name', 'Different name')->count());
    }

    public function test_different_idempotency_keys_run_independently(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['tenants:write']);

        $base = [
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ];

        $this->withHeaders($base + ['Idempotency-Key' => 'k1'])
            ->postJson('/api/v1/tenants', ['name' => 'A'])
            ->assertCreated();

        $this->withHeaders($base + ['Idempotency-Key' => 'k2'])
            ->postJson('/api/v1/tenants', ['name' => 'B'])
            ->assertCreated();

        $this->assertSame(1, Tenant::query()->where('name', 'A')->count());
        $this->assertSame(1, Tenant::query()->where('name', 'B')->count());
    }
}
