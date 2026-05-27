<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Support\Billing\Checkout\CheckoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutSessionsController extends Controller
{
    private const ALLOWED_SORT = ['id', 'status', 'gateway', 'amount_cents', 'created_at', 'completed_at'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $paginator = $this->buildQuery($request)
            ->with([
                'tenant:id,slug,name',
                'plan:id,slug,name',
                'user:id,name,email',
            ])
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, (int) $request->input('page', 1)))
            ->withQueryString();

        return Inertia::render('admin/checkout-sessions/index', [
            'sessions' => [
                'data' => collect($paginator->items())->map(fn (CheckoutSession $s) => $this->serializeRow($s))->all(),
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
                'search' => trim((string) $request->input('search', '')),
                'filters' => (object) (array) $request->input('filter', []),
                'sort' => [
                    'column' => $this->sortColumn($request),
                    'direction' => $this->sortDirection($request),
                ],
            ],
            'stats' => $this->stats(),
        ]);
    }

    public function show(CheckoutSession $checkoutSession): Response
    {
        $checkoutSession->load([
            'tenant:id,slug,name,owner_id',
            'tenant.owner:id,name,email',
            'plan:id,slug,name,price_cents,currency,billing_period',
            'user:id,name,email',
            'subscription:id,status,gateway,unit_amount_cents,currency',
            'invoice:id,number,status,total_cents,currency',
        ]);

        return Inertia::render('admin/checkout-sessions/show', [
            'session' => $this->serializeDetail($checkoutSession),
        ]);
    }

    public function forceCancel(Request $request, CheckoutSession $checkoutSession, CheckoutService $checkout): RedirectResponse
    {
        if ($checkoutSession->isTerminal()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This session is already terminal.')]);

            return back();
        }

        $checkout->cancel($checkoutSession, 'admin_force_cancel');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Checkout session canceled.')]);

        return back();
    }

    protected function buildQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);

        $query = CheckoutSession::query();

        if ($search !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $needle = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($like, $needle) {
                $q->whereRaw('LOWER(public_id) '.$like.' ?', [$needle])
                    ->orWhereRaw('LOWER(gateway_session_id) '.$like.' ?', [$needle])
                    ->orWhereHas('tenant', function ($q) use ($like, $needle) {
                        $q->whereRaw('LOWER(name) '.$like.' ?', [$needle])
                            ->orWhereRaw('LOWER(slug) '.$like.' ?', [$needle]);
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }
        if (! empty($filters['intent'])) {
            $query->where('intent', $filters['intent']);
        }

        return $query;
    }

    protected function sortColumn(Request $request): string
    {
        return in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? (string) $request->input('sort')
            : 'created_at';
    }

    protected function sortDirection(Request $request): string
    {
        return $request->input('direction') === 'asc' ? 'asc' : 'desc';
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRow(CheckoutSession $s): array
    {
        return [
            'id' => $s->id,
            'public_id' => $s->public_id,
            'intent' => $s->intent,
            'status' => $s->status,
            'gateway' => $s->gateway,
            'currency' => $s->currency,
            'amount_cents' => (int) $s->amount_cents,
            'result_kind' => $s->result_kind,
            'created_at' => $s->created_at?->toIso8601String(),
            'completed_at' => $s->completed_at?->toIso8601String(),
            'expires_at' => $s->expires_at?->toIso8601String(),
            'tenant' => $s->tenant ? ['id' => $s->tenant->id, 'slug' => $s->tenant->slug, 'name' => $s->tenant->name] : null,
            'plan' => $s->plan ? ['slug' => $s->plan->slug, 'name' => $s->plan->name] : null,
            'user' => $s->user ? ['id' => $s->user->id, 'name' => $s->user->name, 'email' => $s->user->email] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDetail(CheckoutSession $s): array
    {
        return array_merge($this->serializeRow($s), [
            'gateway_session_id' => $s->gateway_session_id,
            'result_payload' => $s->result_payload,
            'metadata' => $s->metadata,
            'canceled_at' => $s->canceled_at?->toIso8601String(),
            'cancel_reason' => $s->cancel_reason,
            'subscription' => $s->subscription ? [
                'id' => $s->subscription->id,
                'status' => $s->subscription->status,
                'unit_amount_cents' => (int) $s->subscription->unit_amount_cents,
                'currency' => $s->subscription->currency,
            ] : null,
            'invoice' => $s->invoice ? [
                'id' => $s->invoice->id,
                'number' => $s->invoice->number,
                'status' => $s->invoice->status,
                'total_cents' => (int) $s->invoice->total_cents,
                'currency' => $s->invoice->currency,
            ] : null,
            'tenant' => $s->tenant ? [
                'id' => $s->tenant->id,
                'slug' => $s->tenant->slug,
                'name' => $s->tenant->name,
                'owner' => $s->tenant->owner ? [
                    'id' => $s->tenant->owner->id,
                    'name' => $s->tenant->owner->name,
                    'email' => $s->tenant->owner->email,
                ] : null,
            ] : null,
            'plan' => $s->plan ? [
                'slug' => $s->plan->slug,
                'name' => $s->plan->name,
                'price_cents' => (int) $s->plan->price_cents,
                'currency' => $s->plan->currency,
                'billing_period' => $s->plan->billing_period,
            ] : null,
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function stats(): array
    {
        $base = CheckoutSession::query();

        return [
            'pending' => (clone $base)->where('status', CheckoutSession::STATUS_PENDING)->count(),
            'awaiting_payment' => (clone $base)->where('status', CheckoutSession::STATUS_AWAITING_PAYMENT)->count(),
            'completed_30d' => (clone $base)
                ->where('status', CheckoutSession::STATUS_COMPLETED)
                ->where('completed_at', '>=', now()->subDays(30))
                ->count(),
            'expired_30d' => (clone $base)
                ->where('status', CheckoutSession::STATUS_EXPIRED)
                ->where('canceled_at', '>=', now()->subDays(30))
                ->count(),
        ];
    }
}
