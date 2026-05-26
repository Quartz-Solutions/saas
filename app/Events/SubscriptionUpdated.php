<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder event — emitted by Phase 3's BillingService once it merges.
 * Wired here so the webhook subscriber file is ready to receive it.
 */
class SubscriptionUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public Tenant $tenant,
        public int $subscriptionId,
        public string $status,
        public array $changes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toWebhookPayload(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'status' => $this->status,
            'changes' => $this->changes,
        ];
    }
}
