<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');

        // Allow per-request locale override via ?locale=ar (or cookie) for
        // quick RTL spot-checks and i18n UAT. Falls back to app default.
        $locale = $request->query('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale', 'en');

        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
