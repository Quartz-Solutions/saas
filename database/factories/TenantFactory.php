<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2]
        );

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'logo_path' => null,
            'owner_id' => User::factory(),
            'locale' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'active',
            'settings' => [],
            'trial_ends_at' => null,
        ];
    }
}
