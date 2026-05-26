<?php

namespace App\Support\Billing\Checkout;

use App\Models\CheckoutSession;

/**
 * Renders an in-page widget by loading a gateway-provided script that takes
 * over a target element. Used by HyperPay's COPYandPAY (paymentWidgets.js).
 */
final class WidgetCheckout extends CheckoutResult
{
    /**
     * @param  array<string, mixed>  $widgetConfig  Passed to the widget bootstrap.
     */
    public function __construct(
        string $gatewaySessionId,
        public readonly string $scriptUrl,
        public readonly array $widgetConfig,
        ?int $expiresAt = null,
    ) {
        parent::__construct(CheckoutSession::KIND_WIDGET, $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return [
            'script_url' => $this->scriptUrl,
            'widget_config' => $this->widgetConfig,
        ];
    }
}
