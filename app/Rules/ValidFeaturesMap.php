<?php

namespace App\Rules;

use App\Models\Plan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the plans.features map submitted from /admin/plans/{create,edit}.
 *
 * Shape: array<string, bool|int> where:
 *   - keys are slugs defined in config('billing.features')
 *   - boolean features carry a truthy value
 *   - quota features carry a non-negative int OR the unlimited sentinel (-1)
 *
 * Fails fast at the first invalid key/value so the admin sees one specific
 * error rather than a wall.
 */
class ValidFeaturesMap implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === []) {
            return; // empty map is allowed (plan with no features)
        }

        if (! is_array($value)) {
            $fail('The features field must be a map of slug → value.');

            return;
        }

        $catalog = (array) config('billing.features', []);

        foreach ($value as $slug => $entry) {
            if (! is_string($slug) || $slug === '') {
                $fail('Each feature key must be a non-empty string slug.');

                return;
            }

            if (! array_key_exists($slug, $catalog)) {
                $fail("Unknown feature [{$slug}].");

                return;
            }

            $type = (string) ($catalog[$slug]['type'] ?? 'boolean');

            if ($type === 'boolean') {
                if (! in_array($entry, [true, 1, '1', 'true', 'on'], true)) {
                    $fail("Feature [{$slug}] is a boolean; value must be truthy or omitted.");

                    return;
                }

                continue;
            }

            // Quota
            if (! is_numeric($entry)) {
                $fail("Feature [{$slug}] is a quota; value must be a number or -1 for unlimited.");

                return;
            }

            $int = (int) $entry;
            if ($int !== Plan::UNLIMITED && $int < 0) {
                $fail("Feature [{$slug}] cannot be negative (use -1 for unlimited).");

                return;
            }
        }
    }
}
