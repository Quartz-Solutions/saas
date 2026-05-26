<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriptionsAdminController extends Controller
{
    private const ALLOWED_SORT = [
        'id', 'status', 'unit_amount_cents', 'current_period_end', 'created_at',
    ];

    private const PER_PAGE = 25;

    private const NON_TERMINAL = ['trialing', 'active', 'past_due'];

    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('admin/subscriptions/index', [
            'subscriptions' => $this->paginate($request),
            'tableState' => $this->tableState($request),
            'stats' => $this->stats(),
            'plans' => Plan::query()
                ->withTrashed()
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'name' => $p->name])
                ->all(),
        ]);
    }

    public function show(Subscription $subscription): InertiaResponse
    {
        $subscription->load([
            'tenant:id,slug,name,owner_id',
            'tenant.owner:id,name,email',
            'plan',
            'invoices' => fn ($q) => $q->latest('id')->limit(20),
        ]);

        $payments = Payment::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->whereIn('invoice_id', $subscription->invoices->pluck('id')->all())
            ->latest('id')
            ->limit(20)
            ->get();

        /** @var Connection $connection */
        $connection = DB::connection();
        $payloadAsText = $connection->getDriverName() === 'pgsql' ? 'payload::text' : 'payload';

        $webhookEvents = WebhookEvent::query()
            ->where('gateway', $subscription->gateway)
            ->when(
                $subscription->gateway_subscription_id,
                fn ($q, $id) => $q->where(function ($q) use ($id, $payloadAsText) {
                    $q->whereRaw("{$payloadAsText} LIKE ?", ['%'.$id.'%'])
                        ->orWhere('gateway_event_id', $id);
                }),
            )
            ->latest('id')
            ->limit(20)
            ->get(['id', 'gateway', 'event_type', 'gateway_event_id', 'status', 'created_at']);

        $auditEntries = AuditLog::query()
            ->where('auditable_type', Subscription::class)
            ->where('auditable_id', $subscription->id)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'action', 'user_id', 'old_values', 'new_values', 'created_at']);

        return Inertia::render('admin/subscriptions/show', [
            'subscription' => $this->serialize($subscription),
            'invoices' => $subscription->invoices->map(fn (Invoice $inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'status' => $inv->status,
                'total_cents' => (int) $inv->total_cents,
                'amount_paid_cents' => (int) $inv->amount_paid_cents,
                'amount_due_cents' => (int) $inv->amount_due_cents,
                'currency' => $inv->currency,
                'issued_at' => $inv->issued_at?->toIso8601String(),
                'paid_at' => $inv->paid_at?->toIso8601String(),
            ])->all(),
            'payments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'invoice_id' => $p->invoice_id,
                'gateway' => $p->gateway,
                'status' => $p->status,
                'amount_cents' => (int) $p->amount_cents,
                'refunded_cents' => (int) ($p->refunded_cents ?? 0),
                'currency' => $p->currency,
                'captured_at' => $p->captured_at?->toIso8601String(),
            ])->all(),
            'webhookEvents' => $webhookEvents->map(fn ($w) => [
                'id' => $w->id,
                'gateway' => $w->gateway,
                'event_type' => $w->event_type,
                'external_id' => $w->gateway_event_id,
                'status' => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
            ])->all(),
            'auditEntries' => $auditEntries->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'user_id' => $a->user_id,
                'old_values' => $a->old_values,
                'new_values' => $a->new_values,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->all(),
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'name', 'price_cents', 'currency', 'billing_period'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'price_cents' => (int) $p->price_cents,
                    'currency' => $p->currency,
                    'billing_period' => $p->billing_period,
                ])
                ->all(),
            'reasons' => [
                'credit' => (array) config('billing-credit-reasons.credit'),
                'comp' => (array) config('billing-credit-reasons.comp'),
                'refund' => (array) config('billing-credit-reasons.refund'),
                'cancellation' => (array) config('billing-credit-reasons.cancellation'),
                'manual_payment_method' => (array) config('billing-credit-reasons.manual_payment_method'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('Super Admin'), 403);

        $filename = 'subscriptions-'.now()->format('Y-m-d').'.csv';

        return Response::streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'tenant', 'plan', 'status', 'gateway',
                'unit_amount_cents', 'currency', 'trial_ends_at',
                'current_period_end', 'cancel_at_period_end', 'created_at',
            ]);

            $this->buildQuery($request)
                ->with(['tenant:id,slug', 'plan:id,slug'])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r->id,
                            $r->tenant?->slug,
                            $r->plan?->slug,
                            $r->status,
                            $r->gateway,
                            $r->unit_amount_cents,
                            $r->currency,
                            $r->trial_ends_at?->toIso8601String(),
                            $r->current_period_end?->toIso8601String(),
                            $r->cancel_at_period_end ? '1' : '0',
                            $r->created_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function paginate(Request $request): array
    {
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $paginator = $this->buildQuery($request)
            ->with(['tenant:id,slug,name,owner_id', 'tenant.owner:id,name,email', 'plan:id,slug,name'])
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        return [
            'data' => $paginator->getCollection()->map(fn (Subscription $s) => $this->serialize($s))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
        ];
    }

    protected function buildQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);

        $query = Subscription::query();

        if ($search !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $needle = '%'.strtolower($search).'%';
            $query->whereHas('tenant', function ($q) use ($like, $needle) {
                $q->whereRaw("LOWER(name) {$like} ?", [$needle])
                    ->orWhereRaw("LOWER(slug) {$like} ?", [$needle])
                    ->orWhereHas('owner', fn ($o) => $o->whereRaw("LOWER(email) {$like} ?", [$needle]));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['plan'])) {
            $query->where('plan_id', $filters['plan']);
        }
        if (! empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function tableState(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'filters' => (object) (array) $request->input('filter', []),
            'sort' => [
                'column' => in_array($request->input('sort'), self::ALLOWED_SORT, true)
                    ? $request->input('sort')
                    : 'created_at',
                'direction' => $request->input('direction') === 'asc' ? 'asc' : 'desc',
            ],
        ];
    }

    /**
     * MRR is the sum of active/trialing subscriptions normalized to monthly,
     * with a quick churn delta. Cached because the admin overview hits this
     * on every page render.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $active = Subscription::query()->where('status', 'active')->count();
        $trialing = Subscription::query()->where('status', 'trialing')->count();
        $pastDue = Subscription::query()->where('status', 'past_due')->count();
        $cancelledLast30 = Subscription::query()
            ->where('status', 'canceled')
            ->where('canceled_at', '>=', now()->subDays(30))
            ->count();

        // MRR: normalize each non-terminal subscription's unit price to per-month.
        $rows = Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', ['active', 'past_due'])
            ->select([
                'plans.billing_period',
                'plans.billing_interval',
                DB::raw('SUM(subscriptions.unit_amount_cents * subscriptions.quantity) as total_cents'),
            ])
            ->groupBy('plans.billing_period', 'plans.billing_interval')
            ->get();

        $mrrCents = 0;
        foreach ($rows as $row) {
            $period = (string) $row->billing_period;
            $interval = max(1, (int) $row->billing_interval);
            $total = (int) $row->total_cents;
            $perMonth = match ($period) {
                'day' => (int) round($total * 30 / $interval),
                'week' => (int) round($total * 4.345 / $interval),
                'month' => (int) round($total / $interval),
                'year' => (int) round($total / (12 * $interval)),
                default => 0,
            };
            $mrrCents += $perMonth;
        }

        return [
            'active' => $active,
            'trialing' => $trialing,
            'past_due' => $pastDue,
            'canceled_30d' => $cancelledLast30,
            'mrr_cents' => $mrrCents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Subscription $sub): array
    {
        return [
            'id' => $sub->id,
            'gateway' => $sub->gateway,
            'gateway_subscription_id' => $sub->gateway_subscription_id,
            'status' => $sub->status,
            'currency' => $sub->currency,
            'unit_amount_cents' => (int) $sub->unit_amount_cents,
            'quantity' => (int) $sub->quantity,
            'trial_starts_at' => $sub->trial_starts_at?->toIso8601String(),
            'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
            'current_period_start' => $sub->current_period_start?->toIso8601String(),
            'current_period_end' => $sub->current_period_end?->toIso8601String(),
            'cancel_at_period_end' => (bool) $sub->cancel_at_period_end,
            'canceled_at' => $sub->canceled_at?->toIso8601String(),
            'cancellation_reason' => $sub->cancellation_reason,
            'created_at' => $sub->created_at?->toIso8601String(),
            'tenant' => $sub->tenant ? [
                'id' => $sub->tenant->id,
                'slug' => $sub->tenant->slug,
                'name' => $sub->tenant->name,
                'owner' => $sub->tenant->owner ? [
                    'id' => $sub->tenant->owner->id,
                    'name' => $sub->tenant->owner->name,
                    'email' => $sub->tenant->owner->email,
                ] : null,
            ] : null,
            'plan' => $sub->plan ? [
                'id' => $sub->plan->id,
                'slug' => $sub->plan->slug,
                'name' => $sub->plan->name,
                'billing_period' => $sub->plan->billing_period,
                'billing_interval' => (int) $sub->plan->billing_interval,
            ] : null,
        ];
    }
}
