<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantsApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $plain): array
    {
        // Laravel reuses the Application instance across requests in a single
        // test, so the auth guard caches the user resolved on the first call.
        // Forget the resolved user so each Authorization header is re-evaluated.
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'];
    }

    public function test_index_requires_token(): void
    {
        $this->getJson('/api/v1/tenants')->assertUnauthorized();
    }

    public function test_index_rejects_wrong_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['profile:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants')
            ->assertForbidden();
    }

    public function test_index_only_returns_user_tenants(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $svc = app(TenantService::class);
        $mine = $svc->create($user, ['name' => 'Mine']);
        $svc->create($stranger, ['name' => 'Theirs']);

        $token = $user->createToken('cli', ['tenants:read']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($mine->slug, $response->json('data.0.slug'));
        $this->assertSame('Owner', $response->json('data.0.role'));
    }

    public function test_show_requires_membership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Locked']);

        $token = $other->createToken('cli', ['tenants:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug)
            ->assertForbidden();
    }

    public function test_show_returns_tenant_for_member(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);
        $token = $user->createToken('cli', ['tenants:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug)
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'slug', 'name', 'role', 'currency', 'locale', 'timezone'],
            ])
            ->assertJsonPath('data.role', 'Owner');
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['tenants:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/does-not-exist')
            ->assertNotFound();
    }

    public function test_store_creates_tenant_with_caller_as_owner(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['tenants:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants', ['name' => 'New Co', 'currency' => 'USD'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Co')
            ->assertJsonPath('data.role', 'Owner');
    }

    public function test_store_rejects_wrong_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['tenants:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants', ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_store_validates_name(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['tenants:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants', [])
            ->assertStatus(422);
    }

    public function test_update_applies_changes_for_owner(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Old']);
        $token = $user->createToken('cli', ['tenants:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->patchJson('/api/v1/tenants/'.$tenant->slug, ['name' => 'Renamed', 'timezone' => 'UTC'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_destroy_only_owner_can_delete(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $svc = app(TenantService::class);
        $tenant = $svc->create($owner, ['name' => 'T']);

        // attach admin via invitation flow
        $svc->invite($tenant, $owner, $admin->email, 'Admin');

        $token = $admin->createToken('cli', ['tenants:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->deleteJson('/api/v1/tenants/'.$tenant->slug)
            ->assertForbidden();

        $ownerToken = $owner->createToken('cli2', ['tenants:write']);

        $this->withHeaders($this->headers($ownerToken->plainTextToken))
            ->deleteJson('/api/v1/tenants/'.$tenant->slug)
            ->assertNoContent();
    }
}
