<?php

namespace Database\Factories;

use App\Models\Theme;
use App\Models\ThemeFont;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ThemeFont>
 */
class ThemeFontFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $family = fake()->randomElement(['Roboto', 'Inter', 'Lora', 'Poppins']);

        return [
            'theme_id' => Theme::factory(),
            'family' => $family,
            'weight' => '400',
            'style' => 'normal',
            'format' => 'woff2',
            'path' => 'themes/fonts/'.Str::random(40).'.woff2',
            'original_filename' => "{$family}-Regular.woff2",
            'size_bytes' => fake()->numberBetween(10000, 200000),
        ];
    }
}
