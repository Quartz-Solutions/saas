# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

SaaS boilerplate built on **Laravel 13, Inertia.js 3, React 19, TypeScript, Tailwind 4, shadcn/ui (new-york)**. Postgres 16, Redis 7 (cache/session/queue). The product vision and 10-phase roadmap live in `agent-os/product/{mission,roadmap,tech-stack}.md` — read those first before planning new features. The boilerplate is intended to be forked per SaaS project (`git clone boilerplate my-saas`).

## Docker is mandatory

The dev stack (`docker-compose.yml`) runs PHP-FPM + nginx + Postgres + Redis. Never run `composer`, `artisan`, `php`, `pnpm`, `npm`, or `psql` on the host — host-installed PHP/Node will collide with container `vendor/`/`node_modules/` and break the stack.

```bash
# Boot the stack
docker compose up -d

# Anything PHP/Composer/artisan
docker compose exec app php artisan ...
docker compose exec app composer ...

# JS tooling
docker compose exec app pnpm ...
```

### Vite dev server is started manually
Supervisord runs php-fpm + nginx only. After boot, start Vite once per container lifetime:
```bash
docker compose exec -d app pnpm dev
```
This creates `public/hot`, which `@vite()` reads to route assets via HMR (port 5173). Without it, Laravel will throw `ViteManifestNotFoundException`.

## Common commands

```bash
# Migrations + seed
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# Wayfinder typed routes (regenerate after adding/renaming controller routes)
docker compose exec app php artisan wayfinder:generate --with-form
# IMPORTANT: --with-form is required, otherwise Form action helpers
# (Controller.store.form()) disappear and existing CRUD pages break.
# The Vite plugin (formVariants: true) handles this on dev rebuilds.

# Tests — phpunit.xml uses in-memory SQLite; full suite is fast
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=UsersControllerTest

# Lint
docker compose exec app vendor/bin/pint
docker compose exec app pnpm lint
docker compose exec app pnpm types:check
```

## Production stack

`docker-compose.prod.yml` uses `.env.production` (NOT `.env`). Compose's `${...}` substitution requires the `--env-file` flag:
```bash
cp .env.production.example .env.production    # then edit APP_KEY, DB_PASSWORD, MAIL_*, DOMAIN
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

## Current state vs. roadmap

The boilerplate is at **v0** — the foundation. The 10-phase plan to reach v1 lives in `agent-os/product/roadmap.md`.

### Implemented
- **Laravel Fortify** — registration, login, password reset, email verification, 2FA (TOTP + recovery codes). Features list in `config/fortify.php`. 2FA setup UI components exist (`resources/js/components/two-factor-*.tsx`).
- **Users CRUD** with the shadcn admin-CRUD pattern (DataTable + dialogs + FormRequests).
- **Settings scope** — Profile, Security (password + 2FA), Appearance (light/dark), Preferences. Routes in `routes/settings.php`.
- **Shared component library** — `DataTable<T>` (server-driven, full filter/sort/pagination/CSV export) + `LocalDataTable<T>` (client-side) + ~18 shadcn-new-york wrappers. Live catalog at `/shared-components`.
- **`user_preferences` table** — per-user UI state persistence (table column visibility, filters, page size). API at `/settings/preferences/{page}`.
- **Wayfinder typed routes** with `formVariants: true` plugin.
- **Docker stacks** — dev and prod, both `.env`/`.env.production` as single source of truth.

### Planned (not yet built)
See `agent-os/product/roadmap.md` for the full plan. Headline items:
- Multi-tenancy (path-based `/t/{slug}/...` with a `TenantResolver` interface so subdomain + custom-domain can be added later without rewriting middleware)
- Polymorphic billing supporting Stripe, PayPal (global) + Paymob, Fawry, PayTabs, Geidea (Egypt) + Amazon Payment Services, Telr, HyperPay, MyFatoorah (GCC) + HitPay, Billplz, iPay88 (Malaysia). Pattern follows a `GatewayRegistry` driver-registry mirroring `App\Support\Hardware\HardwareRegistry` from prior project work.
- Admin scope with impersonation, audit log, webhook event replay
- Outbound webhooks + Sanctum API
- Markdown email templates + in-app notification bell
- GDPR export, login alerts, breach-check

## Architecture

### Frontend conventions
- **Layout dispatch** in `resources/js/app.tsx` by page-name prefix:
  - `welcome` → no layout
  - `auth/*` → `AuthLayout`
  - `settings/*` → `[AppLayout, SettingsLayout]`
  - default → `AppLayout`
  See `agent-os/standards/frontend/inertia-layouts.md` for adding new sections.
- **Inertia routes are typed via Wayfinder**. Use `import { index } from '@/routes/...'` and `Controller.store.form()` instead of string URLs.
- **Sidebar entries** in `resources/js/components/app-sidebar.tsx`; light/dark mode toggled via `HandleAppearance.php` middleware.
- **shadcn admin-CRUD pattern (mandatory for new resources)**: `DataTable<T>` + `MoreHorizontal` dropdown + `AlertDialog` toggle-destroy + three `FormRequest` classes (Store/Update/Destroy). Don't hand-roll tables.
- shadcn `SelectTrigger` defaults to `w-fit` — always add `className="w-full"` inside form rows.
- Radix `Switch` submits nothing when off — precede every Switch with `<input type="hidden" name="x" value="0">`.
- Never use `AlertDialogAction` / `AlertDialogCancel` as the submit/cancel of an Inertia `<Form>` — they swallow the submit event. Use plain `<Button>`.
- `TabsContent` inside an Inertia `<Form>` must use `forceMount` + `data-[state=inactive]:hidden`, otherwise inactive-panel fields submit empty.
- Replace native `alert/confirm/prompt` with shadcn `Dialog`/`AlertDialog`.

### Routes
- `routes/web.php` — minimal: `/`, `dashboard`, `shared-components`, `users` resource, requires `routes/settings.php`.
- `routes/settings.php` — `settings.*` route group (profile, security, password, appearance, preferences). Most routes also gated on `verified` middleware.
- API endpoints under `app/Http/Controllers/API/` (currently just `UserSearchController` powering async-select filters).

### Controllers folder layout
```
app/Http/Controllers/
  API/        → JSON endpoints consumed by the SPA
  Settings/   → profile, security, preferences
  Users/      → users CRUD
  {Module}/   → one folder per new feature
```
FormRequests mirror this at `app/Http/Requests/{Module}/{X}Request.php`.

### Tests
- Uses **in-memory SQLite** (`phpunit.xml` overrides `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- If a feature exercises Postgres-only syntax (`ilike`, `jsonb`, partial indexes), switch the test class to a real Postgres test DB via `@group` or per-class `DB_*` env overrides.
- `RefreshDatabase` trait auto-migrates each run.

### Key middleware
- `app/Http/Middleware/HandleInertiaRequests.php` — global Inertia share (`auth.user`, `flash.toast`).
- `app/Http/Middleware/HandleAppearance.php` — light/dark mode via cookie.

## Configuration

### `.env` is the single source of truth
- Dev stack reads `.env` via `env_file:` in `docker-compose.yml`. There is NO hardcoded `environment:` block on `app`.
- Prod stack reads `.env.production`; you MUST pass `--env-file .env.production` to docker compose commands (otherwise `${DB_*}` substitution defaults kick in).
- The Postgres container's `POSTGRES_*` vars are interpolated from the same file's `DB_*` keys — change the password in one place, both services pick it up after `docker compose up -d --force-recreate`.

### pnpm
- Pinned to `pnpm@10` in both Dockerfiles (not `@latest`) for build reproducibility.
- `.npmrc` sets `dangerously-allow-all-builds=true` because pnpm 10 hard-errors on transitive deps with postinstall hooks (e.g. `unrs-resolver`).

## Conventions

- **No `Co-Authored-By: Claude` trailer** in commit messages.
- **Add new migrations, never edit existing ones.** Once any environment has run a migration, schema changes need a new timestamped migration.
- **Money as integer cents** — when the billing phase lands, all monetary values must be `unsignedBigInteger('*_cents')` with a paired `currency` column. No floats for currency.
- **Service-layer single seam** — once you add Phase 2+ features (tenancy, billing), every cross-cutting mutation gets ONE canonical service. Direct table writes outside the service are bugs. Pattern documented in `agent-os/product/tech-stack.md`.
- **Driver registries** — pluggable vendors (payment gateways, social providers) will be registered in `AppServiceProvider::register()` via a `Registry` class (mirror of the `HardwareRegistry` pattern from prior project work). Use the registry to resolve drivers; never `app(StripeGateway::class)` directly.

## Standards reference

`agent-os/standards/` contains short conventions docs that govern code style. Most useful:
- `backend/controller-conventions.md` — thin controllers + FormRequest always
- `backend/migration-conventions.md` — money/FK/soft-delete rules
- `backend/model-conventions.md` — Fillable always, no repositories
- `backend/validation-traits.md` — reusable rule traits
- `frontend/data-tables.md` — DataTable vs LocalDataTable choice + filter types
- `frontend/inertia-layouts.md` — layout dispatch
- `frontend/page-components.md` — page entry-point structure
- `frontend/shared-components.md` — kebab-case + cn() + data-test
- `frontend/wayfinder-routes.md` — never hand-write hrefs
- `global/toast-flash.md` — Inertia flash → sonner toast pattern
