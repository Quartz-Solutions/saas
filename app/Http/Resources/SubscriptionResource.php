<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'plan_slug' => $this->plan?->slug,
            'plan_name' => $this->plan?->name,
            'gateway' => $this->gateway,
            'gateway_subscription_id' => $this->gateway_subscription_id,
            'status' => $this->status,
            'currency' => $this->currency,
            'unit_amount_cents' => (int) $this->unit_amount_cents,
            'quantity' => (int) ($this->quantity ?? 1),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'cancellation_reason' => $this->cancellation_reason,
            'trial_starts_at' => $this->trial_starts_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
