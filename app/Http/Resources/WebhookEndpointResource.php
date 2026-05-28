<?php

namespace App\Http\Resources;

use App\Models\OutboundWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutboundWebhook
 */
class WebhookEndpointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'url' => $this->url,
            'description' => $this->description,
            'events' => (array) $this->events,
            'is_active' => (bool) $this->is_active,
            'failure_count' => (int) $this->failure_count,
            'last_delivery_at' => $this->last_delivery_at?->toIso8601String(),
            'disabled_at' => $this->disabled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
