<?php

namespace Database\Factories;

use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MagicLoginToken>
 */
class MagicLoginTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(40)),
            'requested_ip' => '127.0.0.1',
            'requested_user_agent' => 'PHPUnit',
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => null,
            'consumed_ip' => null,
        ];
    }
}
