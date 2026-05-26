<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'slug',
    'name',
    'description',
    'price_cents',
    'currency',
    'billing_period',
    'billing_interval',
    'trial_days',
    'features',
    'gateway_ids',
    'is_active',
    'is_public',
    'sort_order',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'gateway_ids' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Sentinel for unlimited quotas. Stored verbatim in plans.features.
     */
    public const UNLIMITED = -1;

    /**
     * Whether this plan includes the given feature slug. Booleans count when
     * the value is true; quotas count when the limit is > 0 or unlimited.
     *
     * Slug catalog lives in config('billing.features').
     */
    public function hasFeature(string $slug): bool
    {
        $features = (array) ($this->features ?? []);
        if (! array_key_exists($slug, $features)) {
            return false;
        }

        $value = $features[$slug];

        // false / 0 → explicitly not included
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        return true;
    }

    /**
     * Quota for a feature. Returns:
     *   null when the feature is unlimited (sentinel -1 OR boolean true)
     *   0    when the feature is not included
     *   int  otherwise (the limit)
     *
     * Use null-checks for "is unlimited?" since (null < N) is always false.
     */
    public function featureLimit(string $slug): ?int
    {
        $features = (array) ($this->features ?? []);
        if (! array_key_exists($slug, $features)) {
            return 0;
        }

        $value = $features[$slug];

        if ($value === self::UNLIMITED || $value === true) {
            return null;
        }
        if ($value === false) {
            return 0;
        }
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    /**
     * Render the plan's features with display metadata resolved from the
     * catalog. For quota features the display name reflects the actual
     * limit ("20 team members" / "Unlimited team members"). Unknown slugs,
     * unincluded items (value=0 or false), and quota=0 entries are dropped.
     *
     * @return array<int, array{slug: string, name: string, type: string, value: mixed, description: ?string, category: string}>
     */
    public function featuresWithMetadata(): array
    {
        $catalog = (array) config('billing.features', []);
        $resolved = [];

        foreach ((array) ($this->features ?? []) as $slug => $value) {
            if (! is_string($slug) || ! isset($catalog[$slug])) {
                continue;
            }

            $meta = $catalog[$slug];
            $type = (string) ($meta['type'] ?? 'boolean');

            $display = null;
            if ($type === 'quota') {
                if ($value === self::UNLIMITED || $value === true) {
                    $display = (string) ($meta['unlimited_label'] ?? $meta['name']);
                } elseif (is_numeric($value) && (int) $value > 0) {
                    $unit = (string) ($meta['unit'] ?? '');
                    $display = $value.' '.Str::plural($unit, (int) $value);
                }
            } else { // boolean
                if ($value === true || $value === 1 || $value === '1') {
                    $display = (string) ($meta['name'] ?? $slug);
                }
            }

            if ($display === null) {
                continue;
            }

            $resolved[] = [
                'slug' => $slug,
                'name' => $display,
                'type' => $type,
                'value' => $value,
                'description' => $meta['description'] ?? null,
                'category' => (string) ($meta['category'] ?? 'Other'),
            ];
        }

        return $resolved;
    }
}
