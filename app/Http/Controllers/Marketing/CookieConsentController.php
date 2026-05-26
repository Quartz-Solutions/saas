<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class CookieConsentController extends Controller
{
    /** Cookie name used by the consent banner. */
    public const COOKIE_NAME = 'cookie_consent';

    /** Cookie lifetime in days (one year). */
    private const TTL_DAYS = 365;

    public function store(Request $request): RedirectResponse
    {
        $choice = $request->input('choice');

        if (! in_array($choice, ['accepted', 'rejected'], true)) {
            return back();
        }

        $cookie = Cookie::create(
            name: self::COOKIE_NAME,
            value: $choice,
            expire: now()->addDays(self::TTL_DAYS)->getTimestamp(),
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: false,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );

        return back()->withCookie($cookie);
    }
}
