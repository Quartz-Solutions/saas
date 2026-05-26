<?php

namespace Database\Factories;

use App\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FeatureFlag>
 */
class FeatureFlagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'key' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'enabled_globally' => false,
            'rules' => null,
        ];
    }
}
