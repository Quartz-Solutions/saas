<?php

namespace Tests\Feature\Api;

use App\Jobs\DeliverWebhookJob;
use App\Models\OutboundWebhook;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhooksApiTest extends TestCase
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
        $tenant = app(TenantService::class)->create($owner, ['name' => 'WHooks']);

        return [$owner, $tenant];
    }

    public function test_index_requires_token(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $this->getJson('/api/v1/tenants/'.$tenant->slug.'/webhooks')->assertUnauthorized();
    }

    public function test_index_rejects_wrong_ability(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['billing:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/webhooks')
            ->assertForbidden();
    }

    public function test_store_creates_endpoint_and_reveals_secret_once(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['webhooks:write']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/webhooks', [
                'url' => 'https://example.test/hook',
                'events' => ['tenant.member.invited'],
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'url', 'events', 'secret']]);

        $this->assertStringStartsWith('whsec_', $response->json('data.secret'));
    }

    public function test_store_validates_event_against_catalog(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $token = $owner->createToken('cli', ['webhooks:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/webhooks', [
                'url' => 'https://example.test/hook',
                'events' => ['totally.fake.event'],
            ])
            ->assertStatus(422);
    }

    public function test_rotate_secret_returns_new_value(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $hook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_id' => $owner->id,
            'events' => ['tenant.member.invited'],
        ]);
        $token = $owner->createToken('cli', ['webhooks:write']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/webhooks/'.$hook->id.'/rotate-secret')
            ->assertOk();

        $this->assertStringStartsWith('whsec_', $response->json('data.secret'));
        $this->assertNotSame($hook->secret, $response->json('data.secret'));
    }

    public function test_test_fire_enqueues_delivery(): void
    {
        Queue::fake();
        [$owner, $tenant] = $this->withTenant();
        $hook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_id' => $owner->id,
            'events' => ['tenant.member.invited'],
        ]);
        $token = $owner->createToken('cli', ['webhooks:write']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/webhooks/'.$hook->id.'/test')
            ->assertAccepted();

        $this->assertNotNull($response->json('data.delivery_id'));
        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_destroy_removes_endpoint(): void
    {
        [$owner, $tenant] = $this->withTenant();
        $hook = OutboundWebhook::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by_id' => $owner->id,
            'events' => ['tenant.member.invited'],
        ]);
        $token = $owner->createToken('cli', ['webhooks:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->deleteJson('/api/v1/tenants/'.$tenant->slug.'/webhooks/'.$hook->id)
            ->assertNoContent();

        $this->assertNull(OutboundWebhook::find($hook->id));
    }
}
