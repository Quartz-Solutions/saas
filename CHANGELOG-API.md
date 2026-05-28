# Quartz API changelog

Tracks every change to the public `/api/v1/*` surface. Additive entries
do **not** bump the version; breaking changes do (`/api/v2`, with the
`v1` surface staying online for at least 6 months under a `Sunset:`
header).

Conventions for entries:

- `Added` — new endpoints / fields / abilities. Non-breaking.
- `Changed` — behaviour change inside an existing endpoint. Note
  whether breaking.
- `Deprecated` — endpoint marked for removal. Include sunset date.
- `Removed` — endpoint removed at the previously announced sunset date.
- `Fixed` — bug fixes that don't change documented behaviour.
- `Security` — security-only changes.

## [1.0.0] — 2026-05-28 — v1 launch

The first production-ready cut. Every endpoint described in
`agent-os/product/api.md` §4 is shipped, tested, documented.

### Added

- Auth + identity:
  `GET /me`, `PATCH /me`, `POST /me/email-change`,
  `POST /me/sessions/revoke-all`,
  `GET /me/api-tokens`, `DELETE /me/api-tokens/{id}`.
- Tenants:
  `GET /tenants`, `GET /tenants/{slug}`,
  `POST /tenants`, `PATCH /tenants/{slug}`, `DELETE /tenants/{slug}`,
  `POST /tenants/{slug}/transfer-ownership`.
- Membership:
  `GET /tenants/{slug}/members`,
  `PATCH /tenants/{slug}/members/{userId}/role`,
  `DELETE /tenants/{slug}/members/{userId}`,
  `GET /tenants/{slug}/invitations`,
  `POST /tenants/{slug}/invitations`,
  `DELETE /tenants/{slug}/invitations/{id}`.
- Billing:
  `GET /plans`,
  `GET /tenants/{slug}/subscription`, `GET /tenants/{slug}/subscriptions`,
  `POST /tenants/{slug}/subscription/change-plan`,
  `POST /tenants/{slug}/subscription/cancel`,
  `POST /tenants/{slug}/subscription/reactivate`,
  `GET /tenants/{slug}/invoices`, `GET /tenants/{slug}/invoices/{id}`,
  `GET /tenants/{slug}/invoices/{id}/pdf`,
  `GET /tenants/{slug}/payments`, `GET /tenants/{slug}/payments/{id}`.
- Outbound webhooks:
  `GET /tenants/{slug}/webhooks`, `POST /tenants/{slug}/webhooks`,
  `GET /tenants/{slug}/webhooks/{id}`,
  `PATCH /tenants/{slug}/webhooks/{id}`,
  `DELETE /tenants/{slug}/webhooks/{id}`,
  `POST /tenants/{slug}/webhooks/{id}/rotate-secret`,
  `POST /tenants/{slug}/webhooks/{id}/test`,
  `GET /tenants/{slug}/webhooks/{id}/deliveries`,
  `POST /tenants/{slug}/webhooks/{id}/deliveries/{deliveryId}/retry`.
- Audit log:
  `GET /tenants/{slug}/audit-log`,
  `GET /tenants/{slug}/audit-log/{id}`.
- Notification preferences:
  `GET /notification-preferences`,
  `PATCH /notification-preferences`.

### Conventions established

- `data`-wrapped envelopes; pagination via `meta` + `links`.
- Snake_case JSON; ISO 8601 UTC timestamps; money in
  `*_cents` integers paired with `currency`.
- Per-token rate limit buckets: `read` (120/min), `write` (30/min),
  `auth` (6/min). Every response carries `X-RateLimit-*` headers.
- `Idempotency-Key` header on writes, 24h cache.
- Standard error envelope (`message`, optional `errors`, optional
  `trace_id` on 5xx).
