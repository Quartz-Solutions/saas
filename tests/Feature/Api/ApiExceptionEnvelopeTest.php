<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiExceptionEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_returns_message_envelope(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['tenants:read']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/tenants/no-such-slug')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_500_returns_envelope_with_trace_id(): void
    {
        // Wire a one-off API route that throws so we exercise the renderer.
        Route::middleware(['auth:sanctum', 'throttle:api.read', 'api.rate:api.read'])
            ->prefix('api/v1')
            ->get('/__boom', function () {
                throw new \RuntimeException('boom');
            });

        $user = User::factory()->create();
        $token = $user->createToken('cli', ['*']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/__boom');

        $response->assertStatus(500)
            ->assertJsonStructure(['message', 'trace_id']);
        $this->assertSame('Server error.', $response->json('message'));
    }
}
