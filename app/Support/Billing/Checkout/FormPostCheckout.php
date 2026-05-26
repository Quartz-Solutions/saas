<?php

namespace App\Support\Billing\Checkout;

use App\Models\CheckoutSession;

/**
 * Browser auto-submits a hidden form to {action}. Used by APS (Payfort) and
 * iPay88, where the merchant signs a parameter set and the customer's browser
 * POSTs it to the gateway's hosted card page.
 */
final class FormPostCheckout extends CheckoutResult
{
    /**
     * @param  array<string, string|int>  $params  Hidden inputs (sensitive: signed already).
     */
    public function __construct(
        string $gatewaySessionId,
        public readonly string $action,
        public readonly array $params,
        public readonly string $method = 'POST',
        ?int $expiresAt = null,
    ) {
        parent::__construct(CheckoutSession::KIND_FORM_POST, $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return [
            'action' => $this->action,
            'method' => $this->method,
            'params' => $this->params,
        ];
    }
}
