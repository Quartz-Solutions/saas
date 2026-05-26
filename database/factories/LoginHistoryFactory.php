<?php

namespace Database\Factories;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginHistory>
 */
class LoginHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email' => fake()->safeEmail(),
            'outcome' => fake()->randomElement(['succeeded', 'failed', 'locked']),
            'method' => 'password',
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'country' => 'US',
            'city' => fake()->city(),
            'context' => null,
            'created_at' => now(),
        ];
    }
}
