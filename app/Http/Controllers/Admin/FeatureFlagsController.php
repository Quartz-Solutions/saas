<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeatureFlagDestroyRequest;
use App\Http\Requests\Admin\FeatureFlagStoreRequest;
use App\Http\Requests\Admin\FeatureFlagUpdateRequest;
use App\Models\FeatureFlag;
use App\Models\FeatureFlagOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureFlagsController extends Controller
{
    private const ALLOWED_SORT = ['id', 'key', 'name', 'enabled_globally', 'created_at'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $query = FeatureFlag::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        if (isset($filters['enabled_globally']) && $filters['enabled_globally'] !== '') {
            $query->where('enabled_globally', $filters['enabled_globally'] === 'yes');
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('admin/feature-flags/index', [
            'featureFlags' => [
                'data' => $paginator->items(),
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

    public function show(FeatureFlag $featureFlag): Response
    {
        $rows = FeatureFlagOverride::query()
            ->where('feature_flag_id', $featureFlag->id)
            ->with(['tenant:id,slug,name', 'user:id,name,email'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'enabled' => (bool) $o->enabled,
                'expires_at' => $o->expires_at?->toIso8601String(),
                'reason' => $o->reason,
                'created_at' => $o->created_at?->toIso8601String(),
                'tenant' => $o->tenant ? [
                    'id' => $o->tenant->id,
                    'slug' => $o->tenant->slug,
                    'name' => $o->tenant->name,
                ] : null,
                'user' => $o->user ? [
                    'id' => $o->user->id,
                    'name' => $o->user->name,
                    'email' => $o->user->email,
                ] : null,
            ])->all();

        return Inertia::render('admin/feature-flags/show', [
            'featureFlag' => [
                'id' => $featureFlag->id,
                'key' => $featureFlag->key,
                'name' => $featureFlag->name,
                'description' => $featureFlag->description,
                'enabled_globally' => (bool) $featureFlag->enabled_globally,
                'rules' => $featureFlag->rules,
                'created_at' => $featureFlag->created_at?->toIso8601String(),
                'updated_at' => $featureFlag->updated_at?->toIso8601String(),
            ],
            'overrides' => $rows,
        ]);
    }

    public function store(FeatureFlagStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        FeatureFlag::create([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'enabled_globally' => (bool) ($validated['enabled_globally'] ?? false),
            'rules' => $validated['rules'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature flag created.')]);

        return to_route('admin.feature-flags.index');
    }

    public function update(FeatureFlagUpdateRequest $request, FeatureFlag $featureFlag): RedirectResponse
    {
        $validated = $request->validated();

        $featureFlag->forceFill([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'enabled_globally' => (bool) ($validated['enabled_globally'] ?? false),
            'rules' => $validated['rules'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature flag updated.')]);

        return back();
    }

    public function destroy(FeatureFlagDestroyRequest $request, FeatureFlag $featureFlag): RedirectResponse
    {
        unset($request);
        $featureFlag->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Feature flag deleted.')]);

        return to_route('admin.feature-flags.index');
    }
}
