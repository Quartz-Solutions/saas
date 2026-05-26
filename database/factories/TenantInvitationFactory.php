<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantInvitation>
 */
class TenantInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invited_by_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => null,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_by_id' => null,
            'revoked_at' => null,
        ];
    }
}
