# Authentication

The Quartz API authenticates with **Sanctum personal access tokens**.

## Minting a token

Tokens are minted in the SPA at **/settings/api-tokens**. The
multi-select on that page shows the catalogue described in the
[introduction](./intro.md#abilities); the token only unlocks endpoints
that match one of its abilities (or carry the `*` wildcard).

The plain-text token is shown **once** at creation time — store it in
your secret manager. It cannot be re-read after the modal closes.

## Sending the token

Every request sends the token as a Bearer header:

```
Authorization: Bearer 1|abcdef...
Accept:        application/json
```

You can supplement the standard headers with:

- `Idempotency-Key: <uuid>` — see [idempotency](./intro.md#idempotency).
- `X-Request-Id: <ulid>` — copied back in the response for correlation
  (Quartz will mint one for you if you skip it).

## Token expiry

Tokens are immortal by default. You can opt into a TTL at creation
time (`expires_at`) via the SPA. The API does **not** allow tokens to
be minted programmatically — a misbehaving integration could otherwise
mint successor tokens before being noticed and locked out.

## Revoking a token

- The SPA's tokens page is the friendly path.
- The API exposes `DELETE /api/v1/me/api-tokens/{id}` for revoking
  **other** tokens; it refuses to revoke the calling token itself
  (`422`) so a slipped script can't lock you out.

When a token is deleted, every in-flight request that already passed
Sanctum's resolution finishes; new requests get **401**.

## Suspended users

A user the admin has suspended (`users.suspended_at`) cannot
authenticate any token. Calls return **401** with the standard
"Unauthenticated." message.
