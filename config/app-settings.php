<?php

/*
|--------------------------------------------------------------------------
| App Settings Catalog (Super Admin → /admin/settings)
|--------------------------------------------------------------------------
|
| Single source of truth for every setting editable through the admin UI.
| `AppSettingsServiceProvider` reads cached values from the `app_settings`
| table and calls `Config::set($config_path, $value)` for each entry on
| every request — so changes take effect on the *next* request without a
| container restart.
|
| Per-field metadata:
|   env_name     — the .env key (display + import target)
|   config_path  — dotted Laravel config path the override writes to
|   type         — string | secret | email | url | int | bool | select
|   options      — for type=select (associative array value => label)
|   default      — fallback shown if the setting has never been saved
|   rules        — Laravel validation rules string
|   label        — UI label
|   help         — UI helper text (optional)
|
| Infra keys (DB_*, REDIS_*, APP_KEY, CACHE_STORE, SESSION_DRIVER) are NOT
| listed here on purpose — they're read pre-boot and overriding them at
| runtime would either be a no-op or brick the app. Edit those in .env.
|
*/

return [

    'groups' => [

        'app' => [
            'label' => 'Application',
            'description' => 'Branding + default locale shown to users.',
            'icon' => 'Globe',
            'fields' => [
                'APP_NAME' => [
                    'config_path' => 'app.name',
                    'type' => 'string',
                    'rules' => 'required|string|max:120',
                    'label' => 'Application name',
                    'help' => 'Appears in browser tab title, mail templates, and the topbar.',
                ],
                'APP_URL' => [
                    'config_path' => 'app.url',
                    'type' => 'url',
                    'rules' => 'required|url|max:255',
                    'label' => 'Application URL',
                    'help' => 'Used to build absolute links in emails. Changing this requires updating OAuth redirect URLs at each provider.',
                ],
                'APP_LOCALE' => [
                    'config_path' => 'app.locale',
                    'type' => 'select',
                    'options' => ['en' => 'English', 'ar' => 'العربية', 'ms' => 'Bahasa Melayu'],
                    'rules' => 'required|in:en,ar,ms',
                    'label' => 'Default locale',
                ],
                'APP_FALLBACK_LOCALE' => [
                    'config_path' => 'app.fallback_locale',
                    'type' => 'select',
                    'options' => ['en' => 'English', 'ar' => 'العربية', 'ms' => 'Bahasa Melayu'],
                    'rules' => 'required|in:en,ar,ms',
                    'label' => 'Fallback locale',
                    'help' => 'Used when a translation is missing in the user-selected locale.',
                ],
            ],
        ],

        'mail' => [
            'label' => 'Mail',
            'description' => 'Outbound SMTP transport. Test before saving in production.',
            'icon' => 'Mail',
            'fields' => [
                'MAIL_MAILER' => [
                    'config_path' => 'mail.default',
                    'type' => 'select',
                    'options' => [
                        'smtp' => 'SMTP',
                        'log' => 'Log (dev only)',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                        'resend' => 'Resend',
                        'sendmail' => 'Sendmail',
                    ],
                    'rules' => 'required|in:smtp,log,ses,postmark,resend,sendmail',
                    'label' => 'Mailer',
                ],
                'MAIL_SCHEME' => [
                    'config_path' => 'mail.mailers.smtp.scheme',
                    'type' => 'select',
                    'options' => ['' => 'None', 'smtp' => 'smtp (STARTTLS)', 'smtps' => 'smtps (TLS)'],
                    'rules' => 'nullable|in:smtp,smtps',
                    'label' => 'SMTP scheme',
                ],
                'MAIL_HOST' => [
                    'config_path' => 'mail.mailers.smtp.host',
                    'type' => 'string',
                    'rules' => 'required_if:MAIL_MAILER,smtp|nullable|string|max:255',
                    'label' => 'SMTP host',
                ],
                'MAIL_PORT' => [
                    'config_path' => 'mail.mailers.smtp.port',
                    'type' => 'int',
                    'default' => 587,
                    'rules' => 'required_if:MAIL_MAILER,smtp|nullable|integer|min:1|max:65535',
                    'label' => 'SMTP port',
                ],
                'MAIL_USERNAME' => [
                    'config_path' => 'mail.mailers.smtp.username',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'SMTP username',
                ],
                'MAIL_PASSWORD' => [
                    'config_path' => 'mail.mailers.smtp.password',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'SMTP password',
                ],
                'MAIL_FROM_ADDRESS' => [
                    'config_path' => 'mail.from.address',
                    'type' => 'email',
                    'rules' => 'required|email|max:255',
                    'label' => 'From address',
                ],
                'MAIL_FROM_NAME' => [
                    'config_path' => 'mail.from.name',
                    'type' => 'string',
                    'rules' => 'required|string|max:120',
                    'label' => 'From name',
                ],
            ],
        ],

        'oauth' => [
            'label' => 'OAuth providers',
            'description' => 'Social login via Laravel Socialite. Empty client_id disables the button.',
            'icon' => 'KeyRound',
            'fields' => [
                'GOOGLE_CLIENT_ID' => [
                    'config_path' => 'services.google.client_id',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Google client ID',
                ],
                'GOOGLE_CLIENT_SECRET' => [
                    'config_path' => 'services.google.client_secret',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Google client secret',
                ],
                'GOOGLE_REDIRECT_URI' => [
                    'config_path' => 'services.google.redirect',
                    'type' => 'url',
                    'rules' => 'nullable|url|max:255',
                    'label' => 'Google redirect URI',
                    'help' => 'Must match the Authorized redirect URI configured at Google Cloud Console.',
                ],
                'GITHUB_CLIENT_ID' => [
                    'config_path' => 'services.github.client_id',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'GitHub client ID',
                ],
                'GITHUB_CLIENT_SECRET' => [
                    'config_path' => 'services.github.client_secret',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'GitHub client secret',
                ],
                'GITHUB_REDIRECT_URI' => [
                    'config_path' => 'services.github.redirect',
                    'type' => 'url',
                    'rules' => 'nullable|url|max:255',
                    'label' => 'GitHub redirect URI',
                    'help' => 'Must match the callback URL configured at GitHub Developer Settings.',
                ],
            ],
        ],

        'stripe' => [
            'label' => 'Stripe',
            'description' => 'Payment gateway credentials. Webhook secret is verified against the X-Stripe-Signature header on every event.',
            'icon' => 'CreditCard',
            'fields' => [
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
                    'help' => 'Starts with whsec_. From the Stripe Dashboard → Developers → Webhooks page.',
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

        'sentry' => [
            'label' => 'Sentry',
            'description' => 'Error tracking. Sentry is disabled when DSN is empty.',
            'icon' => 'Bug',
            'fields' => [
                'SENTRY_DSN' => [
                    'config_path' => 'sentry.dsn',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'DSN',
                    'help' => 'From Sentry → Project Settings → Client Keys.',
                ],
                'SENTRY_ENVIRONMENT' => [
                    'config_path' => 'sentry.environment',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:64',
                    'label' => 'Environment',
                    'help' => 'e.g. production, staging.',
                ],
                'SENTRY_TRACES_SAMPLE_RATE' => [
                    'config_path' => 'sentry.traces_sample_rate',
                    'type' => 'string',
                    'rules' => 'nullable|numeric|between:0,1',
                    'label' => 'Traces sample rate',
                    'help' => '0.0–1.0. Fraction of requests to trace. Leave empty to disable performance.',
                ],
                'SENTRY_PROFILES_SAMPLE_RATE' => [
                    'config_path' => 'sentry.profiles_sample_rate',
                    'type' => 'string',
                    'rules' => 'nullable|numeric|between:0,1',
                    'label' => 'Profiles sample rate',
                ],
                'SENTRY_SEND_DEFAULT_PII' => [
                    'config_path' => 'sentry.send_default_pii',
                    'type' => 'bool',
                    'rules' => 'boolean',
                    'label' => 'Send default PII',
                    'help' => 'Includes user IP + email on Sentry events. Off by default.',
                ],
            ],
        ],

        'slack' => [
            'label' => 'Slack',
            'description' => 'Optional Slack channel adapter for notifications + log alerts.',
            'icon' => 'Hash',
            'fields' => [
                'SLACK_BOT_USER_OAUTH_TOKEN' => [
                    'config_path' => 'services.slack.notifications.bot_user_oauth_token',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Bot user OAuth token',
                    'help' => 'Starts with xoxb-. From Slack App → OAuth & Permissions.',
                ],
                'SLACK_BOT_USER_DEFAULT_CHANNEL' => [
                    'config_path' => 'services.slack.notifications.channel',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:120',
                    'label' => 'Default channel',
                    'help' => 'e.g. #alerts',
                ],
                'LOG_SLACK_WEBHOOK_URL' => [
                    'config_path' => 'logging.channels.slack.url',
                    'type' => 'secret',
                    'rules' => 'nullable|url|max:255',
                    'label' => 'Log Slack webhook URL',
                    'help' => 'Incoming webhook URL for the "slack" log channel.',
                ],
            ],
        ],

        'aws' => [
            'label' => 'AWS S3',
            'description' => 'Credentials for S3-backed file storage and daily DB backups.',
            'icon' => 'Cloud',
            'fields' => [
                'AWS_ACCESS_KEY_ID' => [
                    'config_path' => 'filesystems.disks.s3.key',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Access key ID',
                ],
                'AWS_SECRET_ACCESS_KEY' => [
                    'config_path' => 'filesystems.disks.s3.secret',
                    'type' => 'secret',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Secret access key',
                ],
                'AWS_DEFAULT_REGION' => [
                    'config_path' => 'filesystems.disks.s3.region',
                    'type' => 'string',
                    'default' => 'us-east-1',
                    'rules' => 'nullable|string|max:32',
                    'label' => 'Default region',
                ],
                'AWS_BUCKET' => [
                    'config_path' => 'filesystems.disks.s3.bucket',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Bucket name',
                ],
            ],
        ],

    ],
];
