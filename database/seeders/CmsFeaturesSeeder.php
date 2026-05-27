<?php

namespace Database\Seeders;

use App\Models\CmsFeature;
use Illuminate\Database\Seeder;

/**
 * Default marketing features — every shippable capability the boilerplate
 * exposes. The `feature_grid` block on the home page references these by
 * ID; the superadmin can pick the subset they want featured, or clear and
 * ship their own.
 *
 * Each `icon` is a Lucide icon name. The matching React component must be
 * present in the iconMap inside resources/js/components/cms/blocks/
 * feature-grid.tsx — that map is kept in sync alongside this seeder.
 */
class CmsFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // ---- Foundation (existing) ----
            [
                'slug' => 'multi-tenant',
                'title' => 'Multi-tenant from day one',
                'description' => 'Path-based tenancy with a resolver abstraction so you can ship subdomains and custom domains later without rewriting middleware.',
                'icon' => 'building',
            ],
            [
                'slug' => 'multi-gateway-billing',
                'title' => 'Multi-gateway billing',
                'description' => 'Stripe, PayPal, Paymob, HyperPay, HitPay — 14 gateways behind one driver registry. Add a new one with a single class.',
                'icon' => 'credit-card',
            ],
            [
                'slug' => 'admin-scope',
                'title' => 'Admin scope built in',
                'description' => 'Impersonation, audit log, webhook event replay, app settings — the back office every SaaS eventually needs.',
                'icon' => 'shield',
            ],
            [
                'slug' => 'typed-end-to-end',
                'title' => 'Typed end-to-end',
                'description' => 'Wayfinder gives you typed routes + form actions from Laravel to React. Never hand-write an href again.',
                'icon' => 'code',
            ],
            [
                'slug' => 'notifications',
                'title' => 'Notifications & email',
                'description' => 'Markdown email templates, in-app notification bell, per-user preferences matrix, optional Slack channel.',
                'icon' => 'mail',
            ],
            [
                'slug' => 'compliance',
                'title' => 'Compliance ready',
                'description' => 'GDPR export, login alerts, password breach check, audit log — fewer questionnaire surprises.',
                'icon' => 'lock',
            ],

            // ---- Content & marketing CMS ----
            [
                'slug' => 'block-cms',
                'title' => 'Block-based CMS',
                'description' => '18 typed blocks, drag-to-reorder editor, server-validated tree. Author landing pages, docs, and legal copy without code.',
                'icon' => 'layout-grid',
            ],
            [
                'slug' => 'media-library',
                'title' => 'Media library',
                'description' => 'Upload once, paste URL anywhere. Alt text, focal point, SHA-256 dedup, soft-delete with cleanup.',
                'icon' => 'image',
            ],
            [
                'slug' => 'globals',
                'title' => 'Site-wide globals',
                'description' => 'Brand, header / footer nav, announcement bar, analytics IDs, cookie banner — all editable from the admin.',
                'icon' => 'settings-2',
            ],
            [
                'slug' => 'blog',
                'title' => 'Built-in blog',
                'description' => 'Posts authored with the same block editor as pages. Categories, tags, archives, RSS, auto reading-minutes.',
                'icon' => 'book-open',
            ],
            [
                'slug' => 'forms-builder',
                'title' => 'Forms & inbox',
                'description' => 'Drag-build contact / lead-capture forms. Submissions inbox + CSV export + optional email and webhook fan-out.',
                'icon' => 'mail-plus',
            ],
            [
                'slug' => 'newsletter',
                'title' => 'Newsletter providers',
                'description' => 'Driver registry — Database (default), Mailchimp, Resend Audiences, ConvertKit. Switch with one env var.',
                'icon' => 'inbox',
            ],
            [
                'slug' => 'redirects',
                'title' => 'Redirects + 404 log',
                'description' => '301/302 manager runs before route matching. Every miss is logged with one-click "convert 404 to redirect".',
                'icon' => 'arrow-right-left',
            ],
            [
                'slug' => 'seo-toolkit',
                'title' => 'SEO toolkit',
                'description' => 'Per-page meta + JSON-LD, env-aware robots.txt, auto sitemap, site-wide title templates and OG defaults.',
                'icon' => 'search',
            ],
            [
                'slug' => 'i18n',
                'title' => 'Multi-locale content',
                'description' => 'en / ar / fr / es / de out of the box. Visitor locale resolver, fallback when untranslated, RTL-aware layouts.',
                'icon' => 'globe',
            ],
            [
                'slug' => 'versions-preview',
                'title' => 'Versions & preview',
                'description' => 'Every save snapshots to a version row. Restore any earlier state, share unpublished drafts via signed URLs.',
                'icon' => 'history',
            ],
            [
                'slug' => 'live-preview',
                'title' => 'Live preview',
                'description' => 'Side-by-side iframe inside the editor with mobile / tablet / desktop viewport switcher.',
                'icon' => 'monitor',
            ],
            [
                'slug' => 'cache-swr',
                'title' => 'Stale-while-revalidate cache',
                'description' => 'Cache::flexible per page + locale, event-driven invalidation, ready for Cloudflare surrogate-key purge.',
                'icon' => 'gauge',
            ],

            // ---- Auth & identity ----
            [
                'slug' => 'social-login',
                'title' => 'Social login',
                'description' => 'Google + GitHub via a Socialite-backed registry. Host-aware callbacks — works on every host with zero config.',
                'icon' => 'users',
            ],
            [
                'slug' => 'magic-link',
                'title' => 'Magic-link login',
                'description' => 'Passwordless sign-in via signed URL with 15-minute TTL. Optional alongside password + 2FA.',
                'icon' => 'sparkles',
            ],
            [
                'slug' => 'two-factor',
                'title' => '2FA + recovery codes',
                'description' => 'TOTP via Fortify, downloadable recovery codes, setup flow with QR code. Step-up for sensitive admin actions.',
                'icon' => 'shield-check',
            ],
            [
                'slug' => 'session-mgmt',
                'title' => 'Session management',
                'description' => 'Active sessions per device + IP + last-active timestamp. Revoke individually or sign out everywhere.',
                'icon' => 'monitor-smartphone',
            ],
            [
                'slug' => 'pwned-check',
                'title' => 'Breach check',
                'description' => 'HaveIBeenPwned k-anonymity check at registration and password change. Block leaked passwords by default.',
                'icon' => 'siren',
            ],

            // ---- API & integrations ----
            [
                'slug' => 'api-tokens',
                'title' => 'API + personal tokens',
                'description' => 'Sanctum tokens with per-token abilities, last-used timestamp, in-app revoke. Rate limited per token.',
                'icon' => 'key-round',
            ],
            [
                'slug' => 'outbound-webhooks',
                'title' => 'Outbound webhooks',
                'description' => 'Tenant-configured endpoints, HMAC-signed payloads, retry queue with exponential backoff, replay UI.',
                'icon' => 'webhook',
            ],
            [
                'slug' => 'feature-flags',
                'title' => 'Feature flags',
                'description' => 'Global on/off + per-tenant overrides. Roll out experimental features safely without redeploys.',
                'icon' => 'flag',
            ],

            // ---- Operations ----
            [
                'slug' => 'impersonation',
                'title' => 'Admin impersonation',
                'description' => 'Sign in as any tenant member to debug their view. Amber banner + every action recorded in impersonation_logs.',
                'icon' => 'user-cog',
            ],
            [
                'slug' => 'audit-log',
                'title' => 'Audit log',
                'description' => 'Auto-recorded model create / update / delete with diff. Filterable by user, tenant, model, action.',
                'icon' => 'clipboard-list',
            ],
            [
                'slug' => 'webhook-replay',
                'title' => 'Webhook event replay',
                'description' => 'Every inbound gateway event persisted. Replay individual events from admin after a bug fix.',
                'icon' => 'repeat',
            ],
            [
                'slug' => 'runtime-settings',
                'title' => 'Runtime settings',
                'description' => 'Edit env-style config (mail, OAuth, Sentry, S3, billing) from /admin/settings — no redeploy needed.',
                'icon' => 'sliders-horizontal',
            ],

            // ---- Billing extras ----
            [
                'slug' => 'dunning',
                'title' => 'Dunning + retries',
                'description' => 'Failed-payment retry queue with configurable backoff. Auto-cancel after exhausted attempts.',
                'icon' => 'refresh-ccw',
            ],
            [
                'slug' => 'multi-currency',
                'title' => 'Multi-currency',
                'description' => 'Currencies table + exchange rates, per-tenant default. Plans priceable in any supported currency.',
                'icon' => 'dollar-sign',
            ],
            [
                'slug' => 'coupons',
                'title' => 'Coupons & credits',
                'description' => 'Redemption tracking, per-plan / per-user limits, admin one-click credit + comp-months tools.',
                'icon' => 'tag',
            ],

            // ---- Compliance & ops ----
            [
                'slug' => 'gdpr-export',
                'title' => 'GDPR data export',
                'description' => 'One-click member data export as JSON / ZIP. Account deletion with a 30-day purge window.',
                'icon' => 'download',
            ],
            [
                'slug' => 'daily-backups',
                'title' => 'Daily DB backups',
                'description' => 'pg_dump → S3 every night, gzipped + dated. onOneServer-guarded so multi-instance schedulers stay safe.',
                'icon' => 'database',
            ],
            [
                'slug' => 'sentry-ready',
                'title' => 'Sentry integration',
                'description' => 'Auto error + perf tracking via the official SDK. Env-gated — registers cleanly when a DSN is set, no-ops without.',
                'icon' => 'bug',
            ],

            // ---- DX & polish ----
            [
                'slug' => 'dark-mode',
                'title' => 'Light & dark mode',
                'description' => 'Cookie-persisted appearance, system-preference default, every shadcn component themed.',
                'icon' => 'moon',
            ],
            [
                'slug' => 'onboarding-wizard',
                'title' => 'Onboarding wizard',
                'description' => 'Tenant create → name + logo → invite teammates → pick plan → first-action prompt. One coherent first run.',
                'icon' => 'sparkles',
            ],
            [
                'slug' => 'demo-seeder',
                'title' => 'One-command demo',
                'description' => 'php artisan db:seed lands Acme + diverse tenants with realistic subscriptions, invoices, login history.',
                'icon' => 'wand-2',
            ],
        ];

        foreach ($defaults as $i => $row) {
            CmsFeature::query()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_active' => true, 'sort_order' => $i]),
            );
        }
    }
}
