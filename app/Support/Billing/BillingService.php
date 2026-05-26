<?php

namespace App\Support\Billing;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Canonical service for all billing lifecycle mutations.
 *
 * Per CLAUDE.md "service-layer single seam": every cross-cutting billing
 * write goes through this class. Controllers and jobs call this; direct
 * Subscription / Invoice / Payment writes outside this class are bugs.
 *
 * Gateways are resolved via GatewayRegistry — never via app(StripeGateway::class).
 */
class BillingService
{
    public function __construct(
        private readonly GatewayRegistry $registry,
    ) {}

    /**
     * Find or upsert the local Plan row for a slug defined in config('billing.plans').
     */
    public function planForSlug(string $slug): Plan
    {
        $config = config("billing.plans.{$slug}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown plan slug [{$slug}].");
        }

        $currency = strtoupper((string) ($config['currency'] ?? config('billing.default_currency', 'USD')));
        Currency::firstOrCreate(
            ['code' => $currency],
            ['name' => $currency, 'symbol' => $currency, 'decimal_places' => 2],
        );

        return Plan::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $config['name'],
                'description' => $config['description'] ?? null,
                'price_cents' => (int) ($config['price_cents'] ?? 0),
                'currency' => $config['currency'] ?? config('billing.default_currency', 'USD'),
                'billing_period' => $config['interval'] ?? 'month',
                'billing_interval' => 1,
                'trial_days' => (int) ($config['trial_days'] ?? config('billing.trial_days', 0)),
                'features' => $config['features'] ?? [],
                'gateway_ids' => $config['gateway_prices'] ?? [],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 0,
            ],
        );
    }

    /**
     * Get the tenant's currently-active or trialing subscription, or null.
     */
    public function currentSubscription(Tenant $tenant): ?Subscription
    {
        return Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latest('id')
            ->first();
    }

    /**
     * Subscribe a tenant to a plan via the named gateway.
     *
     * If the plan is the free tier, no gateway round-trip is made and a
     * local-only Subscription is recorded.
     *
     * @param  array<string, mixed>  $context
     */
    public function subscribeToPlan(Tenant $tenant, Plan $plan, string $gatewayId, array $context = []): Subscription
    {
        if ((int) $plan->price_cents === 0) {
            return $this->recordFreeSubscription($tenant, $plan, $gatewayId);
        }

        $gateway = $this->registry->subscriptions($gatewayId);

        return $gateway->createSubscription($tenant, $plan, $context);
    }

    /**
     * Move an existing subscription to a different plan.
     *
     * @param  array<string, mixed>  $context
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        if ((int) $newPlan->price_cents === 0) {
            // Downgrade to free → cancel paid sub immediately, then start a
            // local-only free row.
            $this->cancel($subscription, 'downgrade_to_free', ['immediately' => true]);

            return $this->recordFreeSubscription($subscription->tenant, $newPlan, $subscription->gateway);
        }

        $gateway = $this->registry->subscriptions($subscription->gateway);

        return $gateway->changePlan($subscription, $newPlan, $context);
    }

    /**
     * Cancel a subscription. Default is at-period-end. Pass
     * `['immediately' => true]` to cancel right now.
     *
     * @param  array<string, mixed>  $context
     */
    public function cancel(Subscription $subscription, ?string $reason = null, array $context = []): Subscription
    {
        if ($subscription->gateway === 'free' || (int) $subscription->unit_amount_cents === 0) {
            return DB::transaction(function () use ($subscription, $reason) {
                $subscription->forceFill([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'cancellation_reason' => $reason,
                    'ends_at' => now(),
                ])->save();

                return $subscription->fresh();
            });
        }

        $gateway = $this->registry->subscriptions($subscription->gateway);
        $subscription = $gateway->cancel($subscription, $context);

        if ($reason !== null) {
            $subscription->forceFill(['cancellation_reason' => $reason])->save();
        }

        return $subscription->fresh();
    }

    /**
     * Re-activate a cancel_at_period_end subscription.
     */
    public function resume(Subscription $subscription): Subscription
    {
        $gateway = $this->registry->subscriptions($subscription->gateway);

        return $gateway->resume($subscription);
    }

    /**
     * Record a Payment row against an invoice. Sole entry-point for
     * payment writes outside the gateway implementations.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordPayment(Invoice $invoice, array $attributes): Payment
    {
        return DB::transaction(function () use ($invoice, $attributes) {
            $payment = new Payment;
            $payment->forceFill(array_merge([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'currency' => $invoice->currency,
            ], $attributes))->save();

            if (($attributes['status'] ?? null) === 'succeeded') {
                $paid = (int) $invoice->amount_paid_cents + (int) ($attributes['amount_cents'] ?? 0);
                $due = max(0, (int) $invoice->total_cents - $paid);

                $invoice->forceFill([
                    'amount_paid_cents' => $paid,
                    'amount_due_cents' => $due,
                    'status' => $due === 0 ? 'paid' : $invoice->status,
                    'paid_at' => $due === 0 ? now() : $invoice->paid_at,
                ])->save();
            }

            return $payment->fresh();
        });
    }

    /**
     * Create a "free" subscription with no gateway round-trip.
     */
    private function recordFreeSubscription(Tenant $tenant, Plan $plan, string $gatewayId): Subscription
    {
        Currency::firstOrCreate(
            ['code' => $plan->currency],
            ['name' => $plan->currency, 'symbol' => $plan->currency, 'decimal_places' => 2],
        );

        return DB::transaction(function () use ($tenant, $plan, $gatewayId) {
            $subscription = new Subscription;
            $subscription->forceFill([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'gateway' => $gatewayId,
                'gateway_subscription_id' => null,
                'status' => 'active',
                'currency' => $plan->currency,
                'unit_amount_cents' => 0,
                'quantity' => 1,
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
                'cancel_at_period_end' => false,
                'metadata' => ['source' => 'free_plan'],
            ])->save();

            return $subscription->fresh();
        });
    }
}
