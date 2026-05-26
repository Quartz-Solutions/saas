<?php

namespace App\Support\Billing;

use App\Models\Plan;
use App\Support\Billing\Stripe\StripeGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Single seam for plan-catalog mutations.
 *
 * Plans are now DB-owned (Phase A — admin manages via /admin/plans). The
 * config/billing.php block becomes a seed for fresh installs; runtime
 * source-of-truth is the `plans` table.
 *
 * When a gateway is enabled, save() pushes the plan to that gateway:
 * Stripe Prices are created on save (and re-created when price/currency/
 * period/interval changes — Stripe Price objects are immutable, so the
 * old Price ID continues to bill existing subscribers).
 */
class PlanService
{
    public function __construct(
        private readonly GatewayRegistry $registry,
    ) {}

    /**
     * Create or update a plan. Returns the persisted row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function save(?Plan $plan, array $attributes): Plan
    {
        $plan ??= new Plan;

        $slug = $this->resolveSlug($plan, $attributes);

        return DB::transaction(function () use ($plan, $attributes, $slug) {
            $plan->fill([
                'slug' => $slug,
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'price_cents' => (int) ($attributes['price_cents'] ?? 0),
                'currency' => strtoupper((string) ($attributes['currency'] ?? config('billing.default_currency', 'USD'))),
                'billing_period' => (string) ($attributes['billing_period'] ?? 'month'),
                'billing_interval' => max(1, (int) ($attributes['billing_interval'] ?? 1)),
                'trial_days' => max(0, (int) ($attributes['trial_days'] ?? 0)),
                'features' => $this->sanitizeFeatures((array) ($attributes['features'] ?? [])),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'is_public' => (bool) ($attributes['is_public'] ?? true),
                'sort_order' => (int) ($attributes['sort_order'] ?? 0),
            ]);

            $plan->save();

            $this->syncToGateways($plan);

            return $plan->fresh();
        });
    }

    /**
     * Archive (soft delete) a plan. Blocked if any non-terminal subscription
     * still references it — those need to be migrated first.
     */
    public function archive(Plan $plan): Plan
    {
        $activeCount = $plan->subscriptions()
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->count();

        if ($activeCount > 0) {
            throw new RuntimeException(
                "Cannot archive plan with {$activeCount} active or trialing subscriptions. Migrate them to another plan first."
            );
        }

        $plan->forceFill(['is_active' => false, 'is_public' => false])->save();
        $plan->delete();

        return $plan->fresh() ?? $plan;
    }

    /**
     * Restore an archived plan. Defaults to non-public so admin can review
     * + flip is_public on a follow-up edit.
     */
    public function restore(Plan $plan): Plan
    {
        $plan->restore();
        $plan->forceFill(['is_active' => true])->save();

        return $plan->fresh();
    }

    /**
     * Push the plan to every enabled gateway that knows how to sync prices.
     * No-op when no gateways are registered (e.g. test environments).
     */
    protected function syncToGateways(Plan $plan): void
    {
        foreach ($this->registry->all() as $gateway) {
            if ($gateway instanceof StripeGateway) {
                $gateway->syncPriceForPlan($plan);
            }
            // PayPal + regional gateways implement the same method in Phase 3.2+.
        }
    }

    /**
     * Defensive filter for the features map. Drops unknown slugs and coerces
     * values to the type declared in the catalog:
     *   - boolean: keeps `true` only; anything else is dropped
     *   - quota:   keeps non-negative ints + the -1 unlimited sentinel
     *
     * FormRequests already enforce this for admin-driven saves, but seeders
     * and factories can call save() directly, so we re-check here.
     *
     * @param  array<string, mixed>|array<int, mixed>  $features
     * @return array<string, bool|int>
     */
    protected function sanitizeFeatures(array $features): array
    {
        $catalog = (array) config('billing.features', []);
        $out = [];

        foreach ($features as $slug => $value) {
            if (! is_string($slug) || ! array_key_exists($slug, $catalog)) {
                continue;
            }

            $type = (string) ($catalog[$slug]['type'] ?? 'boolean');

            if ($type === 'quota') {
                if ($value === true || $value === Plan::UNLIMITED) {
                    $out[$slug] = Plan::UNLIMITED;

                    continue;
                }
                if (is_numeric($value)) {
                    $int = (int) $value;
                    if ($int < 0 && $int !== Plan::UNLIMITED) {
                        continue;
                    }
                    if ($int === 0) {
                        // Explicit zero quota → treat as "not included" + drop.
                        continue;
                    }
                    $out[$slug] = $int;
                }
                // null / false / strings other than numeric → drop.

                continue;
            }

            // Boolean feature.
            if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                $out[$slug] = true;
            }
            // false / 0 / unset → drop.
        }

        return $out;
    }

    protected function resolveSlug(Plan $plan, array $attributes): string
    {
        if (! empty($attributes['slug'])) {
            return Str::slug((string) $attributes['slug']);
        }

        if ($plan->exists && $plan->slug !== null) {
            return $plan->slug;
        }

        return Str::slug((string) $attributes['name']);
    }
}
