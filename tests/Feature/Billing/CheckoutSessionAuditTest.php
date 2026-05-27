<?php

namespace Tests\Feature\Billing;

use App\Models\AuditLog;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSessionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_session_writes_audit_log(): void
    {
        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        app(CheckoutService::class)->start($owner, $tenant, $plan);

        $created = AuditLog::query()
            ->where('auditable_type', CheckoutSession::class)
            ->where('action', 'created')
            ->first();
        $this->assertNotNull($created);
        $this->assertSame(CheckoutSession::STATUS_PENDING, $created->new_values['status'] ?? null);
    }

    public function test_state_transition_writes_audit_row(): void
    {
        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $service = app(CheckoutService::class);
        $session = $service->start($owner, $tenant, $plan);
        AuditLog::query()->delete(); // ignore creation rows

        $service->cancel($session, 'user_canceled');

        $update = AuditLog::query()
            ->where('auditable_type', CheckoutSession::class)
            ->where('auditable_id', $session->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();
        $this->assertNotNull($update);
        $this->assertSame(CheckoutSession::STATUS_PENDING, $update->old_values['status'] ?? null);
        $this->assertSame(CheckoutSession::STATUS_CANCELED, $update->new_values['status'] ?? null);
        $this->assertSame('user_canceled', $update->new_values['cancel_reason'] ?? null);
    }

    public function test_payload_only_changes_do_not_write_audit_rows(): void
    {
        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $session = app(CheckoutService::class)->start($owner, $tenant, $plan);
        AuditLog::query()->delete();

        // Simulate a result_payload-only mutation (no status change).
        $session->forceFill(['result_payload' => ['url' => 'https://example.test/x']])->save();

        $rows = AuditLog::query()
            ->where('auditable_type', CheckoutSession::class)
            ->where('auditable_id', $session->id)
            ->count();
        $this->assertSame(0, $rows);
    }
}
