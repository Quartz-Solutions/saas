<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImpersonateUserRequest;
use App\Http\Requests\Admin\StopImpersonationRequest;
use App\Models\Tenant;
use App\Support\Admin\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantsAdminController extends Controller
{
    private const ALLOWED_SORT = ['id', 'name', 'slug', 'status', 'created_at'];

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $query = Tenant::query()
            ->with(['owner:id,name,email'])
            ->withCount('memberships');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

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

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        $rows = collect($paginator->items())->map(fn (Tenant $t) => [
            'id' => $t->id,
            'slug' => $t->slug,
            'name' => $t->name,
            'status' => $t->status,
            'currency' => $t->currency,
            'created_at' => $t->created_at?->toIso8601String(),
            'owner' => $t->owner ? [
                'id' => $t->owner->id,
                'name' => $t->owner->name,
                'email' => $t->owner->email,
            ] : null,
            'members_count' => (int) $t->memberships_count,
        ])->all();

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
            ],
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load(['owner:id,name,email', 'memberships.user:id,name,email']);

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
                'created_at' => $tenant->created_at?->toIso8601String(),
                'updated_at' => $tenant->updated_at?->toIso8601String(),
                'deleted_at' => $tenant->deleted_at?->toIso8601String(),
                'owner' => $tenant->owner ? [
                    'id' => $tenant->owner->id,
                    'name' => $tenant->owner->name,
                    'email' => $tenant->owner->email,
                ] : null,
                'members' => $tenant->memberships->map(fn ($m) => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                    'joined_at' => $m->joined_at?->toIso8601String(),
                ])->values()->all(),
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
}
