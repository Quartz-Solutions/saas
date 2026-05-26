<?php

namespace Database\Factories;

use App\Models\OutboundWebhook;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboundWebhook>
 */
class OutboundWebhookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by_id' => User::factory(),
            'url' => 'https://'.fake()->domainName().'/webhooks/'.Str::lower(Str::random(8)),
            'description' => fake()->optional()->sentence(),
            'secret' => 'whsec_'.Str::random(48),
            'events' => ['tenant.member.invited', 'payment.succeeded'],
            'is_active' => true,
            'failure_count' => 0,
            'last_delivery_at' => null,
            'disabled_at' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
