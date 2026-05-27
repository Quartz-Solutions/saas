<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutHistoryController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request, string $tenantSlug): Response
    {
        $tenant = app('currentTenant');

        $paginator = CheckoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->with(['plan:id,slug,name'])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('billing/checkout-history', [
            'sessions' => [
                'data' => collect($paginator->items())->map(fn (CheckoutSession $s) => [
                    'public_id' => $s->public_id,
                    'intent' => $s->intent,
                    'status' => $s->status,
                    'gateway' => $s->gateway,
                    'currency' => $s->currency,
                    'amount_cents' => (int) $s->amount_cents,
                    'plan' => $s->plan ? ['slug' => $s->plan->slug, 'name' => $s->plan->name] : null,
                    'created_at' => $s->created_at?->toIso8601String(),
                    'completed_at' => $s->completed_at?->toIso8601String(),
                    'canceled_at' => $s->canceled_at?->toIso8601String(),
                    'expires_at' => $s->expires_at?->toIso8601String(),
                    'can_resume' => ! $s->isTerminal() && $s->expires_at && $s->expires_at->isFuture(),
                ])->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ],
        ]);
    }
}
