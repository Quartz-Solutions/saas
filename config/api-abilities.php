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
            'description' => 'Read the authenticated user profile + tenant memberships.',
            'group' => 'read',
        ],
        [
            'key' => 'tenants:read',
            'label' => 'Read tenants',
            'description' => 'List tenants the user belongs to.',
            'group' => 'read',
        ],
        [
            'key' => 'tenants:write',
            'label' => 'Manage tenants',
            'description' => 'Create / update tenants the user owns.',
            'group' => 'write',
        ],
        [
            'key' => 'users:read',
            'label' => 'Read tenant users',
            'description' => 'List users inside tenants the user is a member of.',
            'group' => 'read',
        ],
        [
            'key' => 'users:write',
            'label' => 'Manage tenant users',
            'description' => 'Invite, update, remove users inside tenants.',
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
            'description' => 'Create / update outbound webhook endpoints.',
            'group' => 'write',
        ],
        [
            'key' => 'billing:read',
            'label' => 'Read billing',
            'description' => 'View subscriptions, invoices, payments.',
            'group' => 'read',
        ],
        [
            'key' => '*',
            'label' => 'Full access',
            'description' => 'All abilities. Use sparingly.',
            'group' => 'admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------
    | Rate limit (per token per minute)
    |--------------------------------------------------------------------
    */
    'rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),

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
