# Product Roadmap

Boilerplate v1 covers **Phases 1–10**. Each phase is independently shippable — you can fork the branch after any phase that's "enough" for the SaaS idea you're starting. Estimated total: ~12–16 weeks of focused work (billing is the long pole because of the multi-gateway scope).

---

## Phase 1 — Auth & identity completion

Finish what Fortify scaffolds into a production auth surface.

- Email verification UI (signed link, resend cooldown).
- Password reset UI.
- **2FA (TOTP)** with recovery codes + setup flow.
- **Social login** via Socialite — Google + GitHub (interface designed for easy add: Apple, Microsoft, Facebook).
- **Magic-link login** (passwordless, signed URL with 15-min TTL).
- Account deletion (GDPR-compliant cascade).
- **Session management page** — list active sessions (device, IP, last active), revoke individual or all.
- Login history table.
- Profile page extension (avatar upload via existing `ImageProcessor`).

---

## Phase 2 — Multi-tenancy (path-based, resolver-ready)

The core SaaS primitive. Path-based now; resolver abstraction makes subdomain + custom-domain a Phase-11 add-on.

- `tenants` table: id, slug, name, logo_path, timezone, currency, locale, owner_id, soft-delete, settings (jsonb).
- `tenant_memberships` pivot: user_id, tenant_id, role, joined_at.
- `App\Support\Tenancy\TenantResolver` interface — `PathTenantResolver` is the default; `SubdomainTenantResolver` + `CustomDomainTenantResolver` are future implementations.
- `SetCurrentTenant` middleware — resolves tenant from URL, sets Spatie `setPermissionsTeamId($tenant->id)`, shares to Inertia.
- Routes structured as `/t/{tenantSlug}/...` for tenant scope; `/admin/...` for super-admin scope; `/account/...` for tenant-less personal scope.
- **Roles per tenant:** Owner, Admin, Member (config-driven so projects can extend).
- **Tenant invitations** — signed-token email link, optional pre-existing-user auto-attach, expires in 7 days.
- Tenant switcher dropdown in topbar (lists user's memberships).
- Tenant settings page — name, slug, logo, timezone, currency, locale, danger zone (delete tenant).
- **Owner transfer flow** — current owner → new owner via email confirmation.
- Soft-delete tenants with 30-day recovery window.

---

## Phase 3 — Billing (multi-gateway, polymorphic)

The headline feature. Mirrors the existing `HardwareRegistry` pattern from the original codebase.

### Phase 3.0 — Billing core

Gateway-agnostic primitives.

- `App\Support\Billing\PaymentGateway` interface — `charge / authorize / capture / refund / void / status / handleWebhook`.
- `App\Support\Billing\SubscriptionGateway` interface — `createSubscription / changePlan / cancel / resume / syncFromGateway`.
- `App\Support\Billing\GatewayRegistry` — register/resolve gateways at boot (mirror of `HardwareRegistry`).
- Tables: `plans`, `subscriptions`, `subscription_items`, `invoices`, `invoice_lines`, `payments`, `payment_attempts`, `gateway_customers`, `webhook_events`.
- `config/billing.php` — plans (Free, Pro, Enterprise — extendable), trial length, per-gateway enable flags, per-region default gateway.
- Plan picker UI on signup + upgrade page (config-driven).
- Trial period (14 days default, configurable).
- Invoices page + PDF download (`barryvdh/laravel-dompdf`).
- Cancel flow with reason capture.
- **Dunning** — failed-payment retry queue + customer notification.
- **Webhook router** — `/webhooks/{gateway}` dispatches to the right `PaymentGateway::handleWebhook()` implementation, every event persisted to `webhook_events` for replay.

### Phase 3.1 — Global gateways

- **Stripe** — subscriptions, customer portal redirect, Stripe Tax integration.
- **PayPal** — subscriptions via Subscriptions API, IPN webhook handler.

### Phase 3.2 — Egypt gateways

- **Paymob** (cards, wallets, Aman/Masary instalments) — Accept SDK.
- **Fawry** — pay-at-kiosk reference codes; one-time-only model, so subscriptions become "pre-generated invoices with kiosk reference".
- **PayTabs** — cards + STC Pay.
- **Geidea** — cards + Apple Pay.

### Phase 3.3 — GCC gateways (Saudi Arabia, UAE, Qatar, Kuwait)

- **Amazon Payment Services** (Payfort) — cards, Mada (Saudi).
- **PayTabs** — already shipped in 3.2, expose in GCC config too.
- **Telr** — cards + Mada.
- **HyperPay** — cards + Mada + Apple Pay.
- **MyFatoorah** — cards + KNET + Benefit Pay + KFAST.

### Phase 3.4 — Malaysia gateways

- **HitPay** — cards + FPX + e-wallets (GrabPay, Boost, TouchNGo).
- **Billplz** — FPX (online banking).
- **iPay88** — cards + FPX + e-wallets.

### Phase 3.5 — Multi-gateway UX

- **Per-tenant gateway picker** — tenant chooses preferred gateway at signup based on region or currency.
- **Multi-currency** — `currencies` + `exchange_rates` tables (pattern already exists in main from Phase 5.6–5.7); admin currency switcher.
- **Gateway availability matrix** — at checkout, filter visible gateways by tenant's currency + region.
- **Mandate/SCA** — Strong Customer Authentication where required (Stripe SCA, MENA SAMA mandates).
- **Tax/VAT** — VAT field on tenant, applied via Stripe Tax (global) or hand-rolled rule engine (regional).

---

## Phase 4 — Admin (internal staff scope)

Super Admin role bypasses tenant scoping. Separate sidebar at `/admin/...`.

- Tenants index — search, filter (plan, status, region), **impersonation** (login as tenant owner).
- Users index — search, filter (verified, 2FA-enabled), impersonation.
- Subscriptions index — active, trialing, past_due, cancelled; export to CSV.
- **Webhook event log + replay** — port from `main` branch (was Phase 11 on main).
- Audit log viewer — who did what when, filterable by user/tenant/model.
- Feature flag admin — per-tenant overrides.
- System metrics dashboard — counts (tenants, users, subscriptions), MRR, churn.

---

## Phase 5 — API & integrations

Make the SaaS programmable.

- **Sanctum** — SPA + personal access tokens.
- API tokens page (per-user) — create token, pick abilities/scopes, last-used timestamp, revoke.
- API rate limiting per token (Laravel rate limiter).
- `/api/v1/*` endpoints — REST for tenant resources, auto-discovered via convention.
- **Outbound webhooks** — tenant registers their own webhook URLs in settings. Events: `tenant.member.invited`, `subscription.updated`, `payment.succeeded`, etc. Signed payloads (HMAC-SHA256), retry queue with exponential backoff.
- API docs — Scribe (auto-generated from controllers + docblocks).

---

## Phase 6 — Notifications & email

- Markdown mail templates: welcome, email verification, password reset, magic link, 2FA recovery, invite, payment receipt, plan changed, trial ending, payment failed, login alert.
- Per-user **notification preferences** page — channel (email, in-app, optionally Slack/SMS) × event matrix.
- **In-app notification bell** in topbar — Inertia share, mark-as-read, dropdown list.
- Notification dispatcher worker (queued, exists in pattern from `LifecycleNotifier` on main).
- Slack channel adapter (optional, env-gated).

---

## Phase 7 — Marketing site & content

Public-facing pages that any new SaaS will need.

- Landing page — hero, features, social proof, pricing CTA, footer.
- **Pricing page** — driven from `config/billing.php` (single source of truth — change plans in one place).
- Public docs section — markdown CMS (port from `main` — agent-discovery + markdown content negotiation already designed).
- Legal pages template — Privacy, Terms, Cookies, Refund (placeholder content per project).
- Cookie consent banner.
- SEO meta + sitemap + robots.txt (patterns from `main` Phase 12).
- Optional blog (markdown-driven).

---

## Phase 8 — Compliance & security

- **GDPR data export** — endpoint returning a tenant member's full data as JSON/ZIP.
- Account deletion request → soft-delete + 30-day purge job.
- **Login alerts** — email on new device/IP login.
- **Password breach check** — HaveIBeenPwned Pwned-Passwords k-anonymity API at registration + password change.
- Rate limiting on `/login`, `/register`, `/forgot-password`, `/2fa` (Laravel rate limiter).
- **Audit log** — model observers auto-record create/update/delete with diff to `audit_logs` table.
- Encrypted PII at rest (Laravel encrypted casts for `email`, `phone`, etc. — opt-in per project).
- Reasonable defaults: HTTPS-only cookies in prod, `SameSite=Lax`, CSRF on all POST routes.

---

## Phase 9 — DX & polish

- **Demo seeder** — one tenant, three users (owner/admin/member), sample data per role.
- Light/dark mode toggle (`HandleAppearance` middleware already exists — verify + extend).
- **Command palette (cmd+k)** — navigate to any tenant page, switch tenant, jump to a model.
- Empty states + skeleton loaders for every `DataTable`.
- Toast notifications (sonner already in stack).
- **Onboarding wizard** for new tenants — create tenant → name + logo → invite teammates → pick plan → first action prompt.
- Internationalization — `lang/{en,ar,ms}/` keys for all UI strings, per-user locale preference.
- **RTL support** verified for Arabic.

---

## Phase 10 — Observability & ops

- Sentry adapter (env-gated, only loaded if `SENTRY_DSN` present).
- Health endpoint (`/up` — Laravel default, already there).
- DB backup script — `pg_dump` → S3 (configurable bucket), daily cron via scheduler.
- Application metrics — basic Prometheus exporter (optional).
- Deploy docs — README sections for Fly.io, Railway, DigitalOcean App Platform, self-hosted Docker.

---

## Post-v1 (future, after first SaaS forks the boilerplate)

- **Subdomain tenancy** — implement `SubdomainTenantResolver`, wildcard DNS + cert docs.
- **Custom domains** — `CustomDomainTenantResolver`, Let's Encrypt automation, DNS verification flow.
- **Mobile SDK starters** — React Native + Flutter starter consuming the API.
- **Enterprise add-ons** — SSO (SAML, OIDC), SCIM provisioning, fine-grained RBAC.
- **Affiliate / referral program** — tracking, payouts via the same gateway registry.
- **Whitelabel** — per-tenant branding (CSS variables + logo).

---

## Implementation order — pragmatic note

Phase 1 → 2 → 3.0 → 3.1 (Stripe + PayPal) → 4 → 6 → 9 gives a working SaaS forkable foundation in ~6 weeks. Phases 3.2–3.4 (regional gateways) can be added as the first SaaS project actually needs them — Paymob/HyperPay/HitPay each take ~3–5 days once 3.0 is built. Phases 5, 7, 8, 10 ship in parallel after the core.
