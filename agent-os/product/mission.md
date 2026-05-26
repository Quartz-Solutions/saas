# Product Mission

## Problem

Building a SaaS product from scratch wastes weeks on the same primitives every time: auth, multi-tenancy, billing, admin, notifications, marketing site, audit/compliance. For markets in **MENA, the GCC, and Southeast Asia**, the problem is harder — no off-the-shelf Laravel SaaS boilerplate supports the local payment gateways customers actually use (Paymob, Fawry, PayTabs, Geidea, HitPay, Billplz, MyFatoorah, etc.). Existing boilerplates assume Stripe-only, which fails the moment you sell to a customer in Cairo, Riyadh, Dubai, Doha, Kuwait City, or Kuala Lumpur.

## Target Users

- **Obaida (primary)** — building multiple SaaS products for own portfolio.
- **Client projects** — SaaS engagements where the boilerplate is the starting point. Clients are predominantly targeting customers in:
  - Egypt
  - Saudi Arabia, UAE, Qatar, Kuwait
  - Malaysia
  - Global (English-speaking, EU, US)
- Solo-dev / small-team delivery model — no enterprise architecture overhead.

## Solution

A Laravel 13 + Inertia 3 + React 19 + TypeScript boilerplate that ships with **every SaaS primitive already wired**, fork-per-project (`git clone boilerplate my-saas`). What makes it distinct:

1. **Polymorphic payment layer** — `PaymentGateway` interface with 13 production gateways: Paymob, Fawry, PayTabs, Geidea (Egypt) · Amazon Payment Services, Telr, HyperPay, MyFatoorah (GCC) · HitPay, Billplz, iPay88 (Malaysia) · Stripe, PayPal (global). Each project enables only the gateways it needs via config.
2. **Tenancy with a resolver seam** — path-based (`/t/{slug}/`) from day 1, designed so the resolver can be swapped to subdomain or custom-domain later without touching every middleware.
3. **Production-grade DX** — shadcn/ui (new-york) component library already extended with `DataTable` + `LocalDataTable`, Wayfinder typed routes, Spatie Permission with teams scoping, Docker dev + prod stacks, `.env` as single source of truth, pinned dependencies.
4. **Internal-staff scope built in** — super-admin, tenant impersonation, webhook event log + replay, audit log viewer, feature flags — not bolted on later.
5. **MENA-aware out of the box** — multi-currency, RTL support, multi-locale, region-aware default tax/VAT.

The boilerplate is not a SaaS itself — it's a fork-and-extend template optimized for the specific markets the owner ships to.
