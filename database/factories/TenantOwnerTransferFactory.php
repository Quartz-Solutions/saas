<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantOwnerTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantOwnerTransfer>
 */
class TenantOwnerTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'current_owner_id' => User::factory(),
            'new_owner_id' => User::factory(),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(3),
            'accepted_at' => null,
            'cancelled_at' => null,
        ];
    }
}
