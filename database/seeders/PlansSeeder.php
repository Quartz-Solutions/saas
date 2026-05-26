<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the default Free/Pro/Enterprise plans from config/billing.php into
 * the `plans` table for fresh installs. After this runs, the DB is the
 * source-of-truth; admin manages plans through /admin/plans, and the
 * config block becomes a one-time seed reference.
 *
 * Idempotent — uses firstOrCreate against slug.
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CurrencySeeder::class);

        $plans = (array) config('billing.plans', []);
        $defaultCurrency = (string) config('billing.default_currency', 'USD');

        foreach ($plans as $slug => $cfg) {
            $currency = strtoupper((string) ($cfg['currency'] ?? $defaultCurrency));
            Currency::firstOrCreate(
                ['code' => $currency],
                ['name' => $currency, 'symbol' => $currency, 'decimal_places' => 2],
            );

            $sortOrder = match ($slug) {
                'free' => 10,
                'pro' => 20,
                'enterprise' => 30,
                default => 100,
            };

            Plan::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $cfg['name'] ?? ucfirst($slug),
                    'description' => $cfg['description'] ?? null,
                    'price_cents' => (int) ($cfg['price_cents'] ?? 0),
                    'currency' => $currency,
                    'billing_period' => (string) ($cfg['interval'] ?? 'month'),
                    'billing_interval' => 1,
                    'trial_days' => (int) ($cfg['trial_days'] ?? config('billing.trial_days', 0)),
                    'features' => (array) ($cfg['features'] ?? []),
                    'gateway_ids' => (array) ($cfg['gateway_prices'] ?? []),
                    'is_active' => true,
                    'is_public' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        $this->command?->info('PlansSeeder: '.count($plans).' default plans ready in the plans table.');
    }
}
