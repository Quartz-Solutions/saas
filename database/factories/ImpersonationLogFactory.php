<?php

namespace Database\Factories;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImpersonationLog>
 */
class ImpersonationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'impersonator_id' => User::factory(),
            'impersonated_id' => User::factory(),
            'tenant_id' => null,
            'started_at' => now(),
            'ended_at' => null,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'reason' => null,
        ];
    }
}
