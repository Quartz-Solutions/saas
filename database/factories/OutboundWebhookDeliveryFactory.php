<?php

namespace Database\Factories;

use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboundWebhookDelivery>
 */
class OutboundWebhookDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outbound_webhook_id' => OutboundWebhook::factory(),
            'event_type' => 'tenant.member.invited',
            'event_id' => (string) Str::uuid(),
            'payload' => ['event' => 'tenant.member.invited', 'data' => []],
            'signature' => str_repeat('a', 64),
            'attempt' => 1,
            'status' => OutboundWebhookDelivery::STATUS_PENDING,
            'response_code' => null,
            'response_body' => null,
            'duration_ms' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'next_retry_at' => null,
        ];
    }
}
