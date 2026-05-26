<?php

namespace App\Support\Billing\Checkout;

use App\Events\CheckoutAbandoned;
use App\Events\CheckoutCompleted;
use App\Events\CheckoutStarted;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Single seam for CheckoutSession lifecycle. Controllers + UI always go
 * through this — never `new CheckoutSession` directly.
 *
 * See agent-os/product/checkout.md §3 for the flow.
 */
class CheckoutService
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Start a checkout for the given user + tenant + plan.
     *
     * FAST-PATH for free plans: skips the gateway picker entirely. Records
     * the Subscription synchronously and returns a *completed* session
     * whose subscription_id points at the new free sub. Callers should
     * detect this and route directly to the tenant dashboard.
     *
     * @param  array<string, mixed>  $context  optional: metadata
     */
    public function start(User $user, Tenant $tenant, Plan $plan, array $context = []): CheckoutSession
    {
        if (! $plan->is_active) {
            throw new InvalidArgumentException("Plan [{$plan->slug}] is not active.");
        }

        return DB::transaction(function () use ($user, $tenant, $plan, $context) {
            $session = CheckoutSession::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
                'status' => CheckoutSession::STATUS_PENDING,
                'currency' => $plan->currency,
                'amount_cents' => (int) $plan->price_cents,
                'expires_at' => now()->addMinutes(
                    (int) config('billing.checkout.timeout_minutes', 30)
                ),
                'metadata' => $context['metadata'] ?? [],
            ]);

            // Free-plan fast-path: skip the gateway picker. Subscribe right
            // now and mark the session completed in the same transaction.
            if ((int) $plan->price_cents === 0) {
                $subscription = $this->billing->subscribeToPlan(
                    $tenant,
                    $plan,
                    'free',
                );

                // Link the new free Subscription back to this session.
                $subscription->forceFill(['checkout_session_id' => $session->id])->save();

                $session->forceFill([
                    'gateway' => 'free',
                    'status' => CheckoutSession::STATUS_COMPLETED,
                    'subscription_id' => $subscription->id,
                    'completed_at' => now(),
                ])->save();
            }

            Event::dispatch(new CheckoutStarted($session->fresh()));

            return $session->fresh();
        });
    }

    /**
     * User picked a gateway → driver's initiateCheckout was already called
     * upstream and persisted the result. This method exists for symmetry
     * + future hooks; today it just dispatches a (deferred) event.
     */
    public function pickedGateway(CheckoutSession $session): void
    {
        // Hook for any post-pick side effects (audit log, etc.).
    }

    /**
     * Mark a session canceled. The user clicked "cancel" on the gateway,
     * or our return-URL detected a cancel state.
     */
    public function cancel(CheckoutSession $session, string $reason = 'user_canceled'): CheckoutSession
    {
        if ($session->isTerminal()) {
            return $session;
        }

        $session->forceFill([
            'status' => CheckoutSession::STATUS_CANCELED,
            'canceled_at' => now(),
            'cancel_reason' => $reason,
        ])->save();

        return $session->fresh();
    }

    /**
     * Sweep job entry-point. Marks expired sessions and dispatches the
     * abandonment event. Called from ExpireStaleCheckouts.
     *
     * @return int Number of sessions expired.
     */
    public function expireStale(): int
    {
        $stale = CheckoutSession::query()
            ->whereIn('status', [
                CheckoutSession::STATUS_PENDING,
                CheckoutSession::STATUS_AWAITING_PAYMENT,
            ])
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $session) {
            $session->forceFill([
                'status' => CheckoutSession::STATUS_EXPIRED,
                'canceled_at' => now(),
                'cancel_reason' => 'expired',
            ])->save();

            Event::dispatch(new CheckoutAbandoned($session->fresh()));
        }

        return $stale->count();
    }

    /**
     * Find any non-terminal session for a tenant — used by the plan picker
     * to show a "resume" banner.
     */
    public function activeSessionFor(Tenant $tenant): ?CheckoutSession
    {
        return CheckoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [
                CheckoutSession::STATUS_PENDING,
                CheckoutSession::STATUS_AWAITING_PAYMENT,
            ])
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Reconcile a webhook completion. Called by driver handlers from
     * onCheckoutCompleted — wraps the BillingService::*FromCheckout helpers
     * so the per-driver code stays small.
     *
     * @param  array<string, mixed>  $payload  Driver-extracted fields
     */
    public function complete(CheckoutSession $session, array $payload): CheckoutSession
    {
        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            return $session; // idempotent re-delivery
        }

        $result = DB::transaction(function () use ($session, $payload) {
            $subscription = $this->billing->subscriptionFromCheckout($session, $payload);
            $invoice = $this->billing->invoiceFromCheckout($session, $payload, $subscription);
            $this->billing->paymentFromCheckout($session, $payload, $invoice);

            $session->forceFill([
                'subscription_id' => $subscription?->id,
                'invoice_id' => $invoice?->id,
                'status' => CheckoutSession::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            return $session->fresh();
        });

        Event::dispatch(new CheckoutCompleted($result));

        return $result;
    }
}
