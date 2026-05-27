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
    | Checkout
    |---------------------------------------------------------------------
    | Lifetime of a CheckoutSession before it's marked expired by the
    | ExpireStaleCheckouts sweep job (registered in routes/console.php).
    */

    'checkout' => [
        'timeout_minutes' => (int) env('CHECKOUT_TIMEOUT_MINUTES', 120),
    ],

    /*
    |---------------------------------------------------------------------
    | Gateways
    |---------------------------------------------------------------------
    | Catalog + runtime config for every payment gateway. Admin manages
    | credentials through /admin/gateways; values flow into Config::set via
    | AppSettingsServiceProvider, so flipping a key here is rarely needed.
    |
    | Per-gateway shape:
    |   name, description, regions[], capabilities[], driver_status,
    |   documentation_url        — UI metadata
    |   enabled, *_key, *_secret — runtime values (read by code)
    |   fields[]                 — admin field declarations
    |
    | driver_status:
    |   'shipped' → driver class exists + has been tested
    |   'planned' → catalog entry only; class is a scaffold that throws
    |               on real operations until the driver is implemented.
    |
    | capabilities:
    |   subscriptions | one_time | refunds | customer_portal
    */

    'gateways' => [

        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Cards, ACH, wallets. Stripe Billing for subscriptions; Customer Portal for self-service.',
            'regions' => ['Global'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'customer_portal'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://docs.stripe.com/',

            'enabled' => env('STRIPE_ENABLED', filled(env('STRIPE_SECRET'))),
            'public_key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance_seconds' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
            'api_version' => env('STRIPE_API_VERSION', '2024-11-20.acacia'),
            'portal_return_path' => env('STRIPE_PORTAL_RETURN_PATH', '/'),
            'checkout_success_path' => env('STRIPE_CHECKOUT_SUCCESS_PATH', '/'),
            'checkout_cancel_path' => env('STRIPE_CHECKOUT_CANCEL_PATH', '/'),

            'fields' => [
                'STRIPE_ENABLED' => [
                    'config_path' => 'billing.gateways.stripe.enabled',
                    'type' => 'bool',
                    'rules' => 'boolean',
                    'label' => 'Enabled',
                ],
                'STRIPE_KEY' => [
                    'config_path' => 'billing.gateways.stripe.public_key',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Publishable key',
                    'help' => 'Safe to expose to the browser. Starts with pk_test_ or pk_live_.',
                ],
                'STRIPE_SECRET' => [
                    'config_path' => 'billing.gateways.stripe.secret',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Secret key',
                    'help' => 'Server-side only. Starts with sk_test_ or sk_live_.',
                ],
                'STRIPE_WEBHOOK_SECRET' => [
                    'config_path' => 'billing.gateways.stripe.webhook_secret',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Webhook signing secret',
                    'help' => 'Starts with whsec_. From Stripe Dashboard → Developers → Webhooks.',
                ],
                'STRIPE_API_VERSION' => [
                    'config_path' => 'billing.gateways.stripe.api_version',
                    'type' => 'string',
                    'default' => '2024-11-20.acacia',
                    'rules' => 'nullable|string|max:64',
                    'label' => 'API version',
                ],
                'STRIPE_PRICE_PRO' => [
                    'config_path' => 'billing.plans.pro.gateway_prices.stripe',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:64',
                    'label' => 'Pro plan price ID',
                ],
                'STRIPE_PRICE_ENTERPRISE' => [
                    'config_path' => 'billing.plans.enterprise.gateway_prices.stripe',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:64',
                    'label' => 'Enterprise plan price ID',
                ],
            ],
        ],

        'paypal' => [
            'name' => 'PayPal',
            'description' => 'Subscriptions API + Orders v2 for one-off; ~200 markets, 24 currencies. RSA-SHA256 webhook signatures.',
            'regions' => ['Global (~200 markets)'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://developer.paypal.com/docs/api/subscriptions/v1/',

            'enabled' => env('PAYPAL_ENABLED', false),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),

            'fields' => [
                'PAYPAL_ENABLED' => ['config_path' => 'billing.gateways.paypal.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'PAYPAL_MODE' => ['config_path' => 'billing.gateways.paypal.mode', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'], 'rules' => 'required|in:sandbox,live', 'label' => 'Mode'],
                'PAYPAL_CLIENT_ID' => ['config_path' => 'billing.gateways.paypal.client_id', 'type' => 'string', 'rules' => 'nullable|string|max:255', 'label' => 'Client ID', 'help' => 'OAuth2 client id from Apps & Credentials.'],
                'PAYPAL_CLIENT_SECRET' => ['config_path' => 'billing.gateways.paypal.client_secret', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Client secret'],
                'PAYPAL_WEBHOOK_ID' => ['config_path' => 'billing.gateways.paypal.webhook_id', 'type' => 'string', 'rules' => 'nullable|string|max:64', 'label' => 'Webhook ID', 'help' => 'ID returned when you create the webhook subscription. Required for signature verification.'],
            ],
        ],

        'paymob' => [
            'name' => 'Paymob',
            'description' => 'Cards, Vodafone Cash, Aman/Masary kiosk, BNPL across EG/AE/SA/OM/PK. HMAC-SHA512 on callback `hmac` query param.',
            'regions' => ['Egypt', 'UAE', 'Saudi Arabia', 'Oman', 'Pakistan'],
            'capabilities' => ['one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://developers.paymob.com/paymob-docs/getting-started/overview',

            'enabled' => env('PAYMOB_ENABLED', false),
            'region' => env('PAYMOB_REGION', 'eg'),
            'secret_key' => env('PAYMOB_SECRET_KEY'),
            'public_key' => env('PAYMOB_PUBLIC_KEY'),
            'api_key' => env('PAYMOB_API_KEY'),
            'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
            'integration_id_card' => env('PAYMOB_INTEGRATION_ID_CARD'),
            'integration_id_wallet' => env('PAYMOB_INTEGRATION_ID_WALLET'),
            'iframe_id' => env('PAYMOB_IFRAME_ID'),

            'fields' => [
                'PAYMOB_ENABLED' => ['config_path' => 'billing.gateways.paymob.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'PAYMOB_REGION' => ['config_path' => 'billing.gateways.paymob.region', 'type' => 'select', 'options' => ['eg' => 'Egypt (EGP)', 'ae' => 'UAE (AED)', 'sa' => 'Saudi Arabia (SAR)', 'om' => 'Oman (OMR)', 'pk' => 'Pakistan (PKR)'], 'rules' => 'required|in:eg,ae,sa,om,pk', 'label' => 'Region'],
                'PAYMOB_SECRET_KEY' => ['config_path' => 'billing.gateways.paymob.secret_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Secret key', 'help' => 'Unified Intentions API key. Preferred over the legacy 3-step Accept flow.'],
                'PAYMOB_PUBLIC_KEY' => ['config_path' => 'billing.gateways.paymob.public_key', 'type' => 'string', 'rules' => 'nullable|string|max:255', 'label' => 'Public key'],
                'PAYMOB_API_KEY' => ['config_path' => 'billing.gateways.paymob.api_key', 'type' => 'secret', 'rules' => 'nullable|string|max:512', 'label' => 'API key (legacy Accept)', 'help' => 'Required only for the legacy 3-step Accept flow.'],
                'PAYMOB_HMAC_SECRET' => ['config_path' => 'billing.gateways.paymob.hmac_secret', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'HMAC secret', 'help' => 'From dashboard → Account Info. Used to verify the `hmac` callback param.'],
                'PAYMOB_INTEGRATION_ID_CARD' => ['config_path' => 'billing.gateways.paymob.integration_id_card', 'type' => 'string', 'rules' => 'nullable|string|max:32', 'label' => 'Card integration ID'],
                'PAYMOB_INTEGRATION_ID_WALLET' => ['config_path' => 'billing.gateways.paymob.integration_id_wallet', 'type' => 'string', 'rules' => 'nullable|string|max:32', 'label' => 'Wallet integration ID', 'help' => 'Vodafone Cash / Etisalat / Masary etc. — one per payment method.'],
                'PAYMOB_IFRAME_ID' => ['config_path' => 'billing.gateways.paymob.iframe_id', 'type' => 'string', 'rules' => 'nullable|string|max:32', 'label' => 'Iframe ID', 'help' => 'For the hosted card iframe.'],
            ],
        ],

        'fawry' => [
            'name' => 'Fawry',
            'description' => 'Egypt. Kiosk reference codes + Express Checkout. SHA-256 signed requests; settles via 200k+ Fawry outlets.',
            'regions' => ['Egypt'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://developer.fawrystaging.com/docs-home',

            'enabled' => env('FAWRY_ENABLED', false),
            'environment' => env('FAWRY_ENVIRONMENT', 'staging'),
            'merchant_code' => env('FAWRY_MERCHANT_CODE'),
            'secure_key' => env('FAWRY_SECURE_KEY'),

            'fields' => [
                'FAWRY_ENABLED' => ['config_path' => 'billing.gateways.fawry.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'FAWRY_ENVIRONMENT' => ['config_path' => 'billing.gateways.fawry.environment', 'type' => 'select', 'options' => ['staging' => 'Staging', 'production' => 'Production'], 'rules' => 'required|in:staging,production', 'label' => 'Environment'],
                'FAWRY_MERCHANT_CODE' => ['config_path' => 'billing.gateways.fawry.merchant_code', 'type' => 'string', 'rules' => 'nullable|string|max:64', 'label' => 'Merchant code'],
                'FAWRY_SECURE_KEY' => ['config_path' => 'billing.gateways.fawry.secure_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Secure key', 'help' => 'Hash key for SHA-256 signing on requests + verifying notifications.'],
            ],
        ],

        'paytabs' => [
            'name' => 'PayTabs',
            'description' => 'Hosted PayPage + Managed Form. STC Pay (KSA), Mada, Apple/Samsung Pay. Region-specific base URLs.',
            'regions' => ['Saudi Arabia', 'UAE', 'Egypt', 'Oman', 'Jordan', 'Kuwait', 'Iraq', 'Qatar', 'Morocco', 'Global (UK)'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/',

            'enabled' => env('PAYTABS_ENABLED', false),
            'region' => env('PAYTABS_REGION', 'SAU'),
            'profile_id' => env('PAYTABS_PROFILE_ID'),
            'server_key' => env('PAYTABS_SERVER_KEY'),
            'client_key' => env('PAYTABS_CLIENT_KEY'),

            'fields' => [
                'PAYTABS_ENABLED' => ['config_path' => 'billing.gateways.paytabs.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'PAYTABS_REGION' => ['config_path' => 'billing.gateways.paytabs.region', 'type' => 'select', 'options' => ['SAU' => 'Saudi Arabia', 'ARE' => 'UAE', 'EGY' => 'Egypt', 'OMN' => 'Oman', 'JOR' => 'Jordan', 'KWT' => 'Kuwait', 'IRQ' => 'Iraq', 'QAT' => 'Qatar', 'MAR' => 'Morocco', 'GLOBAL' => 'Global / UK'], 'rules' => 'required|in:SAU,ARE,EGY,OMN,JOR,KWT,IRQ,QAT,MAR,GLOBAL', 'label' => 'Region', 'help' => 'Selects the regional base URL — same profile/server_key will NOT work across regions.'],
                'PAYTABS_PROFILE_ID' => ['config_path' => 'billing.gateways.paytabs.profile_id', 'type' => 'string', 'rules' => 'nullable|string|max:32', 'label' => 'Profile ID'],
                'PAYTABS_SERVER_KEY' => ['config_path' => 'billing.gateways.paytabs.server_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Server key', 'help' => 'Sent in Authorization header (raw, no Bearer prefix). Also used as the IPN HMAC key.'],
                'PAYTABS_CLIENT_KEY' => ['config_path' => 'billing.gateways.paytabs.client_key', 'type' => 'string', 'rules' => 'nullable|string|max:255', 'label' => 'Client key', 'help' => 'Only required for the Managed Form / client-side tokenization flow.'],
            ],
        ],

        'geidea' => [
            'name' => 'Geidea',
            'description' => 'Checkout/HPP + Direct API. Apple Pay, Mada, BNPL (ValU/Tabby/Tamara). Basic auth; signature uses API password as HMAC key.',
            'regions' => ['Egypt', 'Saudi Arabia', 'UAE'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://docs.geidea.net/docs/overview',

            'enabled' => env('GEIDEA_ENABLED', false),
            'environment' => env('GEIDEA_ENVIRONMENT', 'sandbox'),
            'public_key' => env('GEIDEA_PUBLIC_KEY'),
            'api_password' => env('GEIDEA_API_PASSWORD'),

            'fields' => [
                'GEIDEA_ENABLED' => ['config_path' => 'billing.gateways.geidea.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'GEIDEA_ENVIRONMENT' => ['config_path' => 'billing.gateways.geidea.environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'production' => 'Production'], 'rules' => 'required|in:sandbox,production', 'label' => 'Environment'],
                'GEIDEA_PUBLIC_KEY' => ['config_path' => 'billing.gateways.geidea.public_key', 'type' => 'string', 'rules' => 'nullable|string|max:255', 'label' => 'Public key', 'help' => 'Used as Basic-auth username.'],
                'GEIDEA_API_PASSWORD' => ['config_path' => 'billing.gateways.geidea.api_password', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'API password', 'help' => 'Basic-auth password AND the HMAC-SHA256 key used to verify response signatures.'],
            ],
        ],

        'aps' => [
            'name' => 'Amazon Payment Services',
            'description' => 'Payfort. Hosted redirect; Mada, Meeza, KNET, valU, Apple Pay. Recurring via stored token. Per-request signature with phrase + sorted concat.',
            'regions' => ['Saudi Arabia', 'UAE', 'Egypt', 'Jordan', 'Lebanon', 'Qatar', 'Kuwait', 'Oman', 'Bahrain'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://paymentservices.amazon.com/docs/getting-started',

            'enabled' => env('APS_ENABLED', false),
            'environment' => env('APS_ENVIRONMENT', 'sandbox'),
            'merchant_identifier' => env('APS_MERCHANT_IDENTIFIER'),
            'access_code' => env('APS_ACCESS_CODE'),
            'sha_request_phrase' => env('APS_SHA_REQUEST_PHRASE'),
            'sha_response_phrase' => env('APS_SHA_RESPONSE_PHRASE'),
            'sha_type' => env('APS_SHA_TYPE', 'sha256'),

            'fields' => [
                'APS_ENABLED' => ['config_path' => 'billing.gateways.aps.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'APS_ENVIRONMENT' => ['config_path' => 'billing.gateways.aps.environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'production' => 'Production'], 'rules' => 'required|in:sandbox,production', 'label' => 'Environment'],
                'APS_MERCHANT_IDENTIFIER' => ['config_path' => 'billing.gateways.aps.merchant_identifier', 'type' => 'string', 'rules' => 'nullable|string|max:64', 'label' => 'Merchant identifier'],
                'APS_ACCESS_CODE' => ['config_path' => 'billing.gateways.aps.access_code', 'type' => 'secret', 'rules' => 'nullable|string|max:64', 'label' => 'Access code'],
                'APS_SHA_REQUEST_PHRASE' => ['config_path' => 'billing.gateways.aps.sha_request_phrase', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'SHA request phrase'],
                'APS_SHA_RESPONSE_PHRASE' => ['config_path' => 'billing.gateways.aps.sha_response_phrase', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'SHA response phrase'],
                'APS_SHA_TYPE' => ['config_path' => 'billing.gateways.aps.sha_type', 'type' => 'select', 'options' => ['sha256' => 'SHA-256', 'sha512' => 'SHA-512'], 'rules' => 'required|in:sha256,sha512', 'label' => 'SHA algorithm'],
            ],
        ],

        'telr' => [
            'name' => 'Telr',
            'description' => 'Hosted Pages + REST API. Mada, Apple/Google/Samsung Pay, Tabby BNPL. IPN uses plain-SHA1 `*_check` hashes.',
            'regions' => ['UAE', 'Saudi Arabia', 'Jordan', 'Bahrain'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://docs.telr.com/reference/introduction',

            'enabled' => env('TELR_ENABLED', false),
            'test_mode' => env('TELR_TEST_MODE', '1'),
            'store_id' => env('TELR_STORE_ID'),
            'auth_key' => env('TELR_AUTH_KEY'),
            'ipn_secret' => env('TELR_IPN_SECRET'),

            'fields' => [
                'TELR_ENABLED' => ['config_path' => 'billing.gateways.telr.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'TELR_TEST_MODE' => ['config_path' => 'billing.gateways.telr.test_mode', 'type' => 'select', 'options' => ['0' => 'Live (0)', '1' => 'Test no-3DS (1)', '2' => 'Test 3DS (2)'], 'rules' => 'required|in:0,1,2', 'label' => 'Test mode (ivp_test)'],
                'TELR_STORE_ID' => ['config_path' => 'billing.gateways.telr.store_id', 'type' => 'string', 'rules' => 'nullable|string|max:32', 'label' => 'Store ID'],
                'TELR_AUTH_KEY' => ['config_path' => 'billing.gateways.telr.auth_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'API key', 'help' => 'Basic-auth password on REST; the `ivp_authkey` form field on legacy Hosted Pages.'],
                'TELR_IPN_SECRET' => ['config_path' => 'billing.gateways.telr.ipn_secret', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'IPN secret', 'help' => 'Secret used in `SHA1(secret:f1:f2:…)` for the *_check fields on the IPN POST body.'],
            ],
        ],

        'hyperpay' => [
            'name' => 'HyperPay',
            'description' => 'OPPWA-backed. COPYandPAY widget + server-to-server. Webhook body is AES-256-GCM encrypted.',
            'regions' => ['Saudi Arabia', 'UAE', 'Jordan', 'Egypt'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://hyperpay.docs.oppwa.com/',

            'enabled' => env('HYPERPAY_ENABLED', false),
            'environment' => env('HYPERPAY_ENVIRONMENT', 'test'),
            'access_token' => env('HYPERPAY_ACCESS_TOKEN'),
            'entity_id_card' => env('HYPERPAY_ENTITY_ID_CARD'),
            'entity_id_mada' => env('HYPERPAY_ENTITY_ID_MADA'),
            'entity_id_applepay' => env('HYPERPAY_ENTITY_ID_APPLEPAY'),
            'webhook_secret' => env('HYPERPAY_WEBHOOK_SECRET'),

            'fields' => [
                'HYPERPAY_ENABLED' => ['config_path' => 'billing.gateways.hyperpay.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'HYPERPAY_ENVIRONMENT' => ['config_path' => 'billing.gateways.hyperpay.environment', 'type' => 'select', 'options' => ['test' => 'Test', 'live' => 'Live'], 'rules' => 'required|in:test,live', 'label' => 'Environment'],
                'HYPERPAY_ACCESS_TOKEN' => ['config_path' => 'billing.gateways.hyperpay.access_token', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Access token', 'help' => 'Bearer token from BackOffice → Administration → Account data.'],
                'HYPERPAY_ENTITY_ID_CARD' => ['config_path' => 'billing.gateways.hyperpay.entity_id_card', 'type' => 'string', 'rules' => 'nullable|string|max:32|regex:/^[a-f0-9]{32}$/', 'label' => 'Entity ID — Cards'],
                'HYPERPAY_ENTITY_ID_MADA' => ['config_path' => 'billing.gateways.hyperpay.entity_id_mada', 'type' => 'string', 'rules' => 'nullable|string|max:32|regex:/^[a-f0-9]{32}$/', 'label' => 'Entity ID — Mada'],
                'HYPERPAY_ENTITY_ID_APPLEPAY' => ['config_path' => 'billing.gateways.hyperpay.entity_id_applepay', 'type' => 'string', 'rules' => 'nullable|string|max:32|regex:/^[a-f0-9]{32}$/', 'label' => 'Entity ID — Apple Pay'],
                'HYPERPAY_WEBHOOK_SECRET' => ['config_path' => 'billing.gateways.hyperpay.webhook_secret', 'type' => 'secret', 'rules' => 'nullable|string|max:128|regex:/^[a-fA-F0-9]+$/', 'label' => 'Webhook decryption key', 'help' => '64-char hex AES-256-GCM key from webhook setup.'],
            ],
        ],

        'myfatoorah' => [
            'name' => 'MyFatoorah',
            'description' => 'KNET (KW), Benefit (BH), Mada (SA), STC Pay, Apple/Google/Samsung Pay across 8 markets. V2 native subscriptions.',
            'regions' => ['Kuwait', 'Saudi Arabia', 'UAE', 'Bahrain', 'Oman', 'Qatar', 'Jordan', 'Egypt'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://docs.myfatoorah.com/docs/get-started',

            'enabled' => env('MYFATOORAH_ENABLED', false),
            'environment' => env('MYFATOORAH_ENVIRONMENT', 'test'),
            'country' => env('MYFATOORAH_COUNTRY', 'kuwait'),
            'api_token' => env('MYFATOORAH_API_TOKEN'),
            'webhook_secret' => env('MYFATOORAH_WEBHOOK_SECRET'),

            'fields' => [
                'MYFATOORAH_ENABLED' => ['config_path' => 'billing.gateways.myfatoorah.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'MYFATOORAH_ENVIRONMENT' => ['config_path' => 'billing.gateways.myfatoorah.environment', 'type' => 'select', 'options' => ['test' => 'Test', 'live' => 'Live'], 'rules' => 'required|in:test,live', 'label' => 'Environment'],
                'MYFATOORAH_COUNTRY' => ['config_path' => 'billing.gateways.myfatoorah.country', 'type' => 'select', 'options' => ['kuwait' => 'Kuwait (KWD)', 'saudi_arabia' => 'Saudi Arabia (SAR)', 'uae' => 'UAE (AED)', 'bahrain' => 'Bahrain (BHD)', 'oman' => 'Oman (OMR)', 'qatar' => 'Qatar (QAR)', 'jordan' => 'Jordan (JOD)', 'egypt' => 'Egypt (EGP)'], 'rules' => 'required|in:kuwait,saudi_arabia,uae,bahrain,oman,qatar,jordan,egypt', 'label' => 'Country', 'help' => 'KW/BH/JO/OM share one host; UAE/SA/QA/EG have dedicated hosts.'],
                'MYFATOORAH_API_TOKEN' => ['config_path' => 'billing.gateways.myfatoorah.api_token', 'type' => 'secret', 'rules' => 'nullable|string|max:2048', 'label' => 'API token', 'help' => 'JWT-like Bearer token from Integration Settings → API Key.'],
                'MYFATOORAH_WEBHOOK_SECRET' => ['config_path' => 'billing.gateways.myfatoorah.webhook_secret', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Webhook secret', 'help' => 'Used to verify the `MyFatoorah-Signature` HMAC-SHA256 base64 header.'],
            ],
        ],

        'hitpay' => [
            'name' => 'HitPay',
            'description' => 'FPX, DuitNow, GrabPay/Boost/TouchNGo (MY); PayNow, Atome (SG). Native Recurring Billing. Modern webhook = HMAC-SHA256 of raw JSON.',
            'regions' => ['Singapore', 'Malaysia', 'Australia', 'Hong Kong', 'Philippines'],
            'capabilities' => ['subscriptions', 'one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://docs.hitpayapp.com/introduction',

            'enabled' => env('HITPAY_ENABLED', false),
            'mode' => env('HITPAY_MODE', 'sandbox'),
            'api_key' => env('HITPAY_API_KEY'),
            'salt' => env('HITPAY_SALT'),
            'platform_key' => env('HITPAY_PLATFORM_KEY'),

            'fields' => [
                'HITPAY_ENABLED' => ['config_path' => 'billing.gateways.hitpay.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'HITPAY_MODE' => ['config_path' => 'billing.gateways.hitpay.mode', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'], 'rules' => 'required|in:sandbox,live', 'label' => 'Mode'],
                'HITPAY_API_KEY' => ['config_path' => 'billing.gateways.hitpay.api_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'API key', 'help' => 'Sent as the X-BUSINESS-API-KEY header.'],
                'HITPAY_SALT' => ['config_path' => 'billing.gateways.hitpay.salt', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Webhook salt', 'help' => 'HMAC-SHA256 key for the HITPAY-Signature header.'],
                'HITPAY_PLATFORM_KEY' => ['config_path' => 'billing.gateways.hitpay.platform_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Platform key', 'help' => 'Only for marketplace/platform accounts; sent as X-PLATFORM-KEY.'],
            ],
        ],

        'billplz' => [
            'name' => 'Billplz',
            'description' => 'Malaysia FPX (online banking) + cards. MYR only. Recurring is N-bills (not native subs). Callback HMAC-SHA512, redirect HMAC-SHA256.',
            'regions' => ['Malaysia'],
            'capabilities' => ['one_time', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://www.billplz.com/api#introduction',

            'enabled' => env('BILLPLZ_ENABLED', false),
            'sandbox' => env('BILLPLZ_SANDBOX', true),
            'api_key' => env('BILLPLZ_API_KEY'),
            'collection_id' => env('BILLPLZ_COLLECTION_ID'),
            'x_signature_key' => env('BILLPLZ_X_SIGNATURE_KEY'),

            'fields' => [
                'BILLPLZ_ENABLED' => ['config_path' => 'billing.gateways.billplz.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'BILLPLZ_SANDBOX' => ['config_path' => 'billing.gateways.billplz.sandbox', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Sandbox', 'help' => 'Sandbox is a separate account at billplz-sandbox.com with separate credentials.'],
                'BILLPLZ_API_KEY' => ['config_path' => 'billing.gateways.billplz.api_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'API secret key', 'help' => 'Used as the HTTP Basic auth username (password blank).'],
                'BILLPLZ_COLLECTION_ID' => ['config_path' => 'billing.gateways.billplz.collection_id', 'type' => 'string', 'rules' => 'nullable|string|max:32', 'label' => 'Collection ID', 'help' => 'Bills are created under this collection.'],
                'BILLPLZ_X_SIGNATURE_KEY' => ['config_path' => 'billing.gateways.billplz.x_signature_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'X-Signature key', 'help' => 'Shared key used for HMAC-SHA512 callback + HMAC-SHA256 redirect verification.'],
            ],
        ],

        'ipay88' => [
            'name' => 'iPay88',
            'description' => 'FPX (MY), QRIS (ID), OVO/DANA/LinkAja (ID), cards, e-wallets. HMAC-SHA512 mandated since 2025-01-31.',
            'regions' => ['Malaysia', 'Singapore', 'Indonesia', 'Philippines', 'Thailand', 'Vietnam'],
            'capabilities' => ['one_time', 'refunds', 'tokenization'],
            'driver_status' => 'shipped',
            'documentation_url' => 'https://www.ipay88.com/developer/',

            'enabled' => env('IPAY88_ENABLED', false),
            'environment' => env('IPAY88_ENVIRONMENT', 'sandbox'),
            'country' => env('IPAY88_COUNTRY', 'MY'),
            'merchant_code' => env('IPAY88_MERCHANT_CODE'),
            'merchant_key' => env('IPAY88_MERCHANT_KEY'),
            'signature_type' => env('IPAY88_SIGNATURE_TYPE', 'HMACSHA512'),

            'fields' => [
                'IPAY88_ENABLED' => ['config_path' => 'billing.gateways.ipay88.enabled', 'type' => 'bool', 'rules' => 'boolean', 'label' => 'Enabled'],
                'IPAY88_ENVIRONMENT' => ['config_path' => 'billing.gateways.ipay88.environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox', 'production' => 'Production'], 'rules' => 'required|in:sandbox,production', 'label' => 'Environment'],
                'IPAY88_COUNTRY' => ['config_path' => 'billing.gateways.ipay88.country', 'type' => 'select', 'options' => ['MY' => 'Malaysia', 'SG' => 'Singapore', 'ID' => 'Indonesia', 'PH' => 'Philippines', 'TH' => 'Thailand', 'VN' => 'Vietnam'], 'rules' => 'required|in:MY,SG,ID,PH,TH,VN', 'label' => 'Country', 'help' => 'Each country runs on its own iPay88 entity + endpoint host.'],
                'IPAY88_MERCHANT_CODE' => ['config_path' => 'billing.gateways.ipay88.merchant_code', 'type' => 'string', 'rules' => 'nullable|string|max:32', 'label' => 'Merchant code'],
                'IPAY88_MERCHANT_KEY' => ['config_path' => 'billing.gateways.ipay88.merchant_key', 'type' => 'secret', 'rules' => 'nullable|string|max:255', 'label' => 'Merchant key', 'help' => 'Shared secret used in the signature hash.'],
                'IPAY88_SIGNATURE_TYPE' => ['config_path' => 'billing.gateways.ipay88.signature_type', 'type' => 'select', 'options' => ['HMACSHA512' => 'HMAC-SHA512 (mandated 2025-01-31)', 'SHA256' => 'SHA-256 (deprecated)'], 'rules' => 'required|in:HMACSHA512,SHA256', 'label' => 'Signature type'],
            ],
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
            'trial_days' => 0,
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
            'price_cents' => 2000,
            'currency' => 'USD',
            'interval' => 'month',
            'trial_days' => 7,
            'features' => [
                'priority_support' => true,
                'advanced_analytics' => true,
                'api_access' => true,
                'webhooks' => true,
                'projects' => -1,        // unlimited
                'team_seats' => 20,
                'storage_gb' => 100,
            ],
            'cta' => 'Start 7-day trial',
            'highlighted' => true,
            'gateway_prices' => [
                'stripe' => env('STRIPE_PRICE_PRO'),
            ],
        ],

        'enterprise' => [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Full feature set with unlimited usage.',
            'price_cents' => 10000,
            'currency' => 'USD',
            'interval' => 'month',
            'trial_days' => 7,
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
