<?php

namespace App\Support\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Driver contract for subscription lifecycle across gateways.
 *
 * Implementations are expected to ALSO implement PaymentGateway — most
 * real-world gateways merge both concerns. The interfaces stay separate
 * so non-recurring gateways (e.g. Fawry kiosk codes) can implement only
 * PaymentGateway without faking a subscription engine.
 */
interface SubscriptionGateway
{
    /**
     * Start a subscription on the gateway and persist a local row.
     *
     * @param  array<string, mixed>  $context  Free-form: payment_method, trial_days override, metadata, success/cancel urls...
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription;

    /**
     * Move a subscription to a different plan. By default the swap is
     * prorated and takes effect immediately.
     *
     * @param  array<string, mixed>  $context
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription;

    /**
     * Cancel a subscription. Default is at-period-end; pass
     * `['immediately' => true]` to cancel right now.
     *
     * @param  array<string, mixed>  $context
     */
    public function cancel(Subscription $subscription, array $context = []): Subscription;

    /**
     * Re-activate a cancel_at_period_end subscription.
     */
    public function resume(Subscription $subscription): Subscription;

    /**
     * Pull canonical state from the gateway and update the local row.
     */
    public function syncFromGateway(Subscription $subscription): Subscription;
}
