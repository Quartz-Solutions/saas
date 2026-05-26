<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MagicLinkRequestRequest;
use App\Support\Auth\LoginHistoryRecorder;
use App\Support\Auth\MagicLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MagicLinkController extends Controller
{
    public function __construct(
        protected MagicLinkService $service,
        protected LoginHistoryRecorder $history,
    ) {}

    /**
     * Show the request-a-magic-link page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/magic-link');
    }

    /**
     * Accept an email + issue a signed magic-link URL by mail.
     */
    public function store(MagicLinkRequestRequest $request): RedirectResponse
    {
        $this->service->issue($request->string('email')->toString(), $request);

        // Always succeed visibly so we don't leak account existence.
        return back()->with('status', __('If that email matches an account, a sign-in link is on its way.'));
    }

    /**
     * Consume the magic-link token from the signed URL and sign the user in.
     */
    public function consume(Request $request, string $token): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            $this->history->record(null, 'failed', 'magic_link', $request, null, ['reason' => 'invalid_signature']);

            return redirect()->route('login')->withErrors([
                'email' => __('That sign-in link is invalid or has expired.'),
            ]);
        }

        $user = $this->service->consume($token, $request);

        if ($user === null) {
            $this->history->record(null, 'failed', 'magic_link', $request, null, ['reason' => 'token_unknown_or_consumed']);

            return redirect()->route('login')->withErrors([
                'email' => __('That sign-in link is invalid or has expired.'),
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $this->history->record($user, 'succeeded', 'magic_link', $request);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended(route('dashboard'));
    }
}
