<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function __invoke(): Response
    {
        $plans = array_values(config('billing.plans', []));

        return Inertia::render('marketing/pricing', [
            'plans' => $plans,
            'trialDays' => (int) config('billing.trial_days', 14),
            'defaultCurrency' => (string) config('billing.default_currency', 'USD'),
        ]);
    }
}
