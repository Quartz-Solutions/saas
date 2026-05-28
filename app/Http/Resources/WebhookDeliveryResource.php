<?php

namespace App\Http\Resources;

use App\Models\OutboundWebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutboundWebhookDelivery
 */
class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outbound_webhook_id' => $this->outbound_webhook_id,
            'event_type' => $this->event_type,
            'event_id' => $this->event_id,
            'status' => $this->status,
            'attempt' => (int) $this->attempt,
            'response_code' => $this->response_code,
            'duration_ms' => $this->duration_ms,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'next_retry_at' => $this->next_retry_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
