<?php

namespace App\Http\Middleware;

use App\Support\Theme\ThemeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the active theme's compiled stylesheet URL to the blade so
 * app.blade.php can <link> it after the build-time app.css (overriding the
 * default tokens via the cascade — no front-end rebuild). Sibling to
 * HandleAppearance; the .dark toggle is untouched.
 */
class InjectActiveTheme
{
    public function __construct(private readonly ThemeService $themes) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('activeThemeCss', $this->themes->activeCssUrl());

        return $next($request);
    }
}
