<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * User-manual style documentation for the billing + settings admin surface.
 * Same pattern as CmsDocsSeeder: each page is template=docs, published,
 * uses block-based body so the renderer dogfoods its own block library.
 *
 * Slugs are prefixed `admin-` to distinguish them from CMS docs (which use
 * the `cms-` prefix). They appear at /docs/admin-{slug} and in the docs
 * sidebar under the "Admin" group (wired in config/cms.php).
 */
class BillingDocsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            CmsPage::query()->updateOrCreate(
                ['slug' => $slug, 'locale' => 'en'],
                [
                    'title' => $page['title'],
                    'meta_title' => $page['title'].' — Admin docs',
                    'meta_description' => $page['summary'],
                    'template' => CmsPage::TEMPLATE_DOCS,
                    'status' => CmsPage::STATUS_PUBLISHED,
                    'published_at' => now()->subDay(),
                    'no_index' => false,
                    'body_blocks' => $page['blocks'],
                    'body_html' => null,
                    'body_markdown' => null,
                ],
            );
        }
    }

    /**
     * @return array<string, array{title: string, summary: string, blocks: array<int, array<string, mixed>>}>
     */
    protected function pages(): array
    {
        return [
            'admin-plans' => $this->plansGuide(),
            'admin-subscriptions' => $this->subscriptionsGuide(),
            'admin-checkout' => $this->checkoutGuide(),
            'admin-gateways' => $this->gatewaysGuide(),
            'admin-settings' => $this->settingsGuide(),
        ];
    }

    /* -----------------------------------------------------------------
     * Page builders
     * -----------------------------------------------------------------*/

    protected function plansGuide(): array
    {
        return [
            'title' => 'Plans',
            'summary' => 'Author your pricing tiers — name, price in cents, billing cadence, trial, features, gateway price IDs.',
            'blocks' => [
                $this->richText('<p>The <strong>Plans</strong> admin is where you define every pricing tier your SaaS sells. Each plan lives in the <code>plans</code> table and drives the <a href="/pricing">/pricing</a> page, the get-started funnel, and the polymorphic checkout pipeline.</p><p>Manage them at <a href="/admin/plans"><code>/admin/plans</code></a>.</p>'),

                $this->richText('<h2>Creating a plan</h2><ol><li>Click <strong>New plan</strong>.</li><li>Set name + slug. The slug is part of every URL (<code>/get-started?plan=pro</code>) — pick it carefully and keep it short.</li><li>Set <strong>Price in cents</strong> and <strong>Currency</strong>. <code>2900</code> + <code>USD</code> = $29.00.</li><li>Pick a <strong>Billing cadence</strong>: day / week / month / year / one-time. <strong>Interval</strong> multiplies it — interval 3 + period month = "every 3 months".</li><li>Set <strong>Trial days</strong> if you offer one (typical: 14).</li><li>Pick the features the plan includes from the feature catalog.</li><li>Toggle <strong>Active</strong> and <strong>Public</strong>:<ul><li><em>Active</em> — plan is real and chargeable.</li><li><em>Public</em> — appears on <code>/pricing</code> and in the signup flow. Set false for legacy / grandfathered plans you don\'t want sold any more.</li></ul></li><li>Save.</li></ol>'),

                $this->divider(),

                $this->richText('<h2>Gateway price IDs</h2><p>For each enabled gateway (Stripe, PayPal, Paymob, …), enter the gateway-side product / price ID matching this plan. Stripe uses <code>price_xxx</code>; PayPal uses a billing plan ID; Paymob uses an integration ID, etc.</p><p>The checkout pipeline reads these IDs when starting a session — if you forget to set the Stripe price ID for the Pro plan, attempting Stripe checkout on Pro returns a 422 with a helpful message.</p>'),

                $this->codeBlock(<<<'PHP'
// In code (rare — admin UI is the canonical seam):
$plan = Plan::firstWhere('slug', 'pro');
$stripePrice = $plan->gateway_ids['stripe'] ?? null;
PHP, 'php', 'Reading gateway IDs'),

                $this->richText('<h2>Features</h2><p>Features are a flat catalog declared in <code>config/billing.php</code> under <code>features</code>. Each feature has a slug, name, and category. Pick the ones each plan includes via the multi-select; the public <code>/pricing</code> page lists them under each plan card.</p><p>Add new features at the config level — they show up in the admin picker on the next request.</p>'),

                $this->divider(),

                $this->richText('<h2>Sort order</h2><p>Lowest <code>sort_order</code> shows leftmost on <code>/pricing</code>. Conventional values: Free=10, Pro=20, Enterprise=30. Increment by 10 so you can slot new plans in without renumbering.</p>'),

                $this->richText('<h2>Archiving</h2><p>Don\'t delete plans — <strong>Archive</strong> them. Archived plans are soft-deleted; existing subscriptions on archived plans keep running, new sign-ups can\'t pick them. Restore from the archive filter if you change your mind.</p>'),

                $this->ctaBanner(
                    title: 'Set up your first plan',
                    body: 'A working Pro plan with a Stripe price ID is the fastest path to a live checkout.',
                    primary: ['Open Plans admin', '/admin/plans'],
                    secondary: ['Read about gateways', '/docs/admin-gateways'],
                ),
            ],
        ];
    }

    protected function subscriptionsGuide(): array
    {
        return [
            'title' => 'Subscriptions',
            'summary' => 'Inspect, filter, and act on tenant subscriptions — change plan, cancel, reactivate, apply credit, comp months, refund payments.',
            'blocks' => [
                $this->richText('<p>The <strong>Subscriptions</strong> admin gives you full visibility and control over every active customer relationship. Open <a href="/admin/subscriptions"><code>/admin/subscriptions</code></a>.</p>'),

                $this->richText('<h2>Statuses you\'ll see</h2><p>The boilerplate uses Stripe-compatible statuses everywhere — same vocabulary across every gateway driver:</p><ul><li><strong>trialing</strong> — inside the trial window, no charge yet.</li><li><strong>active</strong> — paying customer, current period in good standing.</li><li><strong>past_due</strong> — most recent renewal failed; dunning retries in progress.</li><li><strong>canceled</strong> — customer (or admin) terminated; ends_at stamped.</li><li><strong>paused</strong> — temporarily halted by customer/admin without canceling.</li><li><strong>incomplete</strong> / <strong>incomplete_expired</strong> — checkout never finished.</li><li><strong>unpaid</strong> — dunning exhausted, awaiting manual intervention.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>Filtering</h2><p>Use the filter row at the top of the table:</p><ul><li><strong>Status</strong> — drop down to filter by any of the statuses above.</li><li><strong>Plan</strong> — pick a plan to see only that tier\'s customers.</li><li><strong>Gateway</strong> — filter by Stripe / PayPal / Paymob / etc. Useful for gateway-specific incident triage.</li><li><strong>Currency</strong> — for multi-currency installs.</li><li><strong>Search</strong> — by tenant name, slug, or gateway subscription ID.</li></ul><p>Hit <strong>Export CSV</strong> for a flat dump including current MRR contribution.</p>'),

                $this->richText('<h2>Drill-down</h2><p>Click any row to open the subscription detail. You\'ll see:</p><ul><li>Header — plan, status, gateway, MRR contribution, trial countdown if any.</li><li>Period — current start / end + a renewal countdown.</li><li>Invoice timeline — every invoice issued, with status pills and PDF links.</li><li>Payment attempts — successes + failures with gateway error codes.</li><li>Webhook events — every gateway event that touched this subscription, with the replay button.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>Admin actions</h2><p>From the detail page, you can:</p><ul><li><strong>Change plan</strong> — moves the customer to a different tier. Choose proration (immediate vs. next renewal). Reflects to the gateway via the registered driver.</li><li><strong>Cancel</strong> — pick "at period end" (default — customer keeps access until the next renewal) or "immediately" (refund pro-rata if applicable).</li><li><strong>Reactivate</strong> — if the customer canceled with "at period end" and changed their mind, this clears the flag.</li><li><strong>Apply credit</strong> — add a fixed credit to the customer\'s next invoice (one-time apology / chargeback resolution / partial refund credit).</li><li><strong>Comp months</strong> — push the renewal date forward N months without charging. Equivalent to "give them free months".</li><li><strong>Refund payment</strong> — from the payment attempts list, refund full or partial. Hits the gateway driver\'s <code>refund()</code>.</li><li><strong>Record manual payment</strong> — for offline payments (wire transfer, kiosk reference like Fawry). Marks the invoice paid without going through the gateway.</li></ul>'),

                $this->richText('<h2>Reason codes</h2><p>Cancellations + credits + comps prompt for a <strong>reason code</strong> from the catalog in <code>config/billing-credit-reasons.php</code>. The catalog is closed-set (so reports stay clean) — extend the config when you discover a new pattern.</p>'),

                $this->richText('<h2>Impersonation</h2><p>To debug a customer\'s billing UI as <em>they</em> see it, click <strong>Tenants → impersonate</strong> from the tenant row. You\'ll land in their dashboard with an amber banner; click "Stop impersonating" to return to your admin session. The impersonation event is recorded in <code>impersonation_logs</code>.</p>'),

                $this->ctaBanner(
                    title: 'Investigate a past_due subscription',
                    body: 'Filter by status = past_due to see who needs attention. Most issues are gateway-side card declines.',
                    primary: ['Open subscriptions', '/admin/subscriptions'],
                ),
            ],
        ];
    }

    protected function checkoutGuide(): array
    {
        return [
            'title' => 'Checkout sessions',
            'summary' => 'Monitor the polymorphic checkout funnel — pending, awaiting-payment, completed, expired, canceled, failed. Force-cancel stuck sessions.',
            'blocks' => [
                $this->richText('<p>Every purchase — new subscription, plan change, one-time top-up — flows through a single <strong>CheckoutSession</strong>. The admin view at <a href="/admin/checkout-sessions"><code>/admin/checkout-sessions</code></a> lets you see in-flight and recently-finished sessions across every gateway.</p>'),

                $this->richText('<h2>Why one funnel?</h2><p>The boilerplate ships ~14 payment gateways with wildly different UX shapes — Stripe Checkout (hosted), Paymob (iframe), Fawry (kiosk reference code), Telr (form_post), etc. The CheckoutSession abstracts those into a small set of <code>result_kind</code> values, so the same React funnel renders all of them.</p>'),

                $this->divider(),

                $this->richText('<h2>The state machine</h2><ul><li><strong>pending</strong> — session created, user hasn\'t picked a gateway yet.</li><li><strong>awaiting_payment</strong> — gateway is engaged. <code>result_payload</code> contains whatever the user needs (redirect URL, iframe URL, kiosk reference code).</li><li><strong>completed</strong> — gateway confirmed payment. <code>subscription_id</code> and/or <code>invoice_id</code> are stamped. <code>completed_at</code> is set.</li><li><strong>failed</strong> — gateway returned a hard failure.</li><li><strong>canceled</strong> — user clicked away or admin force-cancelled. <code>cancel_reason</code> is stored.</li><li><strong>expired</strong> — older than <code>config(billing.checkout.timeout_minutes)</code> (default 30). The <code>ExpireStaleCheckouts</code> job runs every 5 minutes.</li></ul>'),

                $this->codeBlock(<<<'PHP'
// CheckoutSession terminal statuses (the session is "done"):
CheckoutSession::TERMINAL = [
    'completed',
    'failed',
    'canceled',
    'expired',
];
PHP, 'php', 'Reading from the model'),

                $this->divider(),

                $this->richText('<h2>Result kinds</h2><p>The <code>result_kind</code> column tells the React funnel how to present the next step:</p><ul><li><strong>redirect</strong> — top-level redirect to the gateway\'s hosted checkout (Stripe Checkout, PayPal, Telr).</li><li><strong>form_post</strong> — submit a hidden form to the gateway (APS, iPay88).</li><li><strong>iframe</strong> — embed the gateway\'s payment page (Paymob iframe).</li><li><strong>widget</strong> — drop in a gateway-provided JS widget (HyperPay).</li><li><strong>kiosk_ref</strong> — show the customer a reference code to pay offline (Fawry, GCash reference).</li></ul><p>The <code>result_payload</code> jsonb carries the data each kind needs (URLs, payment refs, signatures).</p>'),

                $this->richText('<h2>Admin actions</h2><ul><li><strong>View</strong> — full session payload, the gateway response, related subscription/invoice if any.</li><li><strong>Force cancel</strong> — flip a stuck session to <code>canceled</code> with a reason. Use this when the gateway clearly errored but didn\'t notify us (network blip, gateway outage).</li></ul>'),

                $this->divider(),

                $this->richText('<h2>What gets logged?</h2><p>Every webhook event from every gateway is recorded in <code>webhook_events</code> — admin scope shows them at <a href="/admin/webhooks"><code>/admin/webhooks</code></a>. From that page you can <strong>replay</strong> a single event (re-run the handler) when something broke and was fixed in code.</p>'),

                $this->ctaBanner(
                    title: 'Find an abandoned session',
                    body: 'Filter by status = pending + age > 30 minutes — those are sessions to follow up on.',
                    primary: ['Open checkout sessions', '/admin/checkout-sessions'],
                ),
            ],
        ];
    }

    protected function gatewaysGuide(): array
    {
        return [
            'title' => 'Payment gateways',
            'summary' => 'Configure credentials for every supported gateway. Stripe and PayPal are wired and tested; the rest ship with credential fields ready for live verification.',
            'blocks' => [
                $this->richText('<p>The boilerplate uses a <strong>driver registry</strong> for payment gateways. Each driver implements a small interface (<code>PaymentGateway</code> + optionally <code>SubscriptionGateway</code>) and is registered at boot in <code>AppServiceProvider</code> based on the per-gateway <code>enabled</code> config flag.</p><p>Configure credentials at <a href="/admin/gateways"><code>/admin/gateways</code></a>.</p>'),

                $this->richText('<h2>Gateways shipped</h2><p><strong>Global:</strong> Stripe, PayPal.</p><p><strong>Egypt:</strong> Paymob (cards / wallets / Aman), Fawry (kiosk ref), PayTabs, Geidea.</p><p><strong>GCC:</strong> Amazon Payment Services (Payfort), Telr, HyperPay, MyFatoorah.</p><p><strong>Malaysia:</strong> HitPay, Billplz, iPay88.</p><p>Each gateway entry shows a <em>driver status</em> tag: <strong>shipped</strong> (driver class exists, tested) or <strong>planned</strong> (catalog only, will throw on real ops).</p>'),

                $this->divider(),

                $this->richText('<h2>Configuring credentials</h2><ol><li>Pick the gateway from the list.</li><li>Toggle <strong>Enabled</strong> on.</li><li>Paste the publishable key (or equivalent) in the open field.</li><li>Paste the secret in the secret field — encrypted at rest, never echoed back into the form.</li><li>Paste the webhook signing secret. For Stripe, this comes from Dashboard → Developers → Webhooks → Reveal.</li><li>Save.</li></ol><p>The next request, the registry sees <code>enabled=true</code> and the credentials are available via <code>Config::get(\'billing.gateways.{id}.secret\')</code> — same path your driver code uses.</p>'),

                $this->codeBlock(<<<'BASH'
# Stripe test credentials look like this:
STRIPE_KEY=pk_test_<your-publishable-key>
STRIPE_SECRET=sk_test_<your-secret-key>
STRIPE_WEBHOOK_SECRET=whsec_<your-webhook-signing-secret>

# Once entered in admin, env values are no longer authoritative —
# the app_settings table wins via AppSettingsServiceProvider.
BASH, 'bash', 'Stripe credentials'),

                $this->divider(),

                $this->richText('<h2>Webhook URLs</h2><p>Each enabled gateway needs to send events to <code>https://{your-domain}/webhooks/{gateway-id}</code>. The exact URL is shown at the top of each gateway\'s edit page with a copy button — paste it into the gateway\'s dashboard.</p><p>Webhooks are CSRF-exempt (gateways sign their own POSTs) and go through <code>WebhookController</code>, which dispatches to <code>{Gateway}::handleWebhook()</code>.</p>'),

                $this->richText('<h2>Plan price IDs per gateway</h2><p>After enabling a gateway, switch over to <a href="/admin/plans">Plans</a> and fill in the gateway-side ID for each plan you sell on that gateway. Stripe uses <code>price_xxx</code>, PayPal uses billing plan IDs, etc. The checkout pipeline reads these to instantiate gateway-side sessions.</p>'),

                $this->divider(),

                $this->richText('<h2>Driver verification status</h2><p>Two gateways are <strong>live-verified end-to-end</strong> on real sandbox accounts: Stripe (95 webhook events, race-condition idempotency proven) and PayPal (sandbox subscription + reconcile-on-return). The remaining drivers are <em>code-shipped</em> but await sandbox traffic to validate documented assumptions (signature concatenation order, currency unit semantics, callback payload shapes). Each driver\'s per-driver tests file enumerates its open questions.</p>'),

                $this->richText('<h2>Adding a new gateway</h2><ol><li>Add the catalog entry to <code>config/billing.php</code> under <code>gateways</code> with <code>name</code>, <code>regions</code>, <code>capabilities</code>, and <code>fields</code>.</li><li>Implement the <code>PaymentGateway</code> interface in <code>app/Support/Billing/{Vendor}/{Vendor}Gateway.php</code>.</li><li>Register it conditionally in <code>AppServiceProvider::register</code> based on the gateway\'s <code>enabled</code> flag.</li><li>The admin picker / settings forms pick it up automatically.</li></ol>'),

                $this->ctaBanner(
                    title: 'Configure your first gateway',
                    body: 'Stripe test-mode is the fastest path to a working checkout. Test card 4242 4242 4242 4242 works in the funnel as soon as the keys are saved.',
                    primary: ['Open Gateways', '/admin/gateways'],
                    secondary: ['Read Plans guide', '/docs/admin-plans'],
                ),
            ],
        ];
    }

    protected function settingsGuide(): array
    {
        return [
            'title' => 'App settings',
            'summary' => 'Edit env-style runtime config from the admin UI — app identity, mail, OAuth, Sentry, Slack, S3, billing defaults, more.',
            'blocks' => [
                $this->richText('<p>The <strong>Settings</strong> admin lets you edit env-style runtime configuration without touching <code>.env</code> or redeploying. The catalog of editable keys is declared in <code>config/app-settings.php</code>; overrides land in the <code>app_settings</code> table and are applied via <code>AppSettingsServiceProvider</code> on every request.</p><p>Open at <a href="/admin/settings"><code>/admin/settings</code></a>.</p>'),

                $this->richText('<h2>Grouped tabs</h2><p>Settings are organized by tab:</p><ul><li><strong>App</strong> — name, URL, default locale, fallback locale.</li><li><strong>Mail</strong> — SMTP host / port / auth / scheme + from address + from name.</li><li><strong>OAuth</strong> — Google + GitHub client IDs / secrets / redirect URIs.</li><li><strong>Sentry</strong> — DSN, environment, traces sample rate, profiles sample rate.</li><li><strong>Slack</strong> — bot token, channel, log webhook.</li><li><strong>S3</strong> — key / secret / region / bucket for filesystem disk.</li><li><strong>Billing</strong> — default currency, default gateway, trial days, checkout timeout.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>How overrides resolve</h2><p>The catalog declares each key with a <code>config_path</code> (dotted, e.g. <code>mail.from.address</code>). When you save a value, <code>AppSettingsServiceProvider</code> reads every override on boot and calls <code>Config::set(\'mail.from.address\', \'…\')</code> — Laravel\'s config helper returns the new value from then on.</p><p>This means anything that reads <code>config(\'mail.from.address\')</code> picks up the admin override automatically. No code change needed.</p>'),

                $this->codeBlock(<<<'PHP'
// In your code, just read config normally:
$from = config('mail.from.address');
// → returns whatever the admin saved, or the .env default if unset.
PHP, 'php', 'Reading runtime config'),

                $this->richText('<h2>Secrets</h2><p>Fields marked <code>secret</code> (passwords, API secrets, signing keys) are <strong>encrypted at rest</strong> via Laravel\'s encrypter. The admin form shows them masked; saving without re-entering leaves the existing value in place. The decrypted value is only ever held in memory during a request — never logged.</p>'),

                $this->divider(),

                $this->richText('<h2>What\'s NOT editable here</h2><p>Some keys are <em>pre-boot</em> — they\'re read before the service provider runs, so runtime overrides can\'t take effect. They live in <code>.env</code> only:</p><ul><li><code>APP_KEY</code> — encryption key. Changing it invalidates every encrypted secret in <code>app_settings</code>, sessions, signed URLs.</li><li><code>DB_*</code> — database connection. Changing it would brick the request before any override applies.</li><li><code>REDIS_*</code> — same story.</li><li><code>CACHE_STORE</code>, <code>SESSION_DRIVER</code>, <code>QUEUE_CONNECTION</code> — driver-bind time.</li></ul><p>The admin UI intentionally doesn\'t list these to prevent footguns.</p>'),

                $this->richText('<h2>"Test" button</h2><p>Mail, OAuth, Sentry, Slack, and S3 groups all have a <strong>Test</strong> button next to Save. It exercises the actual integration — sends a test email, pushes a fake event to Sentry, posts a "hello" to Slack, lists S3 buckets, etc. Use it as a smoke test before publishing.</p>'),

                $this->divider(),

                $this->richText('<h2>Host-aware defaults</h2><p>OAuth callback URLs and similar self-referential fields default to <code>url(\'/auth/google/callback\')</code> at boot. That means a fresh install works without any manual config — visit the admin, Google + GitHub login already point at the right host. Override only when you have a reason (custom domain, multi-host setup).</p>'),

                $this->ctaBanner(
                    title: 'Smoke-test your mail config',
                    body: 'Set the SMTP host + from address, hit Test. You should get a sample email within seconds.',
                    primary: ['Open Settings', '/admin/settings'],
                ),
            ],
        ];
    }

    /* -----------------------------------------------------------------
     * Block builder helpers — same shape as CmsDocsSeeder.
     * -----------------------------------------------------------------*/

    /**
     * @return array<string, mixed>
     */
    protected function richText(string $html): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'rich_text',
            'attrs' => ['html' => $html],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function codeBlock(string $code, string $language = 'bash', string $filename = ''): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'code',
            'attrs' => [
                'language' => $language,
                'code' => $code,
                'filename' => $filename,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function divider(): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'divider',
            'attrs' => ['style' => 'line'],
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $primary  [label, url]
     * @param  array{0: string, 1: string}|null  $secondary  [label, url]
     * @return array<string, mixed>
     */
    protected function ctaBanner(string $title, string $body, array $primary, ?array $secondary = null): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'cta_banner',
            'attrs' => [
                'title' => $title,
                'body' => $body,
                'primary_cta_label' => $primary[0],
                'primary_cta_url' => $primary[1],
                'secondary_cta_label' => $secondary[0] ?? '',
                'secondary_cta_url' => $secondary[1] ?? '',
                'background_media_id' => null,
            ],
        ];
    }

    protected function ulid(): string
    {
        return strtoupper(Str::ulid()->toBase32());
    }
}
