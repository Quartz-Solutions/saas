<?php

namespace App\Support\Billing\Checkout;

use App\Models\CheckoutSession;

/**
 * Shows the customer an offline reference code to pay at a kiosk/branch.
 * Used by Fawry. No browser redirect — the user takes the code to a physical
 * Fawry outlet, pays in cash; the gateway webhook arrives days later.
 */
final class KioskReferenceCheckout extends CheckoutResult
{
    public function __construct(
        string $gatewaySessionId,
        public readonly string $reference,
        public readonly ?string $instructionsUrl = null,
        ?int $expiresAt = null,
    ) {
        parent::__construct(CheckoutSession::KIND_KIOSK_REF, $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return [
            'reference' => $this->reference,
            'instructions_url' => $this->instructionsUrl,
        ];
    }
}
