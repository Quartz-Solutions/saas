<?php

namespace App\Support\Billing;

use App\Models\CheckoutSession;
use App\Support\Billing\Checkout\CheckoutResult;

/**
 * Driver contract for one-click checkout. Every gateway that wants to be
 * picked from /checkout's gateway picker implements this — alongside the
 * existing PaymentGateway (post-checkout: refunds + sync) and optionally
 * SubscriptionGateway (post-checkout: change plan / cancel / resume).
 *
 * See agent-os/product/checkout.md §5 for the full design.
 */
interface CheckoutGateway
{
    /**
     * Initiate checkout for the given session. Implementations MUST:
     *
     *   1. Call the gateway API (or build a signed form)
     *   2. Update $session: gateway, gateway_session_id,
     *                       status='awaiting_payment',
     *                       result_kind, result_payload, expires_at
     *   3. Return the matching CheckoutResult subclass
     *
     * MUST be idempotent: re-calling on a session that's already
     * awaiting_payment should re-build / re-return the same CheckoutResult
     * without creating a duplicate at the gateway side. Use the session's
     * stored gateway_session_id to dedupe.
     */
    public function initiateCheckout(CheckoutSession $session): CheckoutResult;

    /**
     * Currencies this gateway can settle in. Drives the gateway picker's
     * filter — gateways that don't support the plan's currency are hidden.
     *
     * @return array<int, string> ISO 4217 codes, uppercase.
     */
    public function supportedCurrencies(): array;

    /**
     * Whether this gateway natively supports recurring subscriptions
     * (vs merchant-side MIT). When false + session.intent='subscription',
     * the picker hides this gateway.
     */
    public function supportsSubscriptions(): bool;
}
