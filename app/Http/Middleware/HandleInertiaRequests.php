<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Marketing\CookieConsentController;
use App\Models\User;
use App\Support\Admin\ImpersonationService;
use App\Support\Cms\GlobalsService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Fortify\Features;
use Spatie\Permission\PermissionRegistrar;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $impersonator = $this->resolveImpersonator();
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isSuperAdmin' => $user !== null && $this->isSuperAdmin($user),
                'unreadNotificationsCount' => fn () => $user
                    ? (int) $user->unreadNotifications()->count()
                    : 0,
                'notifications' => fn () => $user
                    ? $user->notifications()
                        ->latest()
                        ->limit(10)
                        ->get()
                        ->map(fn ($n) => [
                            'id' => $n->id,
                            'type' => $n->type,
                            'data' => $n->data,
                            'read_at' => optional($n->read_at)?->toIso8601String(),
                            'created_at' => optional($n->created_at)?->toIso8601String(),
                            'created_at_human' => optional($n->created_at)?->diffForHumans(),
                        ])
                        ->all()
                    : [],
                // Lightweight list of tenants the current user belongs to —
                // consumed by the sidebar tenant-switcher. Kept tiny so it's
                // safe to include on every Inertia response.
                'tenants' => fn () => $user
                    ? $user->tenants()
                        ->orderBy('name')
                        ->get(['tenants.id', 'tenants.slug', 'tenants.name'])
                        ->map(fn ($t) => [
                            'id' => $t->id,
                            'slug' => $t->slug,
                            'name' => $t->name,
                        ])
                        ->values()
                        ->all()
                    : [],
            ],
            'impersonation' => $impersonator === null ? null : [
                'impersonator' => [
                    'id' => $impersonator->id,
                    'name' => $impersonator->name,
                    'email' => $impersonator->email,
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cookieConsent' => $request->cookie(CookieConsentController::COOKIE_NAME),
            'canRegister' => Features::enabled(Features::registration()),
            // Surface one-shot flashed values so the front-end can pick them up
            // exactly once (e.g. plain-text API tokens & webhook secrets).
            'plain_text_token' => fn () => $request->session()->get('plain_text_token'),
            'webhook_secret' => fn () => $request->session()->get('webhook_secret'),
            // CMS globals bundle — brand / nav / footer / announcement / etc.
            // Lazy via closure so the cache only fires when the page reads it.
            'cmsGlobals' => fn () => app(GlobalsService::class)->forPublic(),
            // Current + supported locales for the public locale picker.
            // Lazy — resolved at response time so the locale-resolving
            // route middleware (cms.locale) has already run.
            'i18n' => fn () => [
                'locale' => app()->getLocale(),
                'locales' => (array) config('cms.locales', ['en']),
            ],
        ];
    }

    private function resolveImpersonator(): ?User
    {
        $service = app(ImpersonationService::class);

        if (! $service->isImpersonating()) {
            return null;
        }

        $id = $service->impersonatorId();

        return $id === null ? null : User::find($id);
    }

    private function isSuperAdmin(User $user): bool
    {
        // Spatie's hasRole respects whatever team is currently set on the
        // request. Look up the Super Admin role explicitly against null
        // (global) team so we don't false-negative inside a tenant scope.
        $previousTeam = app(PermissionRegistrar::class)->getPermissionsTeamId();
        try {
            setPermissionsTeamId(null);

            return $user->hasRole('Super Admin');
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }
}
