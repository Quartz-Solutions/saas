<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds the default Free / Pro / Enterprise plan catalog from
 * config/billing.php into the `plans` table.
 *
 * Three-tier shape:
 *   - free          $0/mo   — basic permissions (community support, basic
 *                             analytics, 1 project, 3 seats, 1 GB).
 *   - pro           $20/mo  — advanced analytics, API access, webhooks,
 *                             priority support, 20 seats, 100 GB, unlimited
 *                             projects.
 *   - enterprise    $100/mo — full feature set: SSO/SAML, audit log export,
 *                             dedicated account manager, custom SLA, +
 *                             unlimited everything.
 *
 * Uses updateOrCreate so re-running the seeder syncs price + feature
 * changes back into existing plan rows. Admins can still override per-plan
 * via /admin/plans afterwards.
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

            Plan::updateOrCreate(
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

        $this->command?->info('PlansSeeder: '.count($plans).' default plans synced into the plans table.');
    }
}
