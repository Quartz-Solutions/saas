<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImpersonateUserRequest;
use App\Http\Requests\Admin\StopImpersonationRequest;
use App\Http\Requests\Admin\Tenants\ForceDeleteTenantRequest;
use App\Http\Requests\Admin\Tenants\GdprExportTenantRequest;
use App\Http\Requests\Admin\Tenants\RestoreTenantRequest;
use App\Http\Requests\Admin\Tenants\SuspendTenantRequest;
use App\Jobs\DeliverWebhookJob;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\LoginHistory;
use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Admin\ImpersonationService;
use App\Support\Admin\TenantAdminService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantsAdminController extends Controller
{
    private const ALLOWED_SORT = ['id', 'name', 'slug', 'status', 'created_at'];

    private const PER_PAGE = 20;

    private const SUMMARY_LIMIT = 5;

    private const AUDIT_LIMIT = 10;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $view = (string) $request->input('view', '');
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $query = Tenant::query()
            ->withTrashed()
            ->with(['owner:id,name,email'])
            ->withCount('memberships');

        // Saved-view shortcuts override status filters.
        if ($view !== '') {
            $this->applyView($query, $view);
        } else {
            if (! empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (! empty($filters['currency'])) {
                $query->where('currency', strtoupper((string) $filters['currency']));
            }

            if (! empty($filters['created_at']) && str_contains((string) $filters['created_at'], '|')) {
                [$from, $to] = explode('|', (string) $filters['created_at'], 2);
                if ($from !== '') {
                    $query->whereDate('created_at', '>=', $from);
                }
                if ($to !== '') {
                    $query->whereDate('created_at', '<=', $to);
                }
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        $tenantIds = collect($paginator->items())->pluck('id')->all();
        $activeSubs = Subscription::query()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->with('plan:id,slug,name,price_cents,currency,billing_period')
            ->get()
            ->keyBy('tenant_id');

        $rows = collect($paginator->items())->map(function (Tenant $t) use ($activeSubs) {
            $sub = $activeSubs->get($t->id);

            return [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'logo_path' => $t->logo_path,
                'status' => $t->status,
                'currency' => $t->currency,
                'created_at' => $t->created_at?->toIso8601String(),
                'deleted_at' => $t->deleted_at?->toIso8601String(),
                'owner' => $t->owner ? [
                    'id' => $t->owner->id,
                    'name' => $t->owner->name,
                    'email' => $t->owner->email,
                ] : null,
                'members_count' => (int) $t->memberships_count,
                'plan' => $sub?->plan ? [
                    'slug' => $sub->plan->slug,
                    'name' => $sub->plan->name,
                ] : null,
                'mrr_cents' => $sub?->plan?->billing_period === 'monthly'
                    ? (int) ($sub->unit_amount_cents ?? $sub->plan->price_cents ?? 0)
                    : null,
                'subscription_status' => $sub?->status,
            ];
        })->all();

        return Inertia::render('admin/tenants/index', [
            'tenants' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ],
            'tableState' => [
                'search' => $search,
                'filters' => (object) $filters,
                'sort' => ['column' => $sort, 'direction' => $direction],
                'view' => $view,
            ],
            'viewCounts' => $this->viewCounts(),
        ]);
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        // Resolve withTrashed manually since route binding excludes soft-deletes.
        if (! $tenant->exists) {
            $tenant = Tenant::withTrashed()->findOrFail($request->route('tenant'));
        }

        $tenant->load(['owner:id,name,email,last_login_at']);

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latest('id')
            ->with('plan')
            ->first()
            ?? Subscription::query()
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->with('plan')
                ->first();

        $invoices = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get(['id', 'number', 'status', 'currency', 'total_cents', 'amount_paid_cents', 'amount_due_cents', 'issued_at', 'paid_at']);

        $payments = Payment::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get(['id', 'gateway', 'status', 'amount_cents', 'refunded_cents', 'currency', 'captured_at', 'failed_at', 'refunded_at', 'created_at']);

        $webhookEvents = WebhookEvent::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get(['id', 'gateway', 'event_type', 'gateway_event_id', 'status', 'created_at']);

        $auditEntries = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->with('user:id,name,email')
            ->latest('id')
            ->limit(self::AUDIT_LIMIT)
            ->get();

        $memberUserIds = $tenant->memberships()->pluck('user_id');
        $loginHistory = LoginHistory::query()
            ->whereIn('user_id', $memberUserIds)
            ->with('user:id,name,email')
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get();

        $outboundWebhooks = OutboundWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->withCount(['deliveries', 'deliveries as failed_deliveries_count' => function ($q) {
                $q->where('status', 'failed')
                    ->orWhere('status', 'abandoned');
            }])
            ->get();

        // Recent outbound webhook deliveries across all of this tenant's
        // endpoints — surfaced as a mini-table so the admin can retry
        // failures inline without leaving the tenant detail.
        $outboundDeliveries = OutboundWebhookDelivery::query()
            ->whereIn('outbound_webhook_id', $outboundWebhooks->pluck('id'))
            ->with('webhook:id,url,description')
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get();

        $membersPaginator = $tenant->memberships()
            ->with('user:id,name,email,last_login_at,suspended_at,avatar_path')
            ->orderBy('joined_at', 'desc')
            ->paginate(20, ['*'], 'members_page')
            ->withQueryString();

        return Inertia::render('admin/tenants/show', [
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'currency' => $tenant->currency,
                'timezone' => $tenant->timezone,
                'locale' => $tenant->locale,
                'logo_path' => $tenant->logo_path,
                'settings' => $tenant->settings,
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'created_at' => $tenant->created_at?->toIso8601String(),
                'updated_at' => $tenant->updated_at?->toIso8601String(),
                'deleted_at' => $tenant->deleted_at?->toIso8601String(),
                'members_count' => $tenant->memberships()->count(),
                'owner' => $tenant->owner ? [
                    'id' => $tenant->owner->id,
                    'name' => $tenant->owner->name,
                    'email' => $tenant->owner->email,
                    'last_login_at' => $tenant->owner->last_login_at?->toIso8601String(),
                ] : null,
            ],
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'gateway' => $subscription->gateway,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'unit_amount_cents' => (int) $subscription->unit_amount_cents,
                'currency' => $subscription->currency,
                'quantity' => (int) $subscription->quantity,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_start' => $subscription->current_period_start?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
                'canceled_at' => $subscription->canceled_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'slug' => $subscription->plan->slug,
                    'name' => $subscription->plan->name,
                    'price_cents' => (int) $subscription->plan->price_cents,
                    'currency' => $subscription->plan->currency,
                    'billing_period' => $subscription->plan->billing_period,
                ] : null,
            ] : null,
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'status' => $i->status,
                'currency' => $i->currency,
                'total_cents' => (int) $i->total_cents,
                'amount_paid_cents' => (int) $i->amount_paid_cents,
                'amount_due_cents' => (int) $i->amount_due_cents,
                'issued_at' => $i->issued_at?->toIso8601String(),
                'paid_at' => $i->paid_at?->toIso8601String(),
            ])->all(),
            'payments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'gateway' => $p->gateway,
                'status' => $p->status,
                'amount_cents' => (int) $p->amount_cents,
                'refunded_cents' => (int) $p->refunded_cents,
                'currency' => $p->currency,
                'captured_at' => $p->captured_at?->toIso8601String(),
                'failed_at' => $p->failed_at?->toIso8601String(),
                'refunded_at' => $p->refunded_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ])->all(),
            'webhookEvents' => $webhookEvents->map(fn (WebhookEvent $w) => [
                'id' => $w->id,
                'gateway' => $w->gateway,
                'event_type' => $w->event_type,
                'gateway_event_id' => $w->gateway_event_id,
                'status' => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
            ])->all(),
            'auditLog' => $auditEntries->map(fn (AuditLog $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'user' => $a->user ? [
                    'id' => $a->user->id,
                    'name' => $a->user->name,
                    'email' => $a->user->email,
                ] : null,
                'auditable_type' => $a->auditable_type,
                'auditable_id' => $a->auditable_id,
                'new_values' => $a->new_values,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->all(),
            'loginHistory' => $loginHistory->map(fn (LoginHistory $l) => [
                'id' => $l->id,
                'user' => $l->user ? [
                    'id' => $l->user->id,
                    'name' => $l->user->name,
                    'email' => $l->user->email,
                ] : null,
                'outcome' => $l->outcome,
                'method' => $l->method,
                'ip' => $l->ip,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->all(),
            'outboundWebhooks' => [
                'count' => $outboundWebhooks->count(),
                'active' => $outboundWebhooks->where('is_active', true)->count(),
                'deliveries_total' => (int) $outboundWebhooks->sum('deliveries_count'),
                'deliveries_failed' => (int) $outboundWebhooks->sum('failed_deliveries_count'),
            ],
            'outboundDeliveries' => $outboundDeliveries->map(fn ($d) => [
                'id' => $d->id,
                'webhook_url' => $d->webhook?->url,
                'event_type' => $d->event_type,
                'status' => $d->status,
                'attempt' => (int) $d->attempt,
                'response_code' => $d->response_code,
                'duration_ms' => $d->duration_ms,
                'created_at' => $d->created_at?->toIso8601String(),
                'failed_at' => $d->failed_at?->toIso8601String(),
                'retryable' => in_array($d->status, ['failed', 'abandoned'], true),
            ])->all(),
            'members' => [
                'data' => collect($membersPaginator->items())->map(fn ($m) => [
                    'membership_id' => $m->id,
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                    'avatar_path' => $m->user?->avatar_path,
                    'last_login_at' => $m->user?->last_login_at?->toIso8601String(),
                    'suspended_at' => $m->user?->suspended_at?->toIso8601String(),
                    'is_owner' => $m->user?->id === $tenant->owner_id,
                    'joined_at' => $m->joined_at?->toIso8601String(),
                ])->values()->all(),
                'meta' => [
                    'current_page' => $membersPaginator->currentPage(),
                    'last_page' => $membersPaginator->lastPage(),
                    'per_page' => $membersPaginator->perPage(),
                    'total' => $membersPaginator->total(),
                ],
            ],
        ]);
    }

    public function impersonate(ImpersonateUserRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->loadMissing('owner');

        if ($tenant->owner === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Tenant has no owner to impersonate.'),
            ]);

            return back();
        }

        $service = app(ImpersonationService::class);
        $service->start(
            $request->user(),
            $tenant->owner,
            $request,
            $request->input('reason'),
        );

        return redirect()->route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
    }

    public function suspend(SuspendTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        app(TenantAdminService::class)->suspend(
            $tenant,
            $request->user(),
            $request->input('reason'),
            $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tenant suspended.'),
        ]);

        return back();
    }

    public function restore(RestoreTenantRequest $request, int $tenantId): RedirectResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($tenantId);

        app(TenantAdminService::class)->restore(
            $tenant,
            $request->user(),
            $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tenant restored.'),
        ]);

        return redirect()->route('admin.tenants.show', ['tenant' => $tenant->id]);
    }

    public function forceDelete(ForceDeleteTenantRequest $request, int $tenantId): RedirectResponse
    {
        $tenant = Tenant::withTrashed()->findOrFail($tenantId);

        app(TenantAdminService::class)->forceDelete(
            $tenant,
            $request->user(),
            $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tenant permanently deleted.'),
        ]);

        return redirect()->route('admin.tenants.index');
    }

    public function destroy(SuspendTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        // Soft-delete uses the same authorization as suspend (and the same
        // FormRequest payload — optional reason).
        app(TenantAdminService::class)->softDelete(
            $tenant,
            $request->user(),
            $request->input('reason'),
            $request,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tenant deleted (soft).'),
        ]);

        return redirect()->route('admin.tenants.index');
    }

    public function gdprExport(GdprExportTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $data = app(TenantAdminService::class)->gdprExport(
            $tenant,
            $request->user(),
            $request,
        );

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="tenant-'.$tenant->slug.'-export.json"',
        ]);
    }

    /**
     * Re-queue a failed (or stuck) outbound webhook delivery from the
     * tenant detail activity panel. Available to Super Admins.
     */
    public function retryWebhookDelivery(Request $request, Tenant $tenant, OutboundWebhookDelivery $delivery): RedirectResponse
    {
        // Authorise via the route group's Super Admin gate already; just
        // sanity-check the delivery belongs to this tenant's webhooks.
        $delivery->loadMissing('webhook');
        if ($delivery->webhook?->tenant_id !== $tenant->id) {
            abort(404);
        }

        $delivery->forceFill([
            'status' => OutboundWebhookDelivery::STATUS_PENDING,
            'failed_at' => null,
            'next_retry_at' => null,
        ])->save();

        DeliverWebhookJob::dispatch($delivery->id);

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'action' => 'admin.tenant.webhook_delivery_retried',
            'auditable_type' => OutboundWebhookDelivery::class,
            'auditable_id' => $delivery->id,
            'new_values' => [
                'webhook_id' => $delivery->outbound_webhook_id,
                'event_type' => $delivery->event_type,
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Delivery re-queued.'),
        ]);

        return back();
    }

    public function stopImpersonation(StopImpersonationRequest $request): RedirectResponse
    {
        unset($request);

        app(ImpersonationService::class)->stop(request());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Stopped impersonating.'),
        ]);

        return redirect()->route('admin.tenants.index');
    }

    public function removeMember(SuspendTenantRequest $request, Tenant $tenant, User $user): RedirectResponse
    {
        if ($user->id === $tenant->owner_id) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Transfer ownership before removing the owner.'),
            ]);

            return back();
        }

        $tenant->memberships()->where('user_id', $user->id)->delete();

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'action' => 'admin.tenant.member_removed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Member removed.'),
        ]);

        return back();
    }

    public function impersonateMember(ImpersonateUserRequest $request, Tenant $tenant, User $user): RedirectResponse
    {
        if (! $tenant->memberships()->where('user_id', $user->id)->exists()) {
            abort(404);
        }

        app(ImpersonationService::class)->start(
            $request->user(),
            $user,
            $request,
            $request->input('reason'),
        );

        return redirect()->route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
    }

    public function transferOwnership(SuspendTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $newOwnerId = (int) $request->input('new_owner_id');
        $newOwner = User::find($newOwnerId);

        if ($newOwner === null || ! $tenant->memberships()->where('user_id', $newOwner->id)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('New owner must be an existing member.'),
            ]);

            return back();
        }

        $oldOwnerId = $tenant->owner_id;
        $tenant->owner_id = $newOwner->id;
        $tenant->save();

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'action' => 'admin.tenant.ownership_transferred',
            'auditable_type' => Tenant::class,
            'auditable_id' => $tenant->id,
            'old_values' => ['owner_id' => $oldOwnerId],
            'new_values' => ['owner_id' => $newOwner->id],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ownership transferred.'),
        ]);

        return back();
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applyView($query, string $view): void
    {
        match ($view) {
            'active' => $query->where('status', 'active')->whereNull('deleted_at'),
            'trialing' => $query->where('status', 'active')
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->whereNull('deleted_at'),
            'past_due' => $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('subscriptions')
                    ->whereColumn('subscriptions.tenant_id', 'tenants.id')
                    ->where('subscriptions.status', 'past_due');
            })->whereNull('deleted_at'),
            'suspended' => $query->where('status', 'suspended')->whereNull('deleted_at'),
            'archived' => $query->whereNotNull('deleted_at'),
            default => null,
        };
    }

    /**
     * @return array<string, int>
     */
    private function viewCounts(): array
    {
        $base = Tenant::query()->whereNull('deleted_at');

        return [
            'all' => Tenant::query()->withTrashed()->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'suspended' => (clone $base)->where('status', 'suspended')->count(),
            'archived' => Tenant::onlyTrashed()->count(),
            'past_due' => (clone $base)->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('subscriptions')
                    ->whereColumn('subscriptions.tenant_id', 'tenants.id')
                    ->where('subscriptions.status', 'past_due');
            })->count(),
        ];
    }
}
