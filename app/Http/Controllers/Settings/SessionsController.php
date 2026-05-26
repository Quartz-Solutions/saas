<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Sessions\SessionDestroyAllRequest;
use App\Http\Requests\Settings\Sessions\SessionDestroyRequest;
use App\Support\Auth\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionsController extends Controller
{
    public function __construct(protected SessionManager $sessions) {}

    /**
     * Show the user's active sessions.
     */
    public function index(Request $request): Response
    {
        $currentId = $request->session()->getId();

        return Inertia::render('settings/sessions', [
            'sessions' => $this->sessions->listFor($request->user(), $currentId),
            'driverIsDatabase' => config('session.driver') === 'database',
        ]);
    }

    /**
     * Revoke a single session by id.
     */
    public function destroy(SessionDestroyRequest $request, string $session): RedirectResponse
    {
        // Never let a user revoke their own current session via this endpoint —
        // they should use the standard logout flow for that.
        if ($session === $request->session()->getId()) {
            return back()->withErrors(['session' => __('Use the logout button to end the current session.')]);
        }

        $this->sessions->revoke($request->user(), $session);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session revoked.')]);

        return back();
    }

    /**
     * Revoke every other session, keeping the current one alive.
     */
    public function destroyAll(SessionDestroyAllRequest $request): RedirectResponse
    {
        $currentId = $request->session()->getId();
        $this->sessions->revokeAllExcept($request->user(), $currentId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All other sessions revoked.')]);

        return back();
    }
}
