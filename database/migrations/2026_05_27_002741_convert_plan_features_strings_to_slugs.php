<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * One-time conversion of plans.features into the new typed-map shape
 * { slug => bool | int } where:
 *   - booleans store true
 *   - quotas store an integer limit (or -1 for unlimited)
 *
 * Handles three legacy input shapes:
 *   1. Already an object/associative map → keep as-is.
 *   2. Flat array of slugs (mid-format)    → map slug => true.
 *   3. Flat array of display strings       → best-effort match name to slug
 *      via case-insensitive lookup, then map slug => true. Quota info that
 *      may have been embedded ("Up to 20 team members") is lost — none of
 *      the seeded plans relied on it.
 *
 * Unknown strings / unknown slugs are dropped (they couldn't be gated on
 * anyway). Down is a no-op — the map is the canonical shape going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        $catalog = (array) config('billing.features', []);
        if ($catalog === []) {
            return;
        }

        $slugSet = array_fill_keys(array_keys($catalog), true);
        $nameToSlug = [];
        foreach ($catalog as $slug => $meta) {
            $name = Str::lower((string) ($meta['name'] ?? ''));
            if ($name !== '') {
                $nameToSlug[$name] = $slug;
            }
            // Also accept the unlimited_label as an alias when available.
            $unlimited = Str::lower((string) ($meta['unlimited_label'] ?? ''));
            if ($unlimited !== '') {
                $nameToSlug[$unlimited] = $slug;
            }
        }

        Plan::query()->withTrashed()->each(function (Plan $plan) use ($slugSet, $nameToSlug): void {
            $current = $plan->features;

            // Already in map shape — keep, but filter unknown keys.
            if (is_array($current) && self::isAssociative($current)) {
                $map = [];
                foreach ($current as $slug => $value) {
                    if (isset($slugSet[$slug])) {
                        $map[$slug] = $value;
                    }
                }
                if ($map !== $current) {
                    $plan->forceFill(['features' => $map])->saveQuietly();
                }

                return;
            }

            // Flat array (either slugs or display strings) → map of slug => true.
            $map = [];
            foreach ((array) $current as $entry) {
                if (! is_string($entry) || $entry === '') {
                    continue;
                }

                if (isset($slugSet[$entry])) {
                    $map[$entry] = true;

                    continue;
                }

                $key = Str::lower($entry);
                if (isset($nameToSlug[$key])) {
                    $map[$nameToSlug[$key]] = true;
                }
                // Otherwise drop.
            }

            if ($map !== $current) {
                $plan->forceFill(['features' => $map])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // No-op. The map is the canonical shape.
    }

    private static function isAssociative(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
};
