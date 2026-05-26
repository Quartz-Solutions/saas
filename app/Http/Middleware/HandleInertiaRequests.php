<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Marketing\CookieConsentController;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Fortify\Features;

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
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
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
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cookieConsent' => $request->cookie(CookieConsentController::COOKIE_NAME),
            'canRegister' => Features::enabled(Features::registration()),
        ];
    }
}
