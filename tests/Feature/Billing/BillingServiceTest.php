<?php

namespace Tests\Feature\Billing;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Billing\BillingService;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_to_free_plan_creates_local_subscription(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        /** @var BillingService $billing */
        $billing = app(BillingService::class);

        $plan = $billing->planForSlug('free');
        $subscription = $billing->subscribeToPlan($tenant, $plan, 'stripe');

        $this->assertSame('active', $subscription->status);
        $this->assertSame(0, (int) $subscription->unit_amount_cents);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame($tenant->id, $subscription->tenant_id);
    }

    public function test_cancel_free_subscription_marks_canceled(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $billing = app(BillingService::class);
        $plan = $billing->planForSlug('free');
        $subscription = $billing->subscribeToPlan($tenant, $plan, 'stripe');

        $cancelled = $billing->cancel($subscription, 'Testing');

        $this->assertSame('canceled', $cancelled->status);
        $this->assertSame('Testing', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->canceled_at);
        $this->assertNotNull($cancelled->ends_at);
    }

    public function test_record_payment_updates_invoice_paid_status(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'subtotal_cents' => 1000,
            'total_cents' => 1000,
            'amount_paid_cents' => 0,
            'amount_due_cents' => 1000,
            'status' => 'open',
        ]);

        $billing = app(BillingService::class);
        $payment = $billing->recordPayment($invoice, [
            'tenant_id' => $tenant->id,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'pi_test',
            'status' => 'succeeded',
            'amount_cents' => 1000,
        ]);

        $this->assertSame('succeeded', $payment->status);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(1000, (int) $invoice->amount_paid_cents);
        $this->assertSame(0, (int) $invoice->amount_due_cents);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_current_subscription_returns_latest_active(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $billing = app(BillingService::class);

        $this->assertNull($billing->currentSubscription($tenant));

        $billing->subscribeToPlan($tenant, $billing->planForSlug('free'), 'stripe');

        $this->assertNotNull($billing->currentSubscription($tenant));
    }
}
