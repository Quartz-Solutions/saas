<?php

namespace Tests\Feature\Api;

use App\Models\TenantInvitation;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembersAndInvitationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $plain): array
    {
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'];
    }

    private function withTenant(): array
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        return [$owner, $tenant];
    }

    public function test_members_index_requires_membership(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $stranger = User::factory()->create();
        $token = $stranger->createToken('cli', ['members:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/members')
            ->assertForbidden();
    }

    public function test_members_index_returns_owner_row(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['members:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/members')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'is_owner', 'joined_at']],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.is_owner', true);
    }

    public function test_invitations_index_returns_pending_invitations(): void
    {
        [$owner, $tenant] = $this->withTenant();
        app(TenantService::class)->invite($tenant, $owner, 'pending@example.com', 'Member');

        $token = $owner->createToken('cli', ['members:read']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/invitations')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_invitations_store_creates_pending_invitation(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['members:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/invitations', [
                'email' => 'guest@example.com',
                'role' => 'Member',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'guest@example.com')
            ->assertJsonPath('data.role', 'Member');

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'guest@example.com',
        ]);
    }

    public function test_invitations_store_rejects_wrong_ability(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['members:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/invitations', [
                'email' => 'guest@example.com',
                'role' => 'Member',
            ])
            ->assertForbidden();
    }

    public function test_invitations_destroy_revokes(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $invite = app(TenantService::class)->invite($tenant, $owner, 'g@example.com', 'Member');

        $token = $owner->createToken('cli', ['members:write']);
        $this->withHeaders($this->headers($token->plainTextToken))
            ->deleteJson('/api/v1/tenants/'.$tenant->slug.'/invitations/'.$invite->id)
            ->assertNoContent();

        $this->assertNotNull(TenantInvitation::find($invite->id)->revoked_at);
    }

    public function test_members_destroy_blocks_removing_owner(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['members:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->deleteJson('/api/v1/tenants/'.$tenant->slug.'/members/'.$owner->id)
            ->assertStatus(422);
    }

    public function test_members_update_role_locks_owner(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['members:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->patchJson('/api/v1/tenants/'.$tenant->slug.'/members/'.$owner->id.'/role', [
                'role' => 'Admin',
            ])
            ->assertStatus(422);
    }
}
