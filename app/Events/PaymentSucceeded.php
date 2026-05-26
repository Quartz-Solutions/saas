<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder event — emitted by Phase 3's BillingService once it merges.
 */
class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public int $paymentId,
        public int $amountCents,
        public string $currency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toWebhookPayload(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
        ];
    }
}
