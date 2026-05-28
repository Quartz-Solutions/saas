# Quartz REST API — v1

The Quartz API is a token-authenticated REST surface that mirrors what a
human can do in the SPA: manage tenants, members, billing, outbound
webhooks, audit history, and notification preferences.

> **Base URL:** `https://your-app/api/v1`
> **Auth:** Sanctum personal access tokens (Bearer)
> **Format:** JSON only — every successful body is wrapped in `{"data": ...}`

## Quick start

1. Mint a token at **/settings/api-tokens** in the SPA. Pick only the
   abilities the integration needs.
2. Store it server-side and send it on every request:

   ```bash
   curl -H "Authorization: Bearer ${QUARTZ_TOKEN}" \
        -H "Accept: application/json" \
        https://your-app/api/v1/me
   ```

3. Tenant-scoped endpoints live under `/api/v1/tenants/{slug}/…` and
   accept the **current** slug or any previous slug from the rename
   history.

## Conventions

- **Pagination** — list endpoints use offset pagination by default
  (`?page=2&per_page=50`). High-cardinality activity streams (audit log,
  webhook deliveries) accept `?cursor=…` for cursor pagination. The cap
  on `per_page` is 100.
- **Filtering + sorting** — equality filters use `?field=value`. Sort
  is `?sort=field` (asc) or `?sort=-field` (desc). Unknown fields are
  silently dropped (never 422).
- **Field naming** — snake_case in every JSON body — matches the database
  and what Stripe / GitHub / Slack do.
- **Money** — every monetary value is returned as an integer
  `*_cents` field paired with `currency` (ISO 4217). Never floats.
- **Timestamps** — ISO 8601 in UTC with offset (`2026-05-28T03:14:15+00:00`).
- **Slugs over ids** — when a stable slug exists (`tenant.slug`,
  `plan.slug`) it's the preferred identifier in URIs.

## Abilities

Tokens are gated by fine-grained abilities stored on the token row. A
call to an endpoint that requires an ability the token lacks returns
**403** with `{"message": "Token lacks <ability> ability."}`.

| Ability                 | Unlocks                                       |
|-------------------------|-----------------------------------------------|
| `profile:read`          | `GET /me`, `GET /me/api-tokens`               |
| `profile:write`         | `PATCH /me`, email change, session revoke     |
| `tenants:read`          | List + detail of tenants you can see          |
| `tenants:write`         | Create / update / delete / transfer ownership |
| `members:read`          | List members + pending invitations            |
| `members:write`         | Invite, change roles, remove                  |
| `billing:read`          | Plans, subscriptions, invoices, payments      |
| `billing:write`         | Change plan, cancel, reactivate               |
| `webhooks:read`         | List endpoints + deliveries                   |
| `webhooks:write`        | CRUD endpoints, rotate secret, replay         |
| `audit:read`            | List audit log entries                        |
| `notifications:read`    | Read the preferences matrix                   |
| `notifications:write`   | Update the preferences matrix                 |
| `*`                     | Wildcard — discouraged outside scripts        |

## Rate limiting

Limits are per-token and bucketed by endpoint category. The same bucket
is keyed by IP for unauthenticated calls.

| Category | Default per minute | Where it applies                  |
|----------|--------------------|-----------------------------------|
| `read`   | 120                | All `GET` endpoints               |
| `write`  | 30                 | `POST` / `PATCH` / `DELETE`       |
| `auth`   | 6                  | Email change, session revoke-all  |

Every response carries:

```
X-RateLimit-Limit:     120
X-RateLimit-Remaining: 117
Retry-After:           28   (only on 429)
```

Tune the defaults via `API_RATE_LIMIT_{READ,WRITE,AUTH}_PER_MINUTE` env
vars.

## Idempotency

Every mutating endpoint accepts an optional `Idempotency-Key` header.
The first request executes; any retry with the same key within 24h
returns the cached response untouched and the `Idempotent-Replay: true`
header. Use a fresh UUID per logical operation.

```bash
curl -H "Authorization: Bearer ${QUARTZ_TOKEN}" \
     -H "Idempotency-Key: 9b06bba8-…" \
     -X POST https://your-app/api/v1/tenants \
     -d '{"name":"Acme"}'
```

## Versioning + deprecation

URL versioning: `/api/v1`, `/api/v2`. New versions ship alongside the
old, never replacing in place.

- Deprecated endpoints carry `Sunset: <date>` and `Deprecation: true`
  headers for **6 months minimum** before removal.
- Breaking shape changes (rename, removal, tighter validation) bump
  the major version.
- Additive changes (new fields, new endpoints) stay in-version.

Change log: `CHANGELOG-API.md` at the repo root.

## Errors

All errors return JSON:

```jsonc
// 401
{ "message": "Unauthenticated." }

// 403
{ "message": "Token lacks tenants:write ability." }

// 404
{ "message": "Resource [tenants/acme] not found." }

// 422 — validation
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field must be a valid email."] }
}

// 5xx
{ "message": "Server error.", "trace_id": "01HVZK4P9XQ…" }
```

The `trace_id` on 5xx is the Sentry event id when Sentry is configured,
otherwise a random identifier. Quote it when filing a support ticket.
