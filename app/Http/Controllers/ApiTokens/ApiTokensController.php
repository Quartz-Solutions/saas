<?php

namespace App\Http\Controllers\ApiTokens;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokens\ApiTokenDestroyRequest;
use App\Http\Requests\ApiTokens\ApiTokenStoreRequest;
use App\Support\Auth\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ApiTokensController extends Controller
{
    public function __construct(
        private readonly ApiTokenService $service,
    ) {}

    /**
     * Personal API tokens index — listing + create dialog.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/api-tokens', [
            'tokens' => $user !== null ? $this->service->listForUser($user) : [],
            'abilities' => array_values((array) config('api-abilities.abilities', [])),
        ]);
    }

    public function store(ApiTokenStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $abilities = (array) $request->input('abilities', []);
        if ($abilities === []) {
            $abilities = ['*'];
        }

        $expiresAt = null;
        if ($request->filled('expires_in_days')) {
            $expiresAt = now()->addDays((int) $request->integer('expires_in_days'));
        }

        try {
            $token = $this->service->create(
                $user,
                (string) $request->string('name'),
                $abilities,
                $expiresAt,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token created.')]);

        // Surface the plain-text token in the session ONCE so the create dialog
        // can reveal it. It is never readable again after this redirect.
        return back()->with('plain_text_token', [
            'id' => $token->accessToken->id,
            'name' => $token->accessToken->name,
            'plain_text' => $token->plainTextToken,
        ]);
    }

    public function destroy(ApiTokenDestroyRequest $request, int $token): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $this->service->revoke($user, $token);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }
}
