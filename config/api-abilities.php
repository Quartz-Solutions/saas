<?php

/*
|---------------------------------------------------------------------------
| API token abilities
|---------------------------------------------------------------------------
|
| Catalogue of fine-grained abilities that users can pick when minting a
| personal access token at /settings/api-tokens. Tokens may only call API
| endpoints that match one of their abilities (Sanctum gates via
| `$user->tokenCan($ability)`).
|
| Each entry:
|   key         — stored on the token (jsonb of scopes)
|   label       — human label shown in the UI multi-select
|   description — short explanatory copy
|   group       — visual grouping (read / write / admin)
|
*/

return [

    'abilities' => [
        [
            'key' => 'profile:read',
            'label' => 'Read profile',
            'description' => 'GET /me — read the authenticated user + token metadata.',
            'group' => 'read',
        ],
        [
            'key' => 'profile:write',
            'label' => 'Update profile',
            'description' => 'PATCH /me, request email change, revoke other sessions.',
            'group' => 'write',
        ],
        [
            'key' => 'tenants:read',
            'label' => 'Read tenants',
            'description' => 'List + detail for the tenants the user can see.',
            'group' => 'read',
        ],
        [
            'key' => 'tenants:write',
            'label' => 'Manage tenants',
            'description' => 'Create / update / delete tenants the user owns.',
            'group' => 'write',
        ],
        [
            'key' => 'members:read',
            'label' => 'Read members',
            'description' => 'List members + pending invitations inside a tenant.',
            'group' => 'read',
        ],
        [
            'key' => 'members:write',
            'label' => 'Manage members',
            'description' => 'Invite, change roles, remove members.',
            'group' => 'write',
        ],
        [
            'key' => 'users:read',
            'label' => 'Read tenant users (legacy alias)',
            'description' => 'Backwards-compat for the v0 ability key — same scope as members:read.',
            'group' => 'read',
        ],
        [
            'key' => 'users:write',
            'label' => 'Manage tenant users (legacy alias)',
            'description' => 'Backwards-compat for the v0 ability key — same scope as members:write.',
            'group' => 'write',
        ],
        [
            'key' => 'billing:read',
            'label' => 'Read billing',
            'description' => 'View plans, subscriptions, invoices, payments.',
            'group' => 'read',
        ],
        [
            'key' => 'billing:write',
            'label' => 'Manage billing',
            'description' => 'Change plan, cancel, reactivate.',
            'group' => 'write',
        ],
        [
            'key' => 'webhooks:read',
            'label' => 'Read webhooks',
            'description' => 'List outbound webhook endpoints + deliveries.',
            'group' => 'read',
        ],
        [
            'key' => 'webhooks:write',
            'label' => 'Manage webhooks',
            'description' => 'Create / update endpoints, rotate secret, replay deliveries.',
            'group' => 'write',
        ],
        [
            'key' => 'audit:read',
            'label' => 'Read audit log',
            'description' => 'List tenant audit log entries.',
            'group' => 'read',
        ],
        [
            'key' => 'notifications:read',
            'label' => 'Read notification preferences',
            'description' => 'Read the channel × event preferences matrix.',
            'group' => 'read',
        ],
        [
            'key' => 'notifications:write',
            'label' => 'Update notification preferences',
            'description' => 'Update channel × event preferences.',
            'group' => 'write',
        ],
        [
            'key' => '*',
            'label' => 'Full access',
            'description' => 'All abilities. Use sparingly — scripts only.',
            'group' => 'admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------
    | Rate limits (per token per minute)
    |--------------------------------------------------------------------
    |
    | Per api.md §3.10, the API uses category buckets — read endpoints get a
    | larger quota than writes, and the small auth bucket guards sensitive
    | endpoints (profile email change, session revocation).
    |
    | `rate_limit_per_minute` is kept so the v0 `throttle:api` named limiter
    | (referenced by older test fixtures that override it directly) continues
    | to resolve. New code should use the category names: api.read /
    | api.write / api.auth.
    */
    'rate_limits' => [
        'read' => env('API_RATE_LIMIT_READ_PER_MINUTE', 120),
        'write' => env('API_RATE_LIMIT_WRITE_PER_MINUTE', 30),
        'auth' => env('API_RATE_LIMIT_AUTH_PER_MINUTE', 6),
    ],

    'rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------
    | Window for caching successful write-endpoint responses keyed by
    | (token, URL, Idempotency-Key header). 24h matches Stripe's convention.
    */
    'idempotency_ttl_seconds' => env('API_IDEMPOTENCY_TTL_SECONDS', 86400),

    /*
    |--------------------------------------------------------------------
    | Outbound webhook events
    |--------------------------------------------------------------------
    |
    | Catalogue of events that a tenant can subscribe to from their
    | `/t/{slug}/settings/webhooks` page. Wired into emitters via the
    | OutboundWebhookDispatcher.
    */
    'webhook_events' => [
        'tenant.member.invited' => 'A new member was invited to the tenant.',
        'tenant.member.joined' => 'A user joined the tenant.',
        'subscription.updated' => 'Subscription status / plan changed.',
        'subscription.canceled' => 'Subscription was canceled.',
        'payment.succeeded' => 'A payment captured successfully.',
        'payment.failed' => 'A payment attempt failed.',
        'invoice.created' => 'A new invoice was issued.',
    ],

];
