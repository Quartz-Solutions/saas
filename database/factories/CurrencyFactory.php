<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'name' => fake()->words(2, true),
            'symbol' => fake()->randomElement(['$', '€', '£', '¥', '₹']),
            'decimal_places' => 2,
            'rounding_increment' => 1,
            'is_active' => true,
        ];
    }
}
