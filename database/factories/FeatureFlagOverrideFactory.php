<?php

namespace Database\Factories;

use App\Models\FeatureFlag;
use App\Models\FeatureFlagOverride;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureFlagOverride>
 */
class FeatureFlagOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature_flag_id' => FeatureFlag::factory(),
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'enabled' => true,
            'expires_at' => null,
            'created_by_id' => null,
            'reason' => null,
        ];
    }
}
