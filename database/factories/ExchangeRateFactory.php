<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = Currency::factory()->create();
        $target = Currency::factory()->create();

        return [
            'base_currency' => $base->code,
            'target_currency' => $target->code,
            'rate' => fake()->randomFloat(8, 1.0, 2.0),
            'source' => 'manual',
            'fetched_at' => now(),
        ];
    }
}
