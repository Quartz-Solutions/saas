<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks navigation for authenticated users whose `force_password_reset`
 * flag is set, bouncing them to the security settings page until they
 * change their password. The flag is set by:
 *   - Super Admin → user actions → "Force password reset"
 *   - (future) password breach detection
 *
 * Allow-listed routes that must still work while the flag is set:
 *   - The password-change form + submit (so the user can clear it)
 *   - Fortify's emailed reset flow (in case they prefer the email link)
 *   - Logout
 *   - Email verification (in case unverified)
 *
 * The User model clears the flag automatically when the password column
 * is updated (see User::booted).
 */
class EnforcePasswordReset
{
    /**
     * Route names that bypass the bounce so the user can actually act
     * on the reset prompt.
     */
    private const ALLOWED_ROUTES = [
        'security.edit',           // GET  /settings/security
        'user-password.update',    // PUT  /settings/password
        'password.request',        // GET  /forgot-password
        'password.email',          // POST /forgot-password
        'password.reset',          // GET  /reset-password/{token}
        'password.update',         // POST /reset-password
        'password.confirm',        // GET  /user/confirm-password
        'password.confirm.store',  // POST /user/confirm-password
        'password.confirmation',   // GET  /user/confirmed-password-status
        'logout',
        'verification.notice',
        'verification.send',
        'verification.verify',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! ($user->force_password_reset ?? false)) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if (in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        // Surface a banner on the redirect target so the user knows why
        // they were bounced.
        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => __('Please change your password before continuing.'),
        ]);

        return redirect()->route('security.edit');
    }
}
