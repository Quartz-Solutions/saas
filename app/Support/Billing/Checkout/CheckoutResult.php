<?php

namespace App\Support\Billing\Checkout;

/**
 * Base class for the discriminated union of "what the front-end must do next
 * to drive the customer to completion." Each concrete subclass is one of the
 * 5 supported next-step kinds (see agent-os/product/checkout.md §5.2).
 */
abstract class CheckoutResult
{
    public function __construct(
        public readonly string $kind,
        public readonly string $gatewaySessionId,
        public readonly ?int $expiresAt = null,
    ) {}

    /**
     * Shape persisted into checkout_sessions.result_payload (jsonb). The
     * /checkout React page reads this back to render the next step.
     *
     * @return array<string, mixed>
     */
    abstract public function toPayload(): array;
}
