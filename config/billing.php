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
    | Features catalog
    |---------------------------------------------------------------------
    | Master registry of every feature the SaaS exposes. Plans reference
    | features by SLUG (the array key); code gates with hasFeature() /
    | featureLimit() on Plan and Tenant.
    |
    | Two feature types:
    |
    |   type: 'boolean'  → present or absent on a plan.
    |                      Stored as: { "api_access": true }
    |
    |   type: 'quota'    → present with a numeric limit, or unlimited.
    |                      Stored as: { "projects": 5 } or { "projects": -1 }
    |                      `-1` is the unlimited sentinel.
    |                      `unit` is the singular unit name; Str::plural
    |                      handles "1 project" vs "20 projects".
    |                      `unlimited_label` is the bullet shown on /pricing
    |                      when value === -1.
    |
    | Add a new feature here first; the admin plan builder reads this catalog
    | and the ValidFeaturesMap rule rejects unknown slugs / wrong-typed values.
    */

    'features' => [

        // Core
        'projects' => [
            'name' => 'Projects',
            'description' => 'Top-level workspaces a tenant can create.',
            'category' => 'Core',
            'type' => 'quota',
            'unit' => 'project',
            'unlimited_label' => 'Unlimited projects',
        ],
        'storage_gb' => [
            'name' => 'Storage',
            'description' => 'Disk space across uploads + attachments.',
            'category' => 'Core',
            'type' => 'quota',
            'unit' => 'GB',
            'unlimited_label' => 'Unlimited storage',
        ],
        'basic_analytics' => [
            'name' => 'Basic analytics',
            'description' => null,
            'category' => 'Core',
            'type' => 'boolean',
        ],
        'advanced_analytics' => [
            'name' => 'Advanced analytics',
            'description' => null,
            'category' => 'Core',
            'type' => 'boolean',
        ],

        // Team
        'team_seats' => [
            'name' => 'Team members',
            'description' => 'Users that can be invited to the tenant.',
            'category' => 'Team',
            'type' => 'quota',
            'unit' => 'team member',
            'unlimited_label' => 'Unlimited team members',
        ],

        // Support
        'community_support' => [
            'name' => 'Community support',
            'description' => null,
            'category' => 'Support',
            'type' => 'boolean',
        ],
        'priority_support' => [
            'name' => 'Priority email support',
            'description' => null,
            'category' => 'Support',
            'type' => 'boolean',
        ],
        'dedicated_account_manager' => [
            'name' => 'Dedicated account manager',
            'description' => null,
            'category' => 'Support',
            'type' => 'boolean',
        ],
        'custom_sla' => [
            'name' => 'Custom SLA',
            'description' => null,
            'category' => 'Support',
            'type' => 'boolean',
        ],

        // Integrations
        'api_access' => [
            'name' => 'API access',
            'description' => 'Personal access tokens + /api/v1 endpoints.',
            'category' => 'Integrations',
            'type' => 'boolean',
        ],
        'webhooks' => [
            'name' => 'Outbound webhooks',
            'description' => 'Subscribe to tenant events.',
            'category' => 'Integrations',
            'type' => 'boolean',
        ],

        // Security & compliance
        'sso_saml' => [
            'name' => 'SSO / SAML',
            'description' => null,
            'category' => 'Security & compliance',
            'type' => 'boolean',
        ],
        'audit_log_export' => [
            'name' => 'Audit log export',
            'description' => null,
            'category' => 'Security & compliance',
            'type' => 'boolean',
        ],

    ],

    /*
    |---------------------------------------------------------------------
    | Plans
    |---------------------------------------------------------------------
    | Source of truth for the plan picker UI and the public pricing page.
    | The Stripe price ids in `gateway_prices.stripe` are looked up at
    | runtime by StripeGateway::createSubscription().
    |
    | `features` values must be slugs defined above.
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
                // booleans
                'community_support' => true,
                'basic_analytics' => true,
                // quotas (integer = limit; -1 = unlimited)
                'projects' => 1,
                'team_seats' => 3,
                'storage_gb' => 1,
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
                'priority_support' => true,
                'advanced_analytics' => true,
                'api_access' => true,
                'webhooks' => true,
                'projects' => -1,        // unlimited
                'team_seats' => 20,
                'storage_gb' => 100,
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
                'priority_support' => true,
                'advanced_analytics' => true,
                'api_access' => true,
                'webhooks' => true,
                'sso_saml' => true,
                'dedicated_account_manager' => true,
                'custom_sla' => true,
                'audit_log_export' => true,
                'projects' => -1,
                'team_seats' => -1,
                'storage_gb' => -1,
            ],
            'cta' => 'Contact sales',
            'highlighted' => false,
            'gateway_prices' => [
                'stripe' => env('STRIPE_PRICE_ENTERPRISE'),
            ],
        ],

    ],

];
