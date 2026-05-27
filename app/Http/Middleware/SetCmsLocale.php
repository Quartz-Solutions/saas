<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the visitor's UI locale and persists it as a cookie. Looks
 * (in priority order) at:
 *   1. ?lang=xx query parameter (one-shot, also persists)
 *   2. `cms_locale` cookie
 *   3. Accept-Language header (first match against supported set)
 *   4. config('app.locale') fallback
 *
 * Supported locales come from config('cms.locales') — `en` always
 * counts as the canonical fallback even if it isn't in the list.
 */
class SetCmsLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = $this->supported();
        $locale = null;
        $persist = false;

        $candidate = (string) $request->query('lang', '');
        if ($candidate !== '' && in_array($candidate, $supported, true)) {
            $locale = $candidate;
            $persist = true;
        }

        if ($locale === null) {
            $cookie = $request->cookie('cms_locale');
            if (is_string($cookie) && in_array($cookie, $supported, true)) {
                $locale = $cookie;
            }
        }

        if ($locale === null) {
            $accept = (string) $request->headers->get('Accept-Language', '');
            foreach (preg_split('/[\s,]+/', $accept) ?: [] as $tag) {
                $short = strtolower(substr($tag, 0, 2));
                if ($short !== '' && in_array($short, $supported, true)) {
                    $locale = $short;
                    break;
                }
            }
        }

        if ($locale === null) {
            $locale = (string) config('app.locale', 'en');
        }

        app()->setLocale($locale);

        $response = $next($request);

        if ($persist) {
            // Long-lived cookie so subsequent requests resolve the same
            // locale without ?lang=. cms_locale is excluded from encryption
            // in bootstrap/app.php so the value stays a plain string.
            $cookie = Cookie::create(
                'cms_locale',
                $locale,
                now()->addYear()->getTimestamp(),
                '/',
                null,
                false,
                false,
                false,
                Cookie::SAMESITE_LAX,
            );
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    /**
     * @return array<int, string>
     */
    private function supported(): array
    {
        $list = (array) config('cms.locales', ['en', 'ar', 'fr', 'es', 'de']);

        return array_map('strval', array_values($list));
    }
}
