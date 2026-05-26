<?php

namespace App\Support\Billing;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
     * Issue account credit applied to the subscription's next invoice.
     * Stripe records it as a balanceTransaction; locally we stash the
     * grant on subscription.metadata.credits so it shows in audit + UI.
     *
     * @param  array<string, mixed>  $context  extra metadata persisted with the grant
     */
    public function applyCredit(Subscription $subscription, int $amountCents, string $reason, array $context = []): Subscription
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        if ($subscription->gateway === 'stripe' && filled($subscription->gateway_subscription_id)) {
            $gateway = $this->registry->get('stripe');
            if (method_exists($gateway, 'applyCredit')) {
                $gateway->applyCredit($subscription, $amountCents, $reason);
            }
        }

        return DB::transaction(function () use ($subscription, $amountCents, $reason, $context) {
            $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
            $credits = (array) ($metadata['credits'] ?? []);
            $credits[] = [
                'amount_cents' => $amountCents,
                'reason' => $reason,
                'context' => $context,
                'at' => now()->toIso8601String(),
            ];
            $metadata['credits'] = $credits;

            $subscription->forceFill(['metadata' => $metadata])->save();

            return $subscription->fresh();
        });
    }

    /**
     * Comp N additional months by pushing current_period_end forward AND
     * recording a $0 paid invoice for the audit trail. Gateway-agnostic —
     * Stripe-side, the comp shows as a coupon-less period extension which
     * we approximate with a balance credit equal to N × price.
     */
    public function compMonths(Subscription $subscription, int $months, string $reason): Subscription
    {
        if ($months <= 0) {
            throw new InvalidArgumentException('Comp months must be a positive integer.');
        }

        return DB::transaction(function () use ($subscription, $months, $reason) {
            $end = $subscription->current_period_end ?? now();
            $newEnd = $end->copy()->addMonths($months);

            $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
            $comps = (array) ($metadata['comps'] ?? []);
            $comps[] = [
                'months' => $months,
                'reason' => $reason,
                'extended_to' => $newEnd->toIso8601String(),
                'at' => now()->toIso8601String(),
            ];
            $metadata['comps'] = $comps;

            $subscription->forceFill([
                'current_period_end' => $newEnd,
                'metadata' => $metadata,
            ])->save();

            $compAmount = (int) $subscription->unit_amount_cents * $months;

            $invoice = new Invoice;
            $invoice->forceFill([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'gateway' => $subscription->gateway,
                'number' => 'COMP-'.now()->format('Ymd-His').'-'.Str::random(4),
                'status' => 'paid',
                'currency' => $subscription->currency,
                'subtotal_cents' => $compAmount,
                'discount_cents' => $compAmount,
                'tax_cents' => 0,
                'total_cents' => 0,
                'amount_paid_cents' => 0,
                'amount_due_cents' => 0,
                'issued_at' => now(),
                'paid_at' => now(),
                'metadata' => ['source' => 'admin_comp', 'months' => $months, 'reason' => $reason],
            ])->save();

            return $subscription->fresh();
        });
    }

    /**
     * Refund a payment via its gateway and persist the new state locally.
     * Pass amountCents=null for a full refund.
     */
    public function refundPayment(Payment $payment, ?int $amountCents = null, ?string $reason = null): Payment
    {
        $gateway = $this->registry->get($payment->gateway);
        $payment = $gateway->refund($payment, $amountCents);

        if ($reason !== null) {
            $metadata = is_array($payment->metadata) ? $payment->metadata : [];
            $metadata['refund_reason'] = $reason;
            $payment->forceFill(['metadata' => $metadata])->save();
        }

        return $payment->fresh();
    }

    /**
     * Record a manual / offline payment against an invoice. Used for wire
     * transfers, cash, cheques — anything that bypasses an automated
     * gateway. Marks the invoice paid on success.
     */
    public function recordManualPayment(Invoice $invoice, int $amountCents, string $method, ?string $reference = null): Payment
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Manual payment amount must be positive.');
        }

        $manualId = 'manual_'.now()->timestamp.'_'.Str::random(8);

        return $this->recordPayment($invoice, [
            'gateway' => 'manual',
            'gateway_payment_id' => $manualId,
            'status' => 'succeeded',
            'amount_cents' => $amountCents,
            'captured_at' => now(),
            'idempotency_key' => $manualId,
            'metadata' => [
                'method' => $method,
                'reference' => $reference,
                'source' => 'admin_manual',
            ],
        ]);
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
