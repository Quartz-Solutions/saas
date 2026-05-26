<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanDestroyRequest;
use App\Http\Requests\Admin\PlanRestoreRequest;
use App\Http\Requests\Admin\PlanStoreRequest;
use App\Http\Requests\Admin\PlanUpdateRequest;
use App\Models\Currency;
use App\Models\Plan;
use App\Support\Billing\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PlansController extends Controller
{
    private const ALLOWED_SORT = ['id', 'slug', 'name', 'price_cents', 'sort_order', 'created_at'];

    private const PER_PAGE = 25;

    public function __construct(private readonly PlanService $plans) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $includeArchived = ($filters['status'] ?? '') === 'archived';

        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'sort_order';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';
        $page = max(1, (int) $request->input('page', 1));

        $query = Plan::query()
            ->withTrashed()
            ->withCount(['subscriptions as active_subscriptions_count' => function ($q) {
                $q->whereIn('status', ['trialing', 'active', 'past_due']);
            }]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if (! $includeArchived) {
            $query->whereNull('deleted_at');
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active'] === 'yes');
        }
        if (isset($filters['is_public']) && $filters['is_public'] !== '') {
            $query->where('is_public', $filters['is_public'] === 'yes');
        }
        if (isset($filters['billing_period']) && $filters['billing_period'] !== '') {
            $query->where('billing_period', $filters['billing_period']);
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('admin/plans/index', [
            'plans' => [
                'data' => $paginator->getCollection()->map(fn (Plan $p) => $this->serialize($p))->all(),
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
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/plans/edit', [
            'plan' => null,
            'currencies' => $this->currencies(),
        ]);
    }

    public function store(PlanStoreRequest $request): RedirectResponse
    {
        $this->plans->save(null, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan created.')]);

        return to_route('admin.plans.index');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('admin/plans/edit', [
            'plan' => $this->serialize($plan->loadCount(['subscriptions as active_subscriptions_count' => function ($q) {
                $q->whereIn('status', ['trialing', 'active', 'past_due']);
            }])),
            'currencies' => $this->currencies(),
        ]);
    }

    public function update(PlanUpdateRequest $request, Plan $plan): RedirectResponse
    {
        $this->plans->save($plan, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return back();
    }

    public function destroy(PlanDestroyRequest $request, Plan $plan): RedirectResponse
    {
        unset($request);

        try {
            $this->plans->archive($plan);
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan archived.')]);

        return to_route('admin.plans.index');
    }

    public function restore(PlanRestoreRequest $request, int $plan): RedirectResponse
    {
        unset($request);
        $row = Plan::query()->withTrashed()->findOrFail($plan);

        $this->plans->restore($row);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan restored.')]);

        return to_route('admin.plans.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => (int) $plan->price_cents,
            'currency' => $plan->currency,
            'billing_period' => $plan->billing_period,
            'billing_interval' => (int) $plan->billing_interval,
            'trial_days' => (int) $plan->trial_days,
            'features' => $plan->features ?? [],
            'gateway_ids' => $plan->gateway_ids ?? [],
            'is_active' => (bool) $plan->is_active,
            'is_public' => (bool) $plan->is_public,
            'sort_order' => (int) $plan->sort_order,
            'deleted_at' => $plan->deleted_at?->toIso8601String(),
            'created_at' => $plan->created_at?->toIso8601String(),
            'active_subscriptions_count' => (int) ($plan->active_subscriptions_count ?? 0),
        ];
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    protected function currencies(): array
    {
        return Currency::query()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name])
            ->all();
    }
}
