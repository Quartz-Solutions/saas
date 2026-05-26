<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokensControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_user_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('cli-1', ['profile:read']);
        $user->createToken('cli-2', ['tenants:read']);

        $this->actingAs($user)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('settings/api-tokens')
                ->has('tokens', 2)
                ->has('abilities')
            );
    }

    public function test_store_creates_token_with_chosen_abilities(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/settings/api-tokens')
            ->post('/settings/api-tokens', [
                'name' => 'deploy-bot',
                'abilities' => ['profile:read', 'tenants:read'],
            ])
            ->assertRedirect('/settings/api-tokens');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'deploy-bot',
        ]);

        $this->assertNotEmpty($response->getSession()->get('plain_text_token.plain_text'));
    }

    public function test_store_rejects_unknown_ability(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/settings/api-tokens')
            ->post('/settings/api-tokens', [
                'name' => 'bad',
                'abilities' => ['not-a-real-ability'],
            ])
            ->assertRedirect('/settings/api-tokens')
            ->assertSessionHasErrors('abilities.0');
    }

    public function test_store_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/settings/api-tokens')
            ->post('/settings/api-tokens', [
                'abilities' => ['profile:read'],
            ])
            ->assertRedirect('/settings/api-tokens')
            ->assertSessionHasErrors('name');
    }

    public function test_destroy_revokes_token(): void
    {
        $user = User::factory()->create();
        $new = $user->createToken('cli', ['profile:read']);
        $id = $new->accessToken->id;

        $this->actingAs($user)
            ->delete("/settings/api-tokens/{$id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }

    public function test_destroy_only_revokes_own_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $other->createToken('cli', ['profile:read']);

        $this->actingAs($owner)
            ->delete('/settings/api-tokens/'.$token->accessToken->id)
            ->assertRedirect();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_guest_cannot_view_index(): void
    {
        $this->get('/settings/api-tokens')->assertRedirect('/login');
    }
}
