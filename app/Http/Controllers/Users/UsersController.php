<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserDestroyRequest;
use App\Http\Requests\Users\UserStoreRequest;
use App\Http\Requests\Users\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    private const ALLOWED_SORT = ['id', 'name', 'email', 'email_verified_at', 'created_at'];

    private const PER_PAGE = 15;

    /**
     * Paginated listing with search, filters and sort.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $query = User::query()->select([
            'id', 'name', 'email', 'email_verified_at',
            'two_factor_confirmed_at', 'created_at',
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['verified'])) {
            $filters['verified'] === 'yes'
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at');
        }

        if (! empty($filters['created_at']) && str_contains($filters['created_at'], '|')) {
            [$from, $to] = explode('|', $filters['created_at'], 2);
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

        return Inertia::render('users/index', [
            'users' => [
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

    public function store(UserStoreRequest $request, string $tenantSlug): RedirectResponse
    {
        User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => $request->string('password'),
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('tenants.users.index', ['tenantSlug' => $tenantSlug]);
    }

    public function update(UserUpdateRequest $request, string $tenantSlug, User $user): RedirectResponse
    {
        unset($tenantSlug);
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    public function destroy(UserDestroyRequest $request, string $tenantSlug, User $user): RedirectResponse
    {
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('tenants.users.index', ['tenantSlug' => $tenantSlug]);
    }
}
