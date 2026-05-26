<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Billing\BillingService;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\PaymentGateway;
use App\Support\Billing\SubscriptionGateway;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionActionsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    /**
     * Free-tier subscription so BillingService can mutate it without hitting
     * a real gateway. We swap a fake gateway into the registry for the few
     * tests that go through Stripe-paths.
     */
    private function seedFreeSub(string $planSlug = 'pro'): Subscription
    {
        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', $planSlug)->firstOrFail();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme '.uniqid()]);

        return Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'free',
            'gateway_subscription_id' => null,
            'currency' => $plan->currency,
            'unit_amount_cents' => 0,
            'quantity' => 1,
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addMonth(),
        ]);
    }

    public function test_change_plan_requires_super_admin(): void
    {
        $user = User::factory()->create();
        $sub = $this->seedFreeSub();

        $this->actingAs($user)
            ->post("/admin/subscriptions/{$sub->id}/change-plan", ['plan_id' => 1])
            ->assertStatus(403);
    }

    public function test_change_plan_to_free_downgrades_and_audits(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub('pro');
        $freePlan = Plan::query()->where('slug', 'free')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/subscriptions/{$sub->id}/change-plan", [
                'plan_id' => $freePlan->id,
                'prorate' => false,
                'admin_note' => 'Customer requested downgrade.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.subscription.plan_changed',
            'auditable_type' => Subscription::class,
            'auditable_id' => $sub->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_cancel_records_reason_and_audit(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub();

        $this->actingAs($admin)
            ->post("/admin/subscriptions/{$sub->id}/cancel", [
                'reason' => 'customer_request',
                'immediately' => true,
            ])
            ->assertRedirect();

        $sub->refresh();
        $this->assertSame('canceled', $sub->status);
        $this->assertSame('customer_request', $sub->cancellation_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.subscription.canceled',
            'auditable_id' => $sub->id,
        ]);
    }

    public function test_cancel_validates_reason_against_catalog(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub();

        $this->actingAs($admin)
            ->from("/admin/subscriptions/{$sub->id}")
            ->post("/admin/subscriptions/{$sub->id}/cancel", ['reason' => 'not_a_real_reason'])
            ->assertSessionHasErrors('reason');
    }

    public function test_reactivate_only_when_pending_cancellation(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub();
        // Not pending cancellation → reactivate should bail with a toast.

        $this->actingAs($admin)
            ->post("/admin/subscriptions/{$sub->id}/reactivate")
            ->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'admin.subscription.reactivated',
            'auditable_id' => $sub->id,
        ]);
    }

    public function test_apply_credit_persists_grant_in_metadata(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub();

        $this->actingAs($admin)
            ->post("/admin/subscriptions/{$sub->id}/credit", [
                'amount_cents' => 500,
                'reason' => 'goodwill',
                'admin_note' => 'Outage compensation.',
            ])
            ->assertRedirect();

        $sub->refresh();
        $this->assertIsArray($sub->metadata);
        $this->assertNotEmpty($sub->metadata['credits'] ?? []);
        $this->assertSame(500, $sub->metadata['credits'][0]['amount_cents']);
        $this->assertSame('goodwill', $sub->metadata['credits'][0]['reason']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.subscription.credit_applied',
            'auditable_id' => $sub->id,
        ]);
    }

    public function test_apply_credit_rejects_zero_amount(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub();

        $this->actingAs($admin)
            ->from("/admin/subscriptions/{$sub->id}")
            ->post("/admin/subscriptions/{$sub->id}/credit", [
                'amount_cents' => 0,
                'reason' => 'goodwill',
            ])
            ->assertSessionHasErrors('amount_cents');
    }

    public function test_comp_months_extends_period_and_records_invoice(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub('pro');
        $sub->forceFill(['unit_amount_cents' => 2900])->save();

        $originalEnd = $sub->current_period_end;

        $this->actingAs($admin)
            ->post("/admin/subscriptions/{$sub->id}/comp", [
                'months' => 2,
                'reason' => 'service_incident',
            ])
            ->assertRedirect();

        $sub->refresh();
        $this->assertEquals(
            $originalEnd->copy()->addMonths(2)->toDateString(),
            $sub->current_period_end->toDateString(),
        );

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $sub->tenant_id,
            'subscription_id' => $sub->id,
            'status' => 'paid',
            'total_cents' => 0,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.subscription.comped',
            'auditable_id' => $sub->id,
        ]);
    }

    public function test_refund_payment_calls_gateway_and_audits(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub('pro');

        $invoice = Invoice::factory()->create([
            'tenant_id' => $sub->tenant_id,
            'subscription_id' => $sub->id,
            'status' => 'paid',
            'currency' => 'USD',
            'total_cents' => 2900,
        ]);

        $payment = Payment::factory()->create([
            'tenant_id' => $sub->tenant_id,
            'invoice_id' => $invoice->id,
            'gateway' => 'stripe',
            'status' => 'succeeded',
            'amount_cents' => 2900,
            'currency' => 'USD',
        ]);

        // Swap a fake stripe gateway into the registry.
        $registry = new GatewayRegistry;
        $fake = Mockery::mock(PaymentGateway::class.','.SubscriptionGateway::class);
        $fake->shouldReceive('id')->andReturn('stripe');
        $fake->shouldReceive('displayName')->andReturn('Stripe');
        $fake->shouldReceive('refund')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->id === $payment->id), 1000)
            ->andReturnUsing(function ($p, $cents) {
                $p->forceFill([
                    'refunded_cents' => $cents,
                    'status' => 'partially_refunded',
                    'refunded_at' => now(),
                ])->save();

                return $p->fresh();
            });
        $registry->register($fake);
        $this->app->instance(GatewayRegistry::class, $registry);
        $this->app->forgetInstance(BillingService::class);

        $this->actingAs($admin)
            ->post("/admin/payments/{$payment->id}/refund", [
                'amount_cents' => 1000,
                'reason' => 'customer_request',
            ])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(1000, (int) $payment->refunded_cents);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.payment.refunded',
            'auditable_id' => $sub->id,
        ]);
    }

    public function test_record_manual_payment_creates_payment_marks_invoice_paid(): void
    {
        $admin = $this->makeSuperAdmin();
        $sub = $this->seedFreeSub('pro');

        $invoice = Invoice::factory()->create([
            'tenant_id' => $sub->tenant_id,
            'subscription_id' => $sub->id,
            'status' => 'open',
            'currency' => 'USD',
            'total_cents' => 2900,
            'amount_paid_cents' => 0,
            'amount_due_cents' => 2900,
        ]);

        $this->actingAs($admin)
            ->post("/admin/invoices/{$invoice->id}/manual-payment", [
                'amount_cents' => 2900,
                'method' => 'wire',
                'reference' => 'WT-12345',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $sub->tenant_id,
            'invoice_id' => $invoice->id,
            'gateway' => 'manual',
            'status' => 'succeeded',
            'amount_cents' => 2900,
        ]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.invoice.manual_payment_recorded',
            'auditable_id' => $sub->id,
        ]);
    }
}
