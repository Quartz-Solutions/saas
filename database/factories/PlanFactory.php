<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
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

        $name = 'Pro '.fake()->unique()->numberBetween(1, 99999);

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(1000, 50000),
            'currency' => 'USD',
            'billing_period' => 'month',
            'billing_interval' => 1,
            'trial_days' => 0,
            'features' => [],
            'gateway_ids' => [],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }
}
