# Tenants & Users Admin Redesign

Reference design: PropEasy "Tenant Details" — entity hero banner, left column of "facts" cards, right column of "related activity" mini-tables, query-string tabs, actions menu.

Goal: apply the same DNA to **Tenants** (existing, basic) and **Users** (new) in the Super Admin scope so the boilerplate ships with a polished, action-rich back office out of the box.

---

## 1. Gap analysis

### Tenants admin (current)
- `GET /admin/tenants` — DataTable index: name, slug, status, currency, owner, member count, created.
- `GET /admin/tenants/{tenant}` — basic show page: identity card + members list.
- `POST /admin/tenants/{tenant}/impersonate` — only action surfaced.
- Routes named: `admin.tenants.index`, `admin.tenants.show`, `admin.tenants.impersonate`, `admin.stop-impersonating`.

### Users admin (current)
- **Doesn't exist.** No `/admin/users` route.
- The only user-related endpoint is `/admin/users/search` for async-select filters.
- Users only surface as columns inside Tenants → Members.

### Gap vs. reference design
- No hero summary banner (the "$5000/month — Paid" equivalent).
- No left-column "facts" cards grouped semantically.
- No right-column mini-tables (invoices, payments, login history, webhooks, audit log).
- Only **one action** exposed for tenants (impersonate). Superadmin currently has **no UI** for: suspend, refund, force-cancel, reset 2FA, revoke sessions, soft-delete, GDPR export, change plan, etc.
- No Users surface at all.

---

## 2. Sitemap after redesign

```
/admin/tenants                       overhauled — card-rich list + saved-view chips
/admin/tenants/{tenant}              detailed show (reference design)
    ?tab=overview   (default)
    ?tab=billing
    ?tab=members
    ?tab=activity
    ?tab=danger

/admin/users                         NEW — DataTable index
/admin/users/{user}                  NEW — detailed show
    ?tab=overview   (default)
    ?tab=tenants
    ?tab=security
    ?tab=activity
    ?tab=danger
```

Tabs are query-string-backed (not separate routes) so deep links round-trip cleanly and the page state survives Inertia partial reloads.

---

## 3. Tenant detail page — wireframe

```
┌──────────────────────────────────────────────────────────────────────────────┐
│  ← Back   Tenants / Tenant Details                                            │
│                                                                                │
│  Tenant Details                                                                │
│                                                                                │
│  ┌───────────────────────┐  Subscription : $29/month  [Active] ┌──────────┐  │
│  │ Logo  Acme Corp        │  Next renewal in 12 days            │  Actions ▾│  │
│  │       slug acme        │                                      └──────────┘  │
│  │       owner Eagle      │  [Impersonate] [Change Plan] [Refund] [Suspend]   │
│  │       status • Active  │                                                    │
│  └───────────────────────┘                                                    │
│                                                                                │
│  [Overview] [Billing] [Members] [Activity] [Danger zone]                      │
│                                                                                │
│  ┌─────────────────────────────────┐  ┌──────────────────────────────────┐  │
│  │ Personal details                 │  │ Recent invoices         View all │  │
│  │ ─────────────────────────────   │  │ Date  | Amount  | Status         │  │
│  │ Name, slug, timezone, locale,    │  │                                  │  │
│  │ currency, created on, member     │  └──────────────────────────────────┘  │
│  │ count                            │                                         │
│  │                                  │  ┌──────────────────────────────────┐  │
│  │ Owner & contact                  │  │ Recent payments         View all │  │
│  │ ─────────────────────────────   │  │ Date  | Amount  | Gateway | Status│  │
│  │ Owner card (avatar, email,       │  └──────────────────────────────────┘  │
│  │ "send password reset",                                                     │
│  │ "reset 2FA")                     │  ┌──────────────────────────────────┐  │
│  │                                  │  │ Webhook events         View all  │  │
│  │ Subscription                     │  │ Date | Gateway | Event | Status   │  │
│  │ ─────────────────────────────   │  └──────────────────────────────────┘  │
│  │ Plan, MRR, gateway, period,      │                                         │
│  │ trial info, cancel_at_period_end │  ┌──────────────────────────────────┐  │
│  │                                  │  │ Audit log (last 10)    View all  │  │
│  │ Outbound webhooks                │  │ Date | User | Action | Target     │  │
│  │ ─────────────────────────────   │  └──────────────────────────────────┘  │
│  │ Endpoint count + delivery health │                                         │
│  │                                  │  ┌──────────────────────────────────┐  │
│  │ Tenant settings (JSON tree)      │  │ Login history (members) View all │  │
│  └─────────────────────────────────┘  │ Date | User | IP | Device | OK?   │  │
│                                       └──────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Hero banner (top-right; replaces "Rent Details")
- **Plan name** + **price / cadence** + **status pill** (`Active` / `Trialing` / `Past due` / `Canceled`).
- Trial countdown if `trialing`.
- "Cancel scheduled" warning if `cancel_at_period_end=true`.
- Quick-action chip row underneath: `Impersonate`, `Change plan`, `Refund last payment`, `Suspend` — frequent actions promoted out of the menu.

### Left-column cards (entity facts)
1. **Personal details** — name, slug, locale, timezone, currency, member count, created at, deleted at.
2. **Owner & contact** — owner avatar, name, email, phone, last login, "Send password reset", "Disable 2FA", "Open user page".
3. **Subscription** — plan, MRR contribution, gateway, current period, trial dates, dunning state, gateway customer ID, gateway subscription ID, "Open in {gateway} dashboard".
4. **Outbound webhooks** — endpoint count, delivery health (last 7-day success rate), inline "Open webhook admin".
5. **Tenant settings** — collapsed JSON pretty-print of the `settings` jsonb so the superadmin can audit it.

### Right-column mini-tables (5 rows each; `View all` deep-links)
1. **Recent invoices** — issued_at, amount, status pill, PDF icon.
2. **Recent payments** — date, amount, gateway, status (succeeded / failed / refunded), refund-action icon.
3. **Webhook events** — last 5 inbound events relevant to this tenant + replay icon.
4. **Audit log** — last 10 events filtered by `auditable_tenant_id`.
5. **Login history** — last 5 successful logins by tenant members.

### Tabs
- **Overview** — the layout above.
- **Billing** — full subscription detail, complete invoices/payments tables, gateway customer record, dunning state, all credits + comps.
- **Members** — DataTable of users with their role, joined date, last login. Per-row actions: impersonate, remove from tenant, transfer ownership.
- **Activity** — full audit log + webhook events + login history filtered to this tenant. Filterable timeline.
- **Danger zone** — Suspend, Force-delete, Restore, Cascade purge (GDPR), Transfer ownership.

### Actions menu (top-right dropdown)
- Impersonate owner *(exists)*
- Change plan *(deep-links to subscription action endpoint)*
- Apply credit
- Comp months
- Refund last payment
- Suspend tenant (sets `status=suspended`)
- Send invitation (opens dialog)
- GDPR export (downloads JSON)
- Soft-delete
- Restore (visible when deleted)

---

## 4. Tenants index — same redesign DNA

- Card-rich rows replace bare table cells:
  - Each row shows: tenant logo + name + slug, owner with avatar, status pill, current plan name + MRR pill, member count, last-activity timestamp.
- New columns: **Plan**, **MRR**, **Last active**, **Suspended?**
- Saved-view chips at the top: `All`, `Active`, `Trialing`, `Past due`, `Suspended`, `Archived`.
- Bulk actions when rows are selected: bulk-suspend, bulk-restore, bulk-export.

---

## 5. Users admin — NEW

### Index `/admin/users`
- DataTable columns: avatar + name, email, status pills (verified / 2FA / suspended), tenants count, role (Super Admin badge if applicable), last_login_at, created_at.
- Filters: verified, 2FA enabled, suspended, role, has API tokens, breach-checked.
- Saved views: `All`, `Super Admins`, `Unverified`, `Suspended`, `Recently created`.
- Bulk actions: suspend, restore, revoke all sessions, revoke all API tokens.

### Show `/admin/users/{user}`
Same wireframe DNA — left column facts, right column activity.

**Hero banner (top-right):** "Last login 2h ago — 5 active sessions" + Super Admin badge if applicable.

**Left-column cards:**
1. **Personal details** — avatar, name, email (with copy), phone, timezone, locale, member since, last login at, last IP.
2. **Security** — verified at, 2FA enabled, recovery codes generated at, last password change, password-breach check result, active sessions count.
3. **Tenants** — list of tenants this user belongs to + their role per tenant (Owner / Admin / Member). One-click "Impersonate in this tenant".
4. **API tokens** — token names + abilities + last_used_at. Link to revoke each.
5. **Notification preferences** — channel × event matrix summary.
6. **Linked social accounts** — Google / GitHub icons + linked emails + unlink action.

**Right-column mini-tables:**
1. **Login history** — last 10 entries (date, IP, device, outcome).
2. **Audit log** — last 10 actions performed by this user.
3. **Recent webhook events** — events where this user was the actor.
4. **Active sessions** — list with per-row "Revoke" + header "Revoke all".

**Tabs:** Overview · Tenants · Security · Activity · Danger zone

**Actions menu:**
- Impersonate (choose tenant if multiple memberships)
- Resend verification email
- Send password-reset link
- Force password reset on next login
- Disable 2FA (admin override; emits audit-log entry)
- Revoke all sessions
- Revoke all API tokens
- Grant / Revoke Super Admin role
- Suspend account
- GDPR data export
- Soft-delete (with 30-day purge window)
- Restore

---

## 6. Files to add / change

### Backend

| File | Change |
|---|---|
| `app/Http/Controllers/Admin/TenantsAdminController.php` | Expand `show()` to load + serialize: subscription, recent invoices, payments, webhook events, audit log, login history, outbound webhooks. Add `suspend()`, `restore()`, `forceDelete()`, `gdprExport()`. |
| `app/Http/Controllers/Admin/UsersAdminController.php` | **NEW** — `index`, `show`, `suspend`, `restore`, `resendVerification`, `forcePasswordReset`, `disableTwoFactor`, `revokeSessions`, `revokeTokens`, `grantSuperAdmin`, `revokeSuperAdmin`, `gdprExport`. |
| `app/Http/Requests/Admin/Tenants/*Request.php` | FormRequests per action: `SuspendTenantRequest`, `RestoreTenantRequest`, `GdprExportTenantRequest`, `ForceDeleteTenantRequest`. |
| `app/Http/Requests/Admin/Users/*Request.php` | **NEW** — FormRequests per action. |
| `app/Support/Admin/TenantAdminService.php` | **NEW** — canonical service seam for the new mutations (suspend / restore / export). |
| `app/Support/Admin/UserAdminService.php` | **NEW** — same, mirroring TenantAdminService. |
| `database/migrations/202x_xxxxxx_add_status_columns_to_users.php` | **NEW** — add `users.suspended_at` and `users.last_login_at` if not present. Tenants already has `status`. |
| `routes/admin.php` | Add user routes + extra tenant action routes. |
| `tests/Feature/Admin/Tenants/*Test.php` + `Users/*Test.php` | Cover every action with role gating + audit log assertions. |

### Frontend

| File | Change |
|---|---|
| `resources/js/pages/admin/tenants/show.tsx` | **Rewrite** to the new wireframe (uses shared primitives). |
| `resources/js/pages/admin/tenants/index.tsx` | Card-rich rows + saved-view chips + bulk-action toolbar. |
| `resources/js/pages/admin/users/index.tsx` | **NEW**. |
| `resources/js/pages/admin/users/show.tsx` | **NEW**. |
| `resources/js/components/admin/entity-detail/entity-header.tsx` | **NEW** primitive — back button + breadcrumbs + entity avatar + name + status dot + actions menu. |
| `resources/js/components/admin/entity-detail/entity-hero-banner.tsx` | **NEW** primitive — one big metric + status pill (the "Rent Details" equivalent). |
| `resources/js/components/admin/entity-detail/fact-card.tsx` | **NEW** primitive — left-column card with a key/value grid inside. |
| `resources/js/components/admin/entity-detail/activity-panel.tsx` | **NEW** primitive — right-column mini-table with sortable headers + `View all` link. |
| `resources/js/components/admin/entity-detail/actions-menu.tsx` | **NEW** primitive — dropdown with destructive items grouped at the bottom. |
| `resources/js/components/admin/entity-detail/tab-bar.tsx` | **NEW** primitive — query-string-backed tab strip. |
| `resources/js/components/admin/saved-views.tsx` | **NEW** — chip row + bulk-select toolbar. |
| `resources/js/components/app-sidebar.tsx` | Add **Users** link to the Admin group. |

---

## 7. Reusable layout primitives

The reference design is repeatable — every "entity detail" admin page (tenant, user, subscription, invoice, plan…) will share these. Building them as primitives means the Subscriptions / Invoices / Plans detail pages can adopt the same look later for free.

```tsx
<EntityHeader
    breadcrumb={['Tenants', 'Tenant Details']}
    backHref="/admin/tenants"
    avatar={tenant.logo_path}
    name={tenant.name}
    subtitle={tenant.slug}
    statusDot="active"
    actions={<ActionsMenu items={tenantActions} />}
/>

<EntityHeroBanner
    label="Subscription"
    value="$29 / month"
    pill={{ label: 'Active', variant: 'default' }}
    helper="Renews in 12 days"
/>

<FactCard title="Personal details">
    <FactGrid rows={[
        ['Name', tenant.name],
        ['Slug', <Mono>{tenant.slug}</Mono>],
        ['Locale', tenant.locale],
        // ...
    ]} />
</FactCard>

<ActivityPanel
    title="Recent invoices"
    viewAllHref={`/admin/subscriptions?tenant=${tenant.id}`}
    columns={[/* date, amount, status */]}
    rows={tenant.recent_invoices}
/>
```

---

## 8. Data sources per panel

All data already exists in the codebase — the redesign is **mostly serialization + UI**, with no new schema except possibly two columns on `users`.

| Panel | Source |
|---|---|
| Subscription summary | `Subscription` (where `tenant_id`) + `Plan` |
| Recent invoices | `Invoice::where('tenant_id')->latest('issued_at')->limit(5)` |
| Recent payments | `Payment::where('tenant_id')->latest()->limit(5)` |
| Webhook events | `WebhookEvent::where('tenant_id')->latest()->limit(5)` (back-fill `tenant_id` if not yet joined) |
| Audit log | `AuditLog::where('tenant_id', ...)` or `where('user_id', ...)` |
| Login history | `LoginHistory::whereHas('user.memberships', fn ($q) => $q->where('tenant_id', ...))` |
| Outbound webhooks | `OutboundWebhook` + `OutboundWebhookDelivery` for health |
| API tokens (user) | `PersonalAccessToken::where('tokenable_type', User::class)->where('tokenable_id', $user->id)` |
| Sessions (user) | `sessions` table (Laravel session-database driver) |
| Social accounts (user) | `SocialAccount::where('user_id')` |
| Notification prefs (user) | `NotificationPreference::where('user_id')` |

---

## 9. Implementation phasing

| # | Step | Effort | Ships |
|---|---|---|---|
| **A** | Shared layout primitives (`<EntityHeader>`, `<EntityHeroBanner>`, `<FactCard>`, `<ActivityPanel>`, `<ActionsMenu>`, `<TabBar>`) + preview cards on `/shared-components`. | ~3–4 h | Reusable parts; no behavior change yet. |
| **B** | Rewrite **Tenant show** to use the new layout + serializer enrichment (subscription, invoices, payments, audit, webhooks, login history). Read-only — no new actions yet. | ~4–5 h | New detail UI live. |
| **C** | Tenant **actions menu**: suspend, restore, GDPR export, change plan deep-link, refund-last-payment. Audit log entries for each. | ~3 h | All Super Admin actions surfaced for tenants. |
| **D** | Tenant **Members tab** with per-member impersonate / remove-from-tenant / transfer-ownership. | ~2–3 h | Member-level operations. |
| **E** | Tenant **Danger zone tab** + tenant index card-richness + saved-view chips + bulk actions. | ~3 h | Polished tenants surface end-to-end. |
| **F** | **Users admin** index + show using the same primitives. | ~5–6 h | New surface. |
| **G** | User actions: resend verification, force password reset, disable 2FA, revoke sessions, revoke tokens, grant/revoke Super Admin, suspend, GDPR export. | ~3–4 h | Full user-management toolkit. |
| **H** | Tests: action policy gating, audit log entries for each new mutation, factories for users + tenants under each state. | ~3 h | Locked in. |

**Total:** ~26–31 hours of focused work.

**Suggested cut points:**
- Phase A + B (~7–9 h) ships a visible UX upgrade for Tenants alone.
- A + B + C (~10–12 h) ships the tenants surface end-to-end with all admin actions.
- A through F (~20–24 h) ships everything except user-side actions.

---

## 10. Open decisions (need answers before phase A)

1. **Tenant `status` semantics.** `tenants.status` already exists — confirm we use `active` / `suspended` / `archived` (matches roadmap). Should we add `frozen` (billing-related lock) or `delinquent` (auto-set when dunning exhausts) as separate states, or fold them under `suspended`?
2. **Where does "suspended" block access?**
   - (a) Login forbidden ("This account has been suspended"), or
   - (b) Login allowed but every tenant route 403s with a banner, or
   - (c) Login allowed, tenant routes 403, but tenant Settings remains accessible so they can update billing details.
   - Affects the middleware change in `EnsureTenantMembership` / `SetCurrentTenant`.
3. **Audit log surface in the right column.** Should the right-column "Audit log" panel show events:
   - (a) **affecting** the tenant (model instance is on this tenant — default), or
   - (b) **performed by** the tenant's members (any model they touched)?
   - Default: (a), with a tab toggle for (b) on the full Activity tab.
4. **GDPR export format.** JSON zip with one file per table, or a single combined JSON document? Default: zip per table (mirrors what Spatie's package does + plays nicely with auditors).
5. **Disabling 2FA from admin.** Require the admin to confirm with their own 2FA code first (step-up auth)? Default: yes — emit an `admin.2fa_disabled` audit event with the admin's IP + UA.

---

## 11. Out of scope (deliberately)

- New schema beyond `users.suspended_at` + `users.last_login_at`.
- Tenant-level RBAC editor (already exists at `/t/{slug}/users` for tenant admins).
- Email template editor for the password-reset / verification emails (lives under Phase 6 of the roadmap).
- Multi-action audit-log diff viewer (a separate feature; the right-column panel just shows the chronological list).
- Webhook **outbound** delivery retry from this UI — already exists at `/admin/webhooks`.

---

## 12. Success criteria

After Phase H, the following must be true:

- **Tenants list** loads in < 200 ms with saved-view filtering applied.
- **Tenant detail** renders in < 300 ms for tenants with > 100 invoices (left panels lazy via `Inertia::defer`).
- Every action in the actions menu writes an `audit_logs` row with the admin user, the target tenant/user, and a serialized "before/after" diff.
- Every destructive action requires an `AlertDialog` confirmation.
- Suspending a tenant immediately revokes session access to `/t/{slug}/...` (verified by feature test).
- Users index + show work without a tenant context (Super Admin scope only).
- 100% of new controller methods + service methods have feature tests with role-gating coverage.
- TypeScript strict mode passes.
- All new components have `data-test=` attributes for Playwright.
