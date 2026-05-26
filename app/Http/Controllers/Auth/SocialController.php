<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\LoginHistoryRecorder;
use App\Support\Auth\SocialAccountLinker;
use App\Support\Auth\SocialProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialController extends Controller
{
    public function __construct(
        protected SocialProviderRegistry $registry,
        protected SocialAccountLinker $linker,
        protected LoginHistoryRecorder $history,
    ) {}

    /**
     * Redirect the user to the OAuth provider's consent screen.
     */
    public function redirect(string $provider): SymfonyRedirect
    {
        if (! $this->registry->has($provider)) {
            throw new NotFoundHttpException("Social provider [{$provider}] is not enabled.");
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the OAuth callback, link or create the local user, sign them in.
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        if (! $this->registry->has($provider)) {
            throw new NotFoundHttpException("Social provider [{$provider}] is not enabled.");
        }

        try {
            $remoteUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            $this->history->record(null, 'failed', "social_{$provider}", $request, null, [
                'reason' => 'oauth_exchange_failed',
            ]);

            return redirect()->route('login')->withErrors([
                'email' => __('We could not complete the :provider sign-in. Please try again.', ['provider' => ucfirst($provider)]),
            ]);
        }

        $user = $this->linker->resolve($provider, $remoteUser);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $this->history->record($user, 'succeeded', "social_{$provider}", $request);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended(route('dashboard'));
    }
}
