<?php

namespace App\Support\Billing;

use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;

/**
 * Driver contract for payment processing across gateways.
 *
 * Phase 3.0 — Stripe is the only implementation that ships. PayPal +
 * regional gateways implement this in later phases.
 *
 * All amounts in integer cents. All currencies in ISO 4217.
 */
interface PaymentGateway
{
    /**
     * Machine identifier used in URLs, DB rows, registry lookup.
     * e.g. 'stripe', 'paypal', 'paymob'.
     */
    public function id(): string;

    /**
     * Human-readable display name (for UI dropdowns).
     */
    public function displayName(): string;

    /**
     * Charge a customer in a single call (auth+capture).
     * Returns the persisted Payment row.
     *
     * @param  array<string, mixed>  $context  Free-form, gateway-specific bag (idempotency_key, description, metadata, customer_id, payment_method...).
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment;

    /**
     * Authorize a card without capturing.
     *
     * @param  array<string, mixed>  $context
     */
    public function authorize(int $amountCents, string $currency, array $context = []): Payment;

    /**
     * Capture an existing authorization (partial allowed via amount).
     */
    public function capture(Payment $payment, ?int $amountCents = null): Payment;

    /**
     * Refund a (fully) captured payment. Partial via $amountCents.
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment;

    /**
     * Void an uncaptured authorization.
     */
    public function void(Payment $payment): Payment;

    /**
     * Re-sync a single payment's status from the gateway.
     */
    public function status(Payment $payment): Payment;

    /**
     * Handle an inbound webhook from this gateway. Implementations MUST:
     *   1) verify the signature
     *   2) idempotently dispatch on event type
     *   3) update the matching WebhookEvent row's status
     *
     * Returns the persisted WebhookEvent row.
     */
    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent;
}
