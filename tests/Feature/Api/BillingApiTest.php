<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $plain): array
    {
        app('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'];
    }

    public function test_plans_index_requires_billing_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('cli', ['profile:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/plans')
            ->assertForbidden();
    }

    public function test_plans_index_returns_active_public_plans(): void
    {
        Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'is_active' => true, 'is_public' => true]);
        Plan::factory()->create(['slug' => 'hidden', 'is_active' => true, 'is_public' => false]);

        $user = User::factory()->create();
        $token = $user->createToken('cli', ['billing:read']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/plans')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertContains('pro', $slugs);
        $this->assertNotContains('hidden', $slugs);
    }

    public function test_subscription_current_returns_null_when_none(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        $token = $user->createToken('cli', ['billing:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/subscription')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_subscription_current_returns_active_subscription(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        $plan = Plan::factory()->create(['slug' => 'pro']);
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $token = $user->createToken('cli', ['billing:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/subscription')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'plan_slug', 'status']])
            ->assertJsonPath('data.plan_slug', 'pro');
    }

    public function test_invoices_index_paginated_with_filter(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        Invoice::factory()->count(3)->create(['tenant_id' => $tenant->id, 'status' => 'paid']);
        Invoice::factory()->count(1)->create(['tenant_id' => $tenant->id, 'status' => 'open']);

        $token = $user->createToken('cli', ['billing:read']);

        $response = $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/invoices?status=paid')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);

        foreach ($response->json('data') as $invoice) {
            $this->assertSame('paid', $invoice['status']);
        }
    }

    public function test_payments_show_404_when_cross_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        $other = User::factory()->create();
        $otherTenant = app(TenantService::class)->create($other, ['name' => 'X']);
        $payment = Payment::factory()->create(['tenant_id' => $otherTenant->id]);

        $token = $user->createToken('cli', ['billing:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->getJson('/api/v1/tenants/'.$tenant->slug.'/payments/'.$payment->id)
            ->assertNotFound();
    }

    public function test_subscription_cancel_rejects_without_billing_write(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        $token = $user->createToken('cli', ['billing:read']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/subscription/cancel')
            ->assertForbidden();
    }

    public function test_subscription_cancel_returns_422_when_nothing_active(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'T']);
        $token = $user->createToken('cli', ['billing:write']);

        $this->withHeaders($this->headers($token->plainTextToken))
            ->postJson('/api/v1/tenants/'.$tenant->slug.'/subscription/cancel')
            ->assertStatus(422);
    }
}
