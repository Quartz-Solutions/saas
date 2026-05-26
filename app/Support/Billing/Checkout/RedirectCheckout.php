<?php

namespace App\Support\Billing\Checkout;

use App\Models\CheckoutSession;

/**
 * Browser hard-redirects to {url}. Used by Stripe Checkout, PayPal approval,
 * PayTabs hosted PayPage, Geidea session, Telr, MyFatoorah, HitPay, Billplz.
 */
final class RedirectCheckout extends CheckoutResult
{
    public function __construct(
        string $gatewaySessionId,
        public readonly string $url,
        ?int $expiresAt = null,
    ) {
        parent::__construct(CheckoutSession::KIND_REDIRECT, $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return ['url' => $this->url];
    }
}
