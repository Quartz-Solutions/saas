<?php

namespace Database\Factories;

use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Theme>
 */
class ThemeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Theme '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'is_active' => false,
            'is_preset' => false,
            'mode_hint' => 'both',
            'tokens' => [
                'light' => ['--primary' => 'oklch(0.52 0.13 152)'],
                'dark' => ['--primary' => 'oklch(0.7 0.14 152)'],
            ],
            'radius' => '0.625rem',
            'font_family' => null,
            'custom_css_path' => null,
            'compiled_css_path' => null,
            'compiled_at' => null,
            'preview_image_path' => null,
            'created_by_id' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function preset(): static
    {
        return $this->state(fn () => ['is_preset' => true]);
    }
}
