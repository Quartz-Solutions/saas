<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price_cents' => (int) $this->price_cents,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period,
            'billing_interval' => (int) $this->billing_interval,
            'trial_days' => (int) $this->trial_days,
            'features' => array_map(
                static fn (array $f) => [
                    'slug' => $f['slug'],
                    'name' => $f['name'],
                    'category' => $f['category'],
                ],
                $this->featuresWithMetadata(),
            ),
            'is_active' => (bool) $this->is_active,
            'is_public' => (bool) $this->is_public,
        ];
    }
}
