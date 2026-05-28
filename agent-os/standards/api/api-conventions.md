# API conventions (`/api/v1/*`)

Authoritative spec lives in `agent-os/product/api.md` §3 — this doc is
the contributor-facing summary. If the two disagree, the product spec
wins; update this file to match.

## File layout

```
app/Http/Controllers/API/V1/        # one controller per resource
   Concerns/                        # shared traits
      ApiController.php             # base — requireAbility / currentApiTenant
      HandlesIdempotency.php        # Idempotency-Key trait
      ScopesApiQuery.php            # ?filter + ?sort helpers
app/Http/Resources/                 # one JsonResource per model
app/Http/Middleware/
   ResolveApiTenant.php             # `api.tenant` alias
   ApiRateLimitHeaders.php          # `api.rate:<name>` alias
config/api-abilities.php            # ability catalogue + rate-limit knobs
routes/api.php                      # explicit routes, no auto-discovery
```

Every new endpoint is **explicit** in `routes/api.php`. We don't
auto-route from models — keeps surface auditable.

## Responding

```php
return UserResource::make($user)->response();
return UserResource::collection($paginator)->response(); // paginated envelope
return response()->json([], 204);                        // delete
return response()->json(['data' => ...], 201);           // create
```

Inline `response()->json([...])` is fine for one-off shapes (debug,
internal). Anything tied to a model goes through a JsonResource so the
envelope stays consistent.

## Abilities

Every action calls `$this->requireAbility($request, '<ability>')` at the
top. Ability strings follow `{resource}:{action}` — add new keys to
`config/api-abilities.php`. The catalogue is surfaced as a multi-select
on the token creation form, so don't ship an endpoint that depends on
an ability the form doesn't expose.

## Tenant scoping

Tenant-scoped routes mount under `/api/v1/tenants/{slug}/...` with the
`api.tenant` middleware:

```php
Route::middleware('api.tenant')->prefix('tenants/{slug}')->group(function () {
    Route::get('members', [MembersController::class, 'index'])->name('members.index');
});
```

Controllers reach the resolved tenant via `$this->currentApiTenant()` —
which 404s when the middleware didn't bind one. Membership +
404-on-unknown-slug is enforced by the middleware itself.

## Idempotency

Write endpoints that should be safe to retry wrap the handler:

```php
return $this->withIdempotency($request, function () use ($request) {
    $data = Validator::make(...)->validate();
    $row  = $this->service->doTheThing($data);

    return MyResource::make($row)->response()->setStatusCode(201);
});
```

If the caller sends `Idempotency-Key: <uuid>` and we've cached a
response in the last 24h, we replay it.

## Rate limiting

Routes opt into one of three buckets:

```php
Route::middleware(['throttle:api.read',  'api.rate:api.read'])->group(...);
Route::middleware(['throttle:api.write', 'api.rate:api.write'])->group(...);
Route::middleware(['throttle:api.auth',  'api.rate:api.auth'])->group(...);
```

`throttle:` enforces the bucket; `api.rate:` decorates the response
with the `X-RateLimit-*` headers so clients can self-pace.

## Services, not table writes

Cross-cutting writes go through canonical services (`TenantService`,
`BillingService`, `WebhookEndpointService`). API controllers **never**
write tables directly — the SPA controllers and the API controllers
share these services so behaviour stays consistent.

## Tests

One feature test class per controller, asserting:

1. `401` without token
2. `403` with the wrong ability
3. `2xx` with shape (`assertJsonStructure`)
4. Pagination meta when applicable
5. Rate-limit headers present on a successful call
6. Idempotency replay on write endpoints

Use `app('auth')->forgetGuards()` between requests when a single test
swaps tokens — Laravel caches the resolved user otherwise.

## Adding an endpoint — checklist

- [ ] Pick or add an ability key in `config/api-abilities.php`.
- [ ] Write the controller method, calling `requireAbility` first.
- [ ] Pick or write a JsonResource for the response.
- [ ] Map the route in `routes/api.php` under the right
      `throttle:` + `api.rate:` middleware pair.
- [ ] Add a feature test covering 401 / 403 / happy path.
- [ ] Update `.scribe/intro.md` if the ability catalogue grew.
- [ ] Add a `CHANGELOG-API.md` entry (additive).
