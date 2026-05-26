<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.Str::lower(Str::random(14)).'_'.fake()->unique()->numerify('####'),
            'event_type' => 'invoice.paid',
            'payload' => [],
            'headers' => null,
            'signature' => null,
            'status' => 'received',
            'processing_attempts' => 0,
            'error_message' => null,
            'received_at' => now(),
            'processed_at' => null,
            'tenant_id' => null,
        ];
    }
}
