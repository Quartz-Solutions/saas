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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cookieConsent' => $request->cookie(CookieConsentController::COOKIE_NAME),
            'canRegister' => Features::enabled(Features::registration()),
            // Surface one-shot flashed values so the front-end can pick them up
            // exactly once (e.g. plain-text API tokens & webhook secrets).
            'plain_text_token' => fn () => $request->session()->get('plain_text_token'),
            'webhook_secret' => fn () => $request->session()->get('webhook_secret'),
        ];
    }
}
