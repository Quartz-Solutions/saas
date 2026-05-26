# Tech Stack

The stack the boilerplate ships with. Pinned so every fork starts from the same versions.

## Framework & runtime

- **PHP** 8.4 (Alpine container)
- **Application framework** Laravel 13
- **Node** 20+ (provided by Alpine `nodejs` package)
- **Package managers** Composer 2, pnpm 10 (pinned in Dockerfile)

## Frontend

- **JavaScript framework** React 19 (with React Compiler enabled via `babel-plugin-react-compiler`)
- **Type system** TypeScript 5
- **SPA bridge** Inertia.js 3 (`@inertiajs/react` + `@inertiajs/vite`)
- **CSS framework** Tailwind CSS 4
- **UI components** shadcn/ui (new-york variant) — 30+ wrappers already shipped (Accordion, AlertDialog, Calendar, DatePicker, DateRangePicker, DataTable, LocalDataTable, etc.)
- **Build tool** Vite 8
- **Icons** lucide-react
- **Notifications** sonner
- **Forms & inputs** react-day-picker, input-otp
- **Typed routes** Laravel Wayfinder (`@laravel/vite-plugin-wayfinder`) — `--with-form` always
- **Date handling** date-fns 4

## Backend

- **HTTP** Laravel routing + Inertia controllers (no separate API layer for first-party app — Sanctum API is additive in Phase 5)
- **Auth** Laravel Fortify (registration, login, password reset, 2FA, email verification)
- **API auth** Laravel Sanctum (Phase 5)
- **Authorization** Spatie laravel-permission (`teams=true` → `team_id == tenant_id`)
- **Social login** Laravel Socialite (Phase 1)
- **Image processing** Intervention Image 3 via `App\Support\ImageProcessor` helper (never inject `ImageManager` directly)
- **PDF generation** barryvdh/laravel-dompdf (invoices, receipts)
- **Excel/CSV** maatwebsite/excel (data table CSV export, imports)
- **Queue** Laravel Queue on Redis
- **Scheduler** Laravel Scheduler (already wired in `routes/console.php` pattern)

## Database & storage

- **Primary DB** PostgreSQL 16 (Alpine)
- **ORM** Eloquent
- **Cache** Redis 7
- **Session store** Redis (configurable to database)
- **Queue store** Redis
- **Object storage** S3-compatible (config-driven; works with AWS S3, Cloudflare R2, DigitalOcean Spaces, MinIO)
- **Local file storage** Laravel `storage/app/public` (Docker named volume `storage_public`)

## Money & finance

- **Cents-everywhere** — all monetary values are integer cents end-to-end (`subtotal_cents`, `total_cents`, etc.). No floats for money.
- **Multi-currency** — `currencies` + `exchange_rates` tables (pattern from main).
- **FX rates** — daily auto-fetch via scheduler (Phase 3.5).

## Payment gateways (Phase 3 — polymorphic via `GatewayRegistry`)

All gateways implement `App\Support\Billing\PaymentGateway` (one-time) and optionally `SubscriptionGateway` (recurring). Each gateway is enabled/disabled via `config/billing.php` per project.

### Global
- **Stripe** (cards, Apple Pay, Google Pay, SCA, Stripe Tax)
- **PayPal** (Express checkout, subscriptions, IPN)

### Egypt
- **Paymob** (cards, ValU, Aman, Masary)
- **Fawry** (kiosk-pay, reference codes — one-time-only)
- **PayTabs** (cards, STC Pay)
- **Geidea** (cards, Apple Pay, Mada)

### GCC (Saudi Arabia, UAE, Qatar, Kuwait)
- **Amazon Payment Services** (Payfort) — cards, Mada
- **Telr** — cards, Mada
- **HyperPay** — cards, Mada, Apple Pay
- **MyFatoorah** — cards, KNET, Benefit Pay, KFAST
- **PayTabs** (shared with Egypt config)

### Malaysia
- **HitPay** — cards, FPX, GrabPay, Boost, TouchNGo
- **Billplz** — FPX (online banking)
- **iPay88** — cards, FPX, e-wallets

## Testing & quality

- **PHPUnit** 12 — `pos_test` Postgres test DB with `RefreshDatabase`, `SeedsAdminContext` trait for fixtures
- **Linting (PHP)** Laravel Pint
- **Linting (JS/TS)** ESLint 9 + `typescript-eslint` + `eslint-plugin-react` + `eslint-plugin-react-hooks` + `@stylistic/eslint-plugin`
- **Formatting** Prettier 3 + `prettier-plugin-tailwindcss`
- **Type check** `tsc --noEmit`

## DevOps & infrastructure

- **Containerization** Docker + docker-compose
  - **Dev stack** PHP-FPM + nginx + Vite + Postgres + Redis (one `app` container, three deps)
  - **Prod stack** app + queue + scheduler + db + redis (five services)
- **Config strategy** `.env` is the single source of truth (loaded into containers via `env_file:`); compose interpolates DB credentials from `.env` substitution
- **Web server** nginx (Alpine)
- **Process manager** supervisord (php-fpm + nginx)
- **Reverse proxy / TLS** project-level decision (Caddy, Traefik, or platform-managed)
- **CI** GitHub Actions (lint workflow, test workflow — already wired)
- **Deploy targets** — first-party support docs for Fly.io, Railway, DigitalOcean App Platform, self-hosted Docker (Phase 10)

## Internationalization

- **Localization** Laravel `lang/{en,ar,ms}/` keys for UI strings
- **Per-user locale** `users.locale` column + middleware (pattern from main: `HandleInertiaRequests::sharePermissions()`)
- **RTL** Tailwind RTL utilities (`dir="rtl"` on `<html>` driven by locale)

## Observability (Phase 10)

- **Error tracking** Sentry (env-gated — `SENTRY_DSN`)
- **Logs** stderr → container log driver (loki/CloudWatch/etc. per deploy)
- **Health** `/up` endpoint (Laravel default)
- **Metrics** Prometheus exporter (optional)

## Architecture conventions

- **Service-layer single seam** — every cross-cutting mutation has one canonical service (e.g. `App\Support\Billing\SubscriptionService`, `App\Support\Tenancy\TenantService`). Direct table writes are bugs.
- **Driver registries** — pluggable vendors (payment gateways, social providers, mail transports) registered in `AppServiceProvider::register()`. Pattern lifted from existing `HardwareRegistry`.
- **Form Requests** — three per resource (Store / Update / Destroy). Validation lives in `app/Http/Requests/{Module}/...`.
- **shadcn admin-CRUD pattern** — `DataTable<T>` + `MoreHorizontal` dropdown + `AlertDialog` toggle-destroy. Mandatory for new resources.
- **Money as integer cents** — never use floats.
- **Migrations are append-only** — once shipped to dev DB, never edit; add a new migration.

## Conventions for this project

- No `Co-Authored-By: Claude` trailer in commit messages.
- Inject `App\Support\ImageProcessor` for image work; never touch `Intervention\Image\...` directly in controllers.
- Use `App\Support\Billing\GatewayRegistry` to resolve gateways; never `app(StripeGateway::class)` directly.
- Use `App\Support\Tenancy\TenantResolver` to read current tenant; never `Auth::user()->currentTenant()` shortcuts.
