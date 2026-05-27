<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImpersonateUserRequest;
use App\Http\Requests\Admin\Users\RevokeSuperAdminRequest;
use App\Http\Requests\Admin\Users\SuspendUserRequest;
use App\Http\Requests\Admin\Users\UserActionRequest;
use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\NotificationPreference;
use App\Models\SocialAccount;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Admin\ImpersonationService;
use App\Support\Admin\UserAdminService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UsersAdminController extends Controller
{
    private const ALLOWED_SORT = ['id', 'name', 'email', 'created_at', 'last_login_at'];

    private const PER_PAGE = 25;

    private const SUMMARY_LIMIT = 10;

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

        $query = User::query()
            ->withCount('memberships')
            ->with('roles:id,name');

        if ($view !== '') {
            $this->applyView($query, $view);
        } else {
            if (isset($filters['verified'])) {
                $filters['verified'] === '1'
                    ? $query->whereNotNull('email_verified_at')
                    : $query->whereNull('email_verified_at');
            }

            if (isset($filters['two_factor'])) {
                $filters['two_factor'] === '1'
                    ? $query->whereNotNull('two_factor_confirmed_at')
                    : $query->whereNull('two_factor_confirmed_at');
            }

            if (isset($filters['suspended'])) {
                $filters['suspended'] === '1'
                    ? $query->whereNotNull('suspended_at')
                    : $query->whereNull('suspended_at');
            }

            if (! empty($filters['role'])) {
                $role = (string) $filters['role'];
                $query->whereHas('roles', fn ($q) => $q->where('name', $role));
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        $rows = collect($paginator->items())->map(function (User $u) {
            $roles = $u->roles->pluck('name')->all();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_path' => $u->avatar_path,
                'verified' => $u->email_verified_at !== null,
                'two_factor' => $u->two_factor_confirmed_at !== null,
                'suspended' => $u->suspended_at !== null,
                'roles' => $roles,
                'is_super_admin' => in_array('Super Admin', $roles, true),
                'tenants_count' => (int) $u->memberships_count,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'created_at' => $u->created_at?->toIso8601String(),
            ];
        })->all();

        return Inertia::render('admin/users/index', [
            'users' => [
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

    public function show(User $user): Response
    {
        $user->loadCount(['memberships', 'tokens']);
        $user->load(['roles:id,name', 'memberships.tenant:id,slug,name', 'currentTenant:id,slug,name']);

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->select(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->orderByDesc('last_activity')
            ->limit(self::SUMMARY_LIMIT)
            ->get();

        $loginHistory = LoginHistory::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get();

        $auditLog = AuditLog::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($q) use ($user) {
                        $q->where('auditable_type', User::class)
                            ->where('auditable_id', $user->id);
                    });
            })
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get();

        $webhookEvents = WebhookEvent::query()
            ->where('tenant_id', $user->current_tenant_id)
            ->latest('id')
            ->limit(self::SUMMARY_LIMIT)
            ->get(['id', 'gateway', 'event_type', 'status', 'created_at']);

        $tokens = $user->tokens()
            ->select(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
            ->orderByDesc('id')
            ->get();

        $socialAccounts = SocialAccount::where('user_id', $user->id)->get();
        $notificationPrefs = NotificationPreference::where('user_id', $user->id)->get();

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_path' => $user->avatar_path,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
                'last_login_ip' => $user->last_login_ip,
                'suspended_at' => $user->suspended_at?->toIso8601String(),
                'force_password_reset' => (bool) $user->force_password_reset,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
                'has_recovery_codes' => $user->two_factor_recovery_codes !== null,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'created_at' => $user->created_at?->toIso8601String(),
                'sessions_count' => $sessions->count(),
                'tokens_count' => (int) $user->tokens_count,
                'is_super_admin' => $user->hasRole('Super Admin'),
                'current_tenant' => $user->currentTenant ? [
                    'id' => $user->currentTenant->id,
                    'slug' => $user->currentTenant->slug,
                    'name' => $user->currentTenant->name,
                ] : null,
            ],
            'memberships' => $user->memberships->map(fn (TenantMembership $m) => [
                'membership_id' => $m->id,
                'tenant' => $m->tenant ? [
                    'id' => $m->tenant->id,
                    'slug' => $m->tenant->slug,
                    'name' => $m->tenant->name,
                ] : null,
                'is_owner' => $m->tenant?->owner_id === $user->id,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ])->all(),
            'sessions' => $sessions->map(fn ($s) => [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'last_activity' => $s->last_activity
                    ? Carbon::createFromTimestamp($s->last_activity)->toIso8601String()
                    : null,
            ])->all(),
            'loginHistory' => $loginHistory->map(fn (LoginHistory $l) => [
                'id' => $l->id,
                'outcome' => $l->outcome,
                'method' => $l->method,
                'ip' => $l->ip,
                'created_at' => $l->created_at?->toIso8601String(),
            ])->all(),
            'auditLog' => $auditLog->map(fn (AuditLog $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'auditable_type' => $a->auditable_type,
                'auditable_id' => $a->auditable_id,
                'new_values' => $a->new_values,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->all(),
            'webhookEvents' => $webhookEvents->map(fn (WebhookEvent $w) => [
                'id' => $w->id,
                'gateway' => $w->gateway,
                'event_type' => $w->event_type,
                'status' => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
            ])->all(),
            'tokens' => $tokens->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ])->all(),
            'socialAccounts' => $socialAccounts->map(fn (SocialAccount $s) => [
                'id' => $s->id,
                'provider' => $s->provider,
                'email' => $s->email,
                'name' => $s->name,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all(),
            'notificationPreferences' => $notificationPrefs->map(fn (NotificationPreference $n) => [
                'event_type' => $n->event_type,
                'channel' => $n->channel,
                'enabled' => (bool) $n->enabled,
            ])->all(),
        ]);
    }

    public function suspend(SuspendUserRequest $request, User $user): RedirectResponse
    {
        app(UserAdminService::class)->suspend(
            $user,
            $request->user(),
            $request->input('reason'),
            $request,
        );

        return $this->flashBack(__('User suspended.'));
    }

    public function restore(UserActionRequest $request, User $user): RedirectResponse
    {
        app(UserAdminService::class)->restore($user, $request->user(), $request);

        return $this->flashBack(__('User restored.'));
    }

    public function resendVerification(UserActionRequest $request, User $user): RedirectResponse
    {
        app(UserAdminService::class)->resendVerification($user, $request->user(), $request);

        return $this->flashBack(__('Verification email sent.'));
    }

    public function forcePasswordReset(UserActionRequest $request, User $user): RedirectResponse
    {
        app(UserAdminService::class)->forcePasswordReset($user, $request->user(), $request);

        return $this->flashBack(__('Password reset link sent. User must reset on next login.'));
    }

    public function disableTwoFactor(UserActionRequest $request, User $user): RedirectResponse
    {
        app(UserAdminService::class)->disableTwoFactor($user, $request->user(), $request);

        return $this->flashBack(__('Two-factor authentication disabled.'));
    }

    public function revokeSessions(UserActionRequest $request, User $user): RedirectResponse
    {
        $count = app(UserAdminService::class)->revokeSessions($user, $request->user(), $request);

        return $this->flashBack(__(':n session(s) revoked.', ['n' => $count]));
    }

    public function revokeTokens(UserActionRequest $request, User $user): RedirectResponse
    {
        $count = app(UserAdminService::class)->revokeTokens($user, $request->user(), $request);

        return $this->flashBack(__(':n API token(s) revoked.', ['n' => $count]));
    }

    public function grantSuperAdmin(UserActionRequest $request, User $user): RedirectResponse
    {
        app(UserAdminService::class)->grantSuperAdmin($user, $request->user(), $request);

        return $this->flashBack(__('Super Admin role granted.'));
    }

    public function revokeSuperAdmin(RevokeSuperAdminRequest $request, User $user): RedirectResponse
    {
        app(UserAdminService::class)->revokeSuperAdmin($user, $request->user(), $request);

        return $this->flashBack(__('Super Admin role revoked.'));
    }

    public function impersonate(ImpersonateUserRequest $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return $this->flashBack(__('Cannot impersonate yourself.'), 'error');
        }

        app(ImpersonationService::class)->start(
            $request->user(),
            $user,
            $request,
            $request->input('reason'),
        );

        // Land on the user's current tenant if available; otherwise the public dashboard.
        if ($user->currentTenant) {
            return redirect()->route('tenants.dashboard', ['tenantSlug' => $user->currentTenant->slug]);
        }

        return redirect()->route('dashboard');
    }

    public function gdprExport(UserActionRequest $request, User $user): JsonResponse
    {
        $data = app(UserAdminService::class)->gdprExport($user, $request->user(), $request);

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="user-'.$user->id.'-export.json"',
        ]);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyView($query, string $view): void
    {
        match ($view) {
            'super_admins' => $query->whereHas('roles', fn ($q) => $q->where('name', 'Super Admin')),
            'unverified' => $query->whereNull('email_verified_at'),
            'suspended' => $query->whereNotNull('suspended_at'),
            'recently_created' => $query->where('created_at', '>=', now()->subDays(7)),
            default => null,
        };
    }

    /**
     * @return array<string, int>
     */
    private function viewCounts(): array
    {
        return [
            'all' => User::query()->count(),
            'super_admins' => User::query()->whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->count(),
            'unverified' => User::query()->whereNull('email_verified_at')->count(),
            'suspended' => User::query()->whereNotNull('suspended_at')->count(),
            'recently_created' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    private function flashBack(string $message, string $type = 'success'): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
