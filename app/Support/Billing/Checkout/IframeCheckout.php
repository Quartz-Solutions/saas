<?php

namespace App\Support\Billing\Checkout;

use App\Models\CheckoutSession;

/**
 * Renders the gateway's hosted page inside an iframe. Used by Paymob's
 * Unified Checkout. The iframe sends a postMessage to the parent on
 * completion so the React side can navigate to /checkout/{id}/return.
 */
final class IframeCheckout extends CheckoutResult
{
    /**
     * @param  array<string, mixed>  $iframeAttributes  height, allow, sandbox, etc.
     */
    public function __construct(
        string $gatewaySessionId,
        public readonly string $iframeUrl,
        public readonly array $iframeAttributes = [],
        ?int $expiresAt = null,
    ) {
        parent::__construct(CheckoutSession::KIND_IFRAME, $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return [
            'iframe_url' => $this->iframeUrl,
            'iframe_attributes' => $this->iframeAttributes,
        ];
    }
}
