<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Support\Auth\SessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session revocation for the authenticated user.
 *
 * @group Auth + identity
 *
 * @authenticated
 */
class SessionsController extends ApiController
{
    public function __construct(private readonly SessionManager $sessions) {}

    /**
     * Revoke every session for the user (browser logins). Tokens are NOT
     * touched — use /me/api-tokens/{id} to revoke a token.
     *
     * Ability: `profile:write`.
     */
    public function revokeAll(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'profile:write');

        $user = $this->actor($request);
        $revoked = $this->sessions->revokeAllExcept($user, null);

        return response()->json([
            'data' => [
                'revoked_count' => $revoked,
            ],
        ]);
    }
}
