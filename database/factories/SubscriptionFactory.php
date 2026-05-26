<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2]
        );

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'payment_method_id' => null,
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_'.Str::lower(Str::random(14)),
            'status' => 'active',
            'currency' => 'USD',
            'unit_amount_cents' => 999,
            'quantity' => 1,
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(30),
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'cancellation_reason' => null,
            'ends_at' => null,
            'metadata' => [],
        ];
    }
}
