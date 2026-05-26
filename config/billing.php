<?php

/*
|--------------------------------------------------------------------------
| Billing Configuration (Phase 7 stub)
|--------------------------------------------------------------------------
|
| This stub powers the public pricing page (resources/js/pages/marketing/pricing.tsx).
| Phase 3 (Billing) will replace this with the full driver-registry config —
| gateway enable flags, per-region defaults, trial length, etc.
|
| All monetary values are integer cents — boilerplate convention (CLAUDE.md).
|
*/

return [

    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'USD'),

    'trial_days' => 14,

    'plans' => [

        'free' => [
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'For solo builders kicking the tires.',
            'price_cents' => 0,
            'currency' => 'USD',
            'interval' => 'month',
            'features' => [
                '1 project',
                'Community support',
                'Up to 3 team members',
                'Basic analytics',
            ],
            'cta' => 'Start free',
            'highlighted' => false,
        ],

        'pro' => [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Everything a growing SaaS needs.',
            'price_cents' => 2900,
            'currency' => 'USD',
            'interval' => 'month',
            'features' => [
                'Unlimited projects',
                'Priority email support',
                'Up to 20 team members',
                'Advanced analytics',
                'API access',
                'Webhooks',
            ],
            'cta' => 'Start 14-day trial',
            'highlighted' => true,
        ],

        'enterprise' => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'For organisations with custom needs.',
            'price_cents' => 9900,
            'currency' => 'USD',
            'interval' => 'month',
            'features' => [
                'Everything in Pro',
                'SSO / SAML',
                'Dedicated account manager',
                'Custom SLA',
                'Unlimited team members',
                'Audit log export',
            ],
            'cta' => 'Contact sales',
            'highlighted' => false,
        ],

    ],

];
