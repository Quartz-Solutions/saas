<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndPreferencesApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $plain): array
    {
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'];
    }

    public function test_audit_index_requires_audit_read(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        $token = $user->createToken('cli', ['billing:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/audit-log')
            ->assertForbidden();
    }

    public function test_audit_index_returns_only_tenant_entries(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        AuditLog::factory()->count(2)->create(['tenant_id' => $tenant->id, 'user_id' => $user->id]);
        AuditLog::factory()->count(3)->create(['tenant_id' => null, 'user_id' => $user->id]);

        $token = $user->createToken('cli', ['audit:read']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/audit-log')
            ->assertOk();

        $tenantIds = collect($response->json('data'))->pluck('tenant_id')->unique()->all();
        $this->assertEquals([$tenant->id], $tenantIds);
    }

    public function test_audit_show_404_cross_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        $foreign = AuditLog::factory()->create(['tenant_id' => null]);
        $token = $user->createToken('cli', ['audit:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/audit-log/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_notification_preferences_show(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['notifications:read']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonStructure(['data' => ['events', 'channels', 'preferences']]);

        $this->assertNotEmpty($response->json('data.events'));
    }

    public function test_notification_preferences_update_applies_partial_changes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['notifications:write']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->patchJson('/api/v1/notification-preferences', [
                'preferences' => [
                    ['event' => 'tenant_invite', 'channel' => 'email', 'enabled' => false],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.applied_count', 1);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'event_type' => 'tenant_invite',
            'channel' => 'email',
            'enabled' => false,
        ]);
    }

    public function test_notification_preferences_update_skips_unknown_events(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['notifications:write']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->patchJson('/api/v1/notification-preferences', [
                'preferences' => [
                    ['event' => 'bogus.event', 'channel' => 'email', 'enabled' => false],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.applied_count', 0);

        $this->assertCount(1, $response->json('data.skipped'));
    }
}
