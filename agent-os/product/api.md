# Public REST API — `/api/v1/*` Build Plan

> **Status:** v1 launch shipped — 2026-05-28. The full §4 endpoint catalog
> (40 routes spanning auth, tenants, members, billing, webhooks, audit,
> notification preferences, API tokens) is implemented behind the
> conventions in §3, covered by feature tests, and documented via Scribe.
> See [`CHANGELOG-API.md`](../../CHANGELOG-API.md) for the launch entry and
> [`agent-os/standards/api/api-conventions.md`](../standards/api/api-conventions.md)
> for the contributor-facing summary.

## 1. Problem statement — what's wrong today

### 1.1 The API is a demo, not a product

`routes/api.php` registers two endpoints:

```php
Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::get('tenants', TenantsController::class)->name('tenants.index');
    });
```

Every other surface (tenant members, invoices, subscriptions, webhooks,
audit, notification preferences, …) is reachable only through the Inertia
controllers, which return HTML, not JSON. An integration consumer can
authenticate with a Sanctum token but cannot do anything useful with it
beyond reading their email and tenant list.

### 1.2 Output shape is ad-hoc

Each `__invoke` builds its own JSON in-line:

```php
return response()->json([
    'data' => [
        'id' => $user->id,
        'name' => $user->name,
        ...
    ],
]);
```

There are no API Resources, no consistent envelope, no pagination convention,
no error envelope. Add a third endpoint and we'll re-invent the shape a third
time.

### 1.3 Per-token abilities are checked, but inconsistently

`tokenCan('profile:read')` and `tokenCan('tenants:read')` are called in the
two controllers — but there's no documented catalog of abilities, no "deny
everything not explicitly allowed" default, and no UI hint on the
token-creation form telling the user what each ability unlocks.

### 1.4 Rate limiting is one global bucket

`throttle:api` is registered in `AppServiceProvider::boot` as
`Limit::perMinute(60)->by(token-or-ip)`. Write-heavy endpoints (invite
member, create webhook) share the same bucket as cheap reads (`GET /me`).
A misbehaving integration polling `/me` can starve all other endpoints
for a tenant.

### 1.5 Scribe docs intro is the default

`.scribe/intro.md` is the boilerplate Scribe template. The auth section
(`.scribe/auth.md`) doesn't tell the developer where to mint a token, how
to scope abilities, or what happens on revocation.

## 2. Goals + non-goals

### Goals

- Mirror the SPA's tenant-scoped surface as REST endpoints so mobile apps,
  scripts, and integrations can do everything a user can do.
- Consistent response shapes across endpoints, driven by API Resources.
- Documented + enforced ability catalog so token scoping is meaningful.
- Per-endpoint rate limiting at sensible defaults.
- Auto-generated, hand-polished Scribe docs at `/api-docs`,
  `/api-docs.openapi`, `/api-docs.postman`.
- Feature tests per endpoint covering auth, ability, response shape, and
  edge cases.

### Non-goals (deferred / out of scope)

- **GraphQL** — REST is enough for v1; GraphQL is a fork-time choice.
- **WebSockets / Server-Sent Events** — push delivery uses outbound
  webhooks (already shipped). Real-time is project-specific.
- **Admin scope over the API** — `/admin/*` actions don't get
  `/api/v1/admin/*` mirrors. Super Admin work stays in the SPA.
- **OAuth2 authorization-code flow** — Sanctum personal access tokens
  cover the v1 use cases. OAuth2 is a Phase-11 enterprise add-on.
- **Webhook ingest** — `POST /api/v1/webhooks` is reserved for outbound
  endpoints the integration provisions; gateway webhook ingest stays at
  `/webhooks/{gateway}` outside `/api/v1`.

## 3. Conventions

### 3.1 URI shape

```
/api/v1/{resource}                    # collection (list / create)
/api/v1/{resource}/{id-or-slug}       # member (read / update / delete)
/api/v1/{resource}/{id}/{sub-action}  # custom action (e.g. ".../cancel")
```

Tenant-scoped resources live under `/api/v1/tenants/{slug}/...`. The slug
is preferred over the id in URIs (stable across renames thanks to the
slug-history table — see §6.4).

### 3.2 Response envelope

```jsonc
// Single resource
{
  "data": { ... }
}

// Collection (paginated)
{
  "data": [ ... ],
  "links": {
    "first": "https://app/api/v1/x?page=1",
    "last":  "https://app/api/v1/x?page=12",
    "prev":  null,
    "next":  "https://app/api/v1/x?page=2"
  },
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 287,
    "from": 1,
    "to": 25
  }
}
```

This is the default shape `JsonResource::collection(...)` emits when wrapped
around a `LengthAwarePaginator`. We don't fight it.

### 3.3 Error envelope

```jsonc
// 401 / 403
{ "message": "Unauthenticated." }

// 404
{ "message": "Resource [tenants/acme] not found." }

// 422
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field must be a valid email."]
  }
}

// 5xx
{
  "message": "Server error.",
  "trace_id": "01HVZK4P9XQ..." // Sentry event id when present
}
```

Backed by the standard Laravel exception handler with a JSON renderer
override in `bootstrap/app.php` for 5xx that adds `trace_id`.

### 3.4 Field naming

`snake_case` everywhere in JSON payloads — matches the database, matches
what Stripe / GitHub / Slack do. No `camelCase` in API responses (the SPA
uses Inertia which is separate).

### 3.5 Money + currency

Money fields are always returned as `*_cents` integers paired with
`currency` (ISO 4217). Same convention as the schema. Never floats.

### 3.6 Timestamps

ISO 8601 in UTC with explicit timezone offset:
```
"created_at": "2026-05-28T03:14:15+00:00"
```

Generated via `$model->created_at?->toIso8601String()`. Tenant-locale or
user-timezone formatting is the client's job.

### 3.7 Pagination

Cursor-based for high-cardinality activity streams (audit log, webhook
events) — `?cursor=eyJpZCI6Mn0`. Offset-based (`?page=2`) for everything
else. `per_page` cap at 100.

### 3.8 Filtering + sorting

Idiomatic Laravel patterns:

```
GET /api/v1/tenants/{slug}/invoices?status=paid&sort=-issued_at&per_page=50
```

- `?{field}=value` for equality filters
- `?{field}_between=YYYY-MM-DD,YYYY-MM-DD` for ranges
- `?sort=field` ascending, `?sort=-field` descending
- `?include=plan,subscription` for relations (controller decides what's
  loadable; unknown includes are silently dropped, never 422)

Implemented hand-rolled in the controllers (no `spatie/laravel-query-builder`
dependency for v1 — kept simple). A wrapper trait `ScopesApiQuery` lives
under `app/Http/Controllers/API/Concerns/`.

### 3.9 Ability scoping

Every endpoint declares its required ability in the docblock and enforces
it via:

```php
abort_unless($user->tokenCan($ability) || $user->tokenCan('*'), 403);
```

Abilities follow `{resource}:{action}` naming:

| Ability                       | Unlocks                                            |
|-------------------------------|----------------------------------------------------|
| `*`                           | Wildcard. Discouraged outside dev/scripts.         |
| `profile:read`                | `GET /me`                                          |
| `profile:write`               | `PATCH /me`                                        |
| `tenants:read`                | `GET /tenants`, `GET /tenants/{slug}`              |
| `tenants:write`               | `POST /tenants`, `PATCH /tenants/{slug}`           |
| `members:read`                | List members + invitations                         |
| `members:write`               | Invite, change roles, remove                       |
| `billing:read`                | Plans, subscriptions, invoices, payments (read)    |
| `billing:write`               | Change plan, cancel, manual payment                |
| `webhooks:read`               | List outbound endpoints + recent deliveries        |
| `webhooks:write`              | CRUD endpoints, rotate secret, replay delivery     |
| `audit:read`                  | List audit entries                                 |
| `notifications:read`          | Preferences matrix                                 |
| `notifications:write`         | Update preferences                                 |

The catalog lives in `config/sanctum.php` under a new `abilities` block
and is surfaced as a multi-select on the token-creation form.

### 3.10 Rate limiting

Per-token, per-endpoint-category:

| Category | Limit                | Endpoints                            |
|----------|----------------------|--------------------------------------|
| `read`   | 120 req/min/token    | All `GET` endpoints                  |
| `write`  | 30  req/min/token    | `POST` / `PATCH` / `DELETE`          |
| `auth`   | 6   req/min/token    | Token rotation, profile email change |

Configured in `AppServiceProvider::configureRateLimiting()` with named
limiters; routes opt into them via `throttle:api.read`, `throttle:api.write`,
`throttle:api.auth`.

Every response includes:

```
X-RateLimit-Limit:     120
X-RateLimit-Remaining: 117
Retry-After:           28   (only on 429)
```

### 3.11 Versioning

URL versioning: `/api/v1`, `/api/v2`, ... New versions live alongside,
not replace. Deprecation policy:

1. Mark the deprecated endpoint with `Sunset: <date>` and
   `Deprecation: true` response headers.
2. Sunset is **6 months minimum** from the deprecation announcement.
3. Breaking changes (response shape, removed fields, renamed fields,
   tighter validation) bump the major version.
4. Additive changes (new fields, new endpoints) are non-breaking and
   stay in the current version.

Changelog tracked in `CHANGELOG-API.md` at the repo root.

## 4. Resource map — endpoints to ship

The full target surface. Grouped by domain. Phasing in §5.

### 4.1 Auth + identity

| Method | Path                                | Ability         | Notes                                  |
|--------|-------------------------------------|-----------------|----------------------------------------|
| GET    | `/me`                               | `profile:read`  | ✅ shipped                             |
| PATCH  | `/me`                               | `profile:write` | Update name, locale, timezone, phone   |
| POST   | `/me/email-change`                  | `profile:write` | Triggers verification; doesn't apply directly |
| POST   | `/me/sessions/revoke-all`           | `profile:write` | Revoke every active session except current |

### 4.2 Tenants

| Method | Path                                | Ability          | Notes                                  |
|--------|-------------------------------------|------------------|----------------------------------------|
| GET    | `/tenants`                          | `tenants:read`   | ✅ shipped                             |
| GET    | `/tenants/{slug}`                   | `tenants:read`   | Tenant detail with `?include=`         |
| POST   | `/tenants`                          | `tenants:write`  | Create tenant; caller becomes Owner    |
| PATCH  | `/tenants/{slug}`                   | `tenants:write`  | Update name, locale, timezone, currency, … |
| DELETE | `/tenants/{slug}`                   | `tenants:write`  | Soft-delete (Owner only)               |

### 4.3 Tenant membership

| Method | Path                                          | Ability          | Notes                          |
|--------|-----------------------------------------------|------------------|--------------------------------|
| GET    | `/tenants/{slug}/members`                     | `members:read`   | Paginated member list          |
| GET    | `/tenants/{slug}/invitations`                 | `members:read`   | Pending invitations            |
| POST   | `/tenants/{slug}/invitations`                 | `members:write`  | Invite by email + role         |
| DELETE | `/tenants/{slug}/invitations/{id}`            | `members:write`  | Revoke pending invitation      |
| PATCH  | `/tenants/{slug}/members/{user_id}/role`      | `members:write`  | Change role (Owner locked)     |
| DELETE | `/tenants/{slug}/members/{user_id}`           | `members:write`  | Remove member                  |
| POST   | `/tenants/{slug}/transfer-ownership`          | `tenants:write`  | Initiate ownership transfer    |

### 4.4 Billing

| Method | Path                                                    | Ability         | Notes                          |
|--------|---------------------------------------------------------|-----------------|--------------------------------|
| GET    | `/plans`                                                | `billing:read`  | Plan catalog                   |
| GET    | `/tenants/{slug}/subscription`                          | `billing:read`  | Current non-terminal sub       |
| GET    | `/tenants/{slug}/subscriptions`                         | `billing:read`  | Paginated history              |
| POST   | `/tenants/{slug}/subscription/change-plan`              | `billing:write` | Body: `plan_slug`              |
| POST   | `/tenants/{slug}/subscription/cancel`                   | `billing:write` | Body: `at_period_end`, `reason`|
| POST   | `/tenants/{slug}/subscription/reactivate`               | `billing:write` |                                |
| GET    | `/tenants/{slug}/invoices`                              | `billing:read`  | Paginated, filter by `status`  |
| GET    | `/tenants/{slug}/invoices/{id}`                         | `billing:read`  |                                |
| GET    | `/tenants/{slug}/invoices/{id}/pdf`                     | `billing:read`  | Streams the PDF                |
| GET    | `/tenants/{slug}/payments`                              | `billing:read`  | Paginated                      |
| GET    | `/tenants/{slug}/payments/{id}`                         | `billing:read`  |                                |

### 4.5 Outbound webhooks

| Method | Path                                                          | Ability          | Notes                          |
|--------|---------------------------------------------------------------|------------------|--------------------------------|
| GET    | `/tenants/{slug}/webhooks`                                    | `webhooks:read`  | List configured endpoints      |
| POST   | `/tenants/{slug}/webhooks`                                    | `webhooks:write` | Body: `url`, `events[]`, `description` |
| GET    | `/tenants/{slug}/webhooks/{id}`                               | `webhooks:read`  | Endpoint detail                |
| PATCH  | `/tenants/{slug}/webhooks/{id}`                               | `webhooks:write` | Update url / events / active flag |
| DELETE | `/tenants/{slug}/webhooks/{id}`                               | `webhooks:write` |                                |
| POST   | `/tenants/{slug}/webhooks/{id}/rotate-secret`                 | `webhooks:write` | Returns new secret once        |
| POST   | `/tenants/{slug}/webhooks/{id}/test`                          | `webhooks:write` | Fire a test delivery           |
| GET    | `/tenants/{slug}/webhooks/{id}/deliveries`                    | `webhooks:read`  | Cursor-paginated delivery log  |
| POST   | `/tenants/{slug}/webhooks/{id}/deliveries/{delivery_id}/retry`| `webhooks:write` | Re-queue a failed delivery     |

### 4.6 Audit log

| Method | Path                                  | Ability       | Notes                                          |
|--------|---------------------------------------|---------------|------------------------------------------------|
| GET    | `/tenants/{slug}/audit-log`           | `audit:read`  | Cursor-paginated, filter by `action`, `user_id`|
| GET    | `/tenants/{slug}/audit-log/{id}`      | `audit:read`  | Single entry with old/new diff                 |

### 4.7 Notification preferences

| Method | Path                                  | Ability                | Notes                                          |
|--------|---------------------------------------|------------------------|------------------------------------------------|
| GET    | `/notification-preferences`           | `notifications:read`   | Full event × channel matrix for the user       |
| PATCH  | `/notification-preferences`           | `notifications:write`  | Bulk-update by (event, channel, enabled)       |

### 4.8 API token self-service

| Method | Path                                  | Ability         | Notes                                          |
|--------|---------------------------------------|-----------------|------------------------------------------------|
| GET    | `/me/api-tokens`                      | `profile:read`  | Caller's own tokens                            |
| DELETE | `/me/api-tokens/{id}`                 | `profile:write` | Revoke a token (cannot revoke the calling token via API — use the SPA) |

Self-rotation is intentionally not exposed via API — a misbehaving
integration could lock itself out.

## 5. Phasing

Five phases, sized for one focused dev. Each phase is independently
shippable and has its own feature-test bar.

### Phase A — Foundations + read-side coverage (~4–6 h)

Lays the conventions in code so all later phases inherit them.

- Create base classes:
  - `app/Http/Controllers/API/Concerns/ApiController.php` — exposes
    `requireAbility($ability)` + `respond($data, $status=200)` helpers.
  - `app/Http/Resources/{User,Tenant,Plan,Subscription,Invoice,Payment,
    Member,Invitation,WebhookEndpoint,WebhookDelivery,AuditEntry,
    NotificationPreference}Resource.php` — define the JSON shape once
    per model. The existing inline serializers in the Inertia
    controllers are the reference for what fields to emit.
- Register named rate limiters (`api.read`, `api.write`, `api.auth`) in
  `AppServiceProvider::configureRateLimiting`.
- Wire the JSON exception renderer in `bootstrap/app.php` so unhandled
  exceptions on `/api/*` routes return the §3.3 envelope.
- Update `config/sanctum.php` with the ability catalog (§3.9) and
  surface it on `/settings/api-tokens` as a multi-select instead of
  free-text.

Then ship the **read** endpoints from §4 — every `GET` listed. They're
mostly mechanical translations of the Inertia controllers' serializer
blocks into API Resources. ~10 endpoints.

**Done when:** Mobile app or curl can `GET` everything a SPA user can
see, with consistent envelopes + paginated lists.

### Phase B — Write-side coverage (~6–8 h)

Mutations. Every `POST` / `PATCH` / `DELETE` from §4.

- Reuse existing FormRequests where possible (e.g. `TenantUpdateRequest`)
  — they already encode validation rules. Where a FormRequest is
  SPA-tied (returns `RedirectResponse`), introduce an API-specific
  cousin or have the controller call into the canonical service.
- Reuse canonical services (`TenantAdminService`, `BillingService`,
  `TenantService::invite()`, etc.) — never duplicate mutation logic in
  the API layer.
- Idempotency: write endpoints accept an optional `Idempotency-Key`
  header. Cached responses (URL + key + auth) live in Redis for 24 h.
  Helper trait `HandlesIdempotency`.

**Done when:** Mobile app or integration can drive every meaningful
mutation a SPA user can drive.

### Phase C — Output discipline + docs (~3–4 h)

Tighten the polish.

- API Resources cover every endpoint; no inline arrays.
- `JsonResource::withoutWrapping()` left disabled — keep the `data`
  envelope.
- Standard pagination envelope verified on every list endpoint.
- Custom collections where needed (e.g. `InvoiceCollection` for stats
  meta).
- Rewrite `.scribe/intro.md` + `.scribe/auth.md` with Quartz copy:
  - Quick start (create a token at `/settings/api-tokens`, store
    `Authorization: Bearer ...`, base URL `https://your-app/api/v1`).
  - Ability catalog explanation.
  - Versioning + deprecation policy.
  - Rate limit categories + headers (`X-RateLimit-Limit`, `Remaining`,
    `Retry-After`).
  - Error envelope examples.
- Add `agent-os/standards/api/api-conventions.md` summarising §3 for
  contributors.
- `composer scribe` script alias = `php artisan scribe:generate`.
- CI step in `.github/workflows/ci.yml` that regenerates Scribe + fails
  if `.scribe/` diff is non-empty (forces contributors to commit doc
  changes).

### Phase D — Tests + quality (~3–5 h)

- One feature test class per controller, asserting:
  - 401 without token, 403 with wrong ability, 200 with right ability.
  - Response JSON shape (using `assertJsonStructure`).
  - Pagination meta when applicable.
  - Rate-limit headers present on a successful call.
  - Idempotency behaviour on write endpoints.
- Update the existing `MeControllerTest` and `TenantsControllerTest`
  (the API ones) to use the new shared shape.
- ~25–30 new tests total. The Inertia controllers' tests stay; nothing
  is replaced.

### Phase E — Polish (~2–3 h, optional)

Ship if there's a customer ask:

- **ETag / 304**: GET resources include `ETag: "<sha256-of-payload>"`;
  if `If-None-Match` matches, return 304.
- **Sparse fieldsets**: `?fields=id,name,email` projects.
- **Sort/filter normalisation**: refactor the hand-rolled
  `ScopesApiQuery` into a dedicated `ApiQuery` builder if there's
  appetite for it.
- **Webhook signing examples**: snippet in `.scribe/intro.md` showing
  how to verify HMAC on the consumer side.
- **OpenAPI publishing**: GitHub Action that pushes the spec to
  `https://your-app/api-docs.openapi` AND uploads it to SwaggerHub /
  Postman public workspace on release tags.

## 6. Cross-cutting design decisions

### 6.1 Inertia controllers stay as-is

We do not migrate the SPA to consume `/api/v1`. The SPA stays on Inertia
because:

- Inertia gives us deep-linked typed routes via Wayfinder.
- Inertia ships full payloads in one request — no waterfalls.
- The SPA isn't a third-party integration; sharing controllers with one
  would force API constraints onto every UI iteration.

The API is a **second presentation** of the same domain services. The
single seam — `TenantAdminService`, `BillingService`, etc. — guarantees
both presentations stay consistent.

### 6.2 No automatic resource discovery

We don't auto-route `Resource::all()` from models. Every endpoint is
explicit in `routes/api.php`. Cost: more typing. Benefit: no surprise
endpoints that leak fields the auto-mapper didn't know to hide.

### 6.3 Tenant resolution in API differs from SPA

In the SPA, `SetCurrentTenant` middleware resolves the tenant from the
URL path (`/t/{tenantSlug}/...`) and sets `app('currentTenant')`. The
API mirrors this but the middleware reads from
`/api/v1/tenants/{slug}/...` instead. We add an alias `api.tenant` that
does the same job. Membership check (`api.tenant.member`) follows
immediately.

### 6.4 Slug stability

Tenants today can rename their slug, which invalidates any cached URL.
For API stability we'll add (in Phase A):

- `tenants.slug_history` jsonb column tracking previous slugs.
- API routing accepts the current slug OR any previous slug — old slugs
  302 to the current canonical URL.

This is the same idea Stripe uses for renamed resources.

### 6.5 No `id` in JSON when there's a stable slug

When the model has a stable slug (`tenant.slug`, `plan.slug`), the JSON
resource emits both `id` and `slug` but documents the slug as the
preferred identifier. URIs use the slug.

### 6.6 Time-bounded list endpoints

Audit log, webhook deliveries, login history — anything that grows
unbounded — uses cursor pagination + a `?since=<iso-ts>` filter. A
client can't accidentally page through all 10 M audit entries.

## 7. Acceptance criteria

The API is "done" (for v1) when:

- [x] Every endpoint in §4 is implemented, tested, and documented.
- [x] Scribe-generated `/api-docs` page is current and the intro / auth
      sections are hand-written for Quartz, not Scribe's default.
- [x] OpenAPI 3 spec at `/api-docs.openapi` is generated and served
      (~35 KB, valid YAML, 30+ paths).
- [x] Postman collection at `/api-docs.postman` imports cleanly.
- [x] Each endpoint has 401 / 403 / happy-path coverage in
      `tests/Feature/API/*`.
- [x] All abilities listed in §3.9 are settable on the token-creation
      form at `/settings/api-tokens` (config-driven via
      `api-abilities.abilities`).
- [x] Per-category rate-limit headers are present on every response
      (`ApiRateLimitHeaders` middleware).
- [x] No endpoint reads or writes outside its canonical service seam
      (every mutation routes through `TenantService`, `BillingService`,
      `WebhookEndpointService`, `NotificationDispatcher`, `ApiTokenService`,
      or `SessionManager`).
- [x] `agent-os/standards/api/api-conventions.md` documents §3 for
      contributors.
- [x] `CHANGELOG-API.md` is initialised with the v1 launch entry.

## 8. Open decisions

1. **Public plan catalog**. `GET /plans` — token-required or public?
   - Pro public: pricing pages can fetch + cache server-side without
     baking creds into the marketing CMS.
   - Pro auth'd: nothing the public API exposes is a security risk, but
     less attack surface = better default.
   - **Lean: token-required.** Public listing reuses the existing
     Inertia `/pricing` route.

2. **PATCH semantics for `notification-preferences`**. The full matrix
   is ~80 (event × channel) keys. Options:
   - Send only changed pairs:
     `[{event:"x", channel:"email", enabled:false}]`
   - Send the full matrix every time.
   - **Lean: partial — only changed pairs.** Saves bandwidth + scales.

3. **Idempotency window**. 24 h vs 7 d? Stripe uses 24 h.
   - **Lean: 24 h** — matches industry norm and limits Redis cache size.

4. **API deprecation channel**. Where do we announce sunsets?
   - `CHANGELOG-API.md` is read by people who already know to look.
   - Email to "API consumers" — but we have no such list.
   - **Lean: dedicated `/admin/settings → API deprecations` setting
     that emails owners of tenants with an active token AND the
     `Sunset` HTTP header for one release cycle.** Defer the email job
     to a separate ticket.

5. **GraphQL parity**. Out of scope for v1 (§2). Re-evaluate after
   2 customer asks. Don't pre-build.

## 9. Out of scope (deliberately)

- Per-tenant API base URLs (e.g. `https://acme.our-app.com/api/v1`).
  Multi-tenant resolution stays path-based per §6.3.
- API key rotation reminder emails. (Useful but its own feature.)
- Two-way SDK generation (Python / Ruby / Go SDKs auto-built from
  OpenAPI). Manual hand-off to OpenAPI Generator is fine for v1.
- API usage dashboard for tenants (requests / day, error rate). Hooks
  exist in Sentry; building a customer-facing UI is its own product.

## 10. Estimated effort

| Phase | What                          | Effort  |
|-------|-------------------------------|---------|
| A     | Foundations + read endpoints  | 4–6 h   |
| B     | Write endpoints               | 6–8 h   |
| C     | Output discipline + docs      | 3–4 h   |
| D     | Tests                         | 3–5 h   |
| E     | Polish (optional)             | 2–3 h   |

**Total:** 18–26 h to fully complete; **~5 h** for an API that does
something useful (Phase A alone); **~12 h** for an API that's safe to
publish to friendlies (Phase A + B); **~22 h** for shippable-to-customers
grade (Phase A + B + C + D).

## 11. Suggested cut points

- **Phase A alone** → useful for read-only integrations (status pages,
  mobile dashboards). Ship as `0.1`.
- **A + B** → integrations can write. Ship as `0.5`.
- **A + B + C + D** → public launch. Ship as `1.0`.
- **+ E** → only when a customer asks for one of the polish items.
