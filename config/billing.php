<?php

/*
|--------------------------------------------------------------------------
| Billing Configuration (Phase 3 — Stripe core)
|--------------------------------------------------------------------------
|
| Single source of truth for plans, gateways, trial length. The driver
| registry (App\Support\Billing\GatewayRegistry) reads `gateways.*.enabled`
| at boot in AppServiceProvider::register() — flipping a flag here is the
| only thing required to disable a gateway.
|
| All monetary values are integer cents — boilerplate convention.
|
*/

return [

    /*
    |---------------------------------------------------------------------
    | Defaults
    |---------------------------------------------------------------------
    */

    'default_currency' => env('BILLING_DEFAULT_CURRENCY', 'USD'),

    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'stripe'),

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    /*
    |---------------------------------------------------------------------
    | Dunning (failed-payment retry backoff in days)
    |---------------------------------------------------------------------
    | After max_attempts failed retries the subscription is moved from
    | past_due to cancelled.
    */

    'dunning' => [
        'backoff_days' => [1, 3, 7],
        'max_attempts' => 3,
    ],

    /*
    |---------------------------------------------------------------------
    | Gateways
    |---------------------------------------------------------------------
    | Phase 3.1 ships Stripe only. PayPal + regional gateways
    | (Paymob, Fawry, PayTabs, Geidea, Telr, HyperPay, MyFatoorah,
    | HitPay, Billplz, iPay88) ship in later phases.
    */

    'gateways' => [

        'stripe' => [
            'enabled' => filled(env('STRIPE_SECRET')),
            'public_key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance_seconds' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
            'api_version' => env('STRIPE_API_VERSION', '2024-11-20.acacia'),
            'portal_return_path' => env('STRIPE_PORTAL_RETURN_PATH', '/'),
            'checkout_success_path' => env('STRIPE_CHECKOUT_SUCCESS_PATH', '/'),
            'checkout_cancel_path' => env('STRIPE_CHECKOUT_CANCEL_PATH', '/'),
        ],

    ],

    /*
    |---------------------------------------------------------------------
    | Plans
    |---------------------------------------------------------------------
    | Source of truth for the plan picker UI and the public pricing page.
    | The Stripe price ids in `gateway_prices.stripe` are looked up at
    | runtime by StripeGateway::createSubscription().
    */

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
            'gateway_prices' => [
                'stripe' => null,
            ],
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
            'gateway_prices' => [
                'stripe' => env('STRIPE_PRICE_PRO'),
            ],
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
            'gateway_prices' => [
                'stripe' => env('STRIPE_PRICE_ENTERPRISE'),
            ],
        ],

    ],

];
