<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function __invoke(): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get()
            ->map(fn (Plan $p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'description' => $p->description ?? '',
                'price_cents' => (int) $p->price_cents,
                'currency' => $p->currency,
                'interval' => $p->billing_period,
                // Display labels resolved from config('billing.features') —
                // unknown slugs are dropped by featuresWithMetadata().
                'features' => array_map(
                    fn (array $f) => $f['name'],
                    $p->featuresWithMetadata(),
                ),
                'cta' => (int) $p->price_cents === 0
                    ? 'Start free'
                    : ($p->trial_days > 0 ? "Start {$p->trial_days}-day trial" : 'Choose plan'),
                'highlighted' => false,
            ])
            ->all();

        return Inertia::render('marketing/pricing', [
            'plans' => $plans,
            'trialDays' => (int) config('billing.trial_days', 14),
            'defaultCurrency' => (string) config('billing.default_currency', 'USD'),
        ]);
    }
}
