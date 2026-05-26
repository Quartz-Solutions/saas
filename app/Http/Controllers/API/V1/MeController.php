<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * GET /api/v1/me
 *
 * Returns the authenticated user + token metadata. Requires ability
 * `profile:read` (or `*`).
 *
 * @group Auth
 *
 * @authenticated
 */
class MeController extends Controller
{
    /**
     * Show the current authenticated user.
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Ada Lovelace",
     *     "email": "ada@example.com",
     *     "token": { "id": 12, "name": "CLI", "abilities": ["profile:read"], "last_used_at": null }
     *   }
     * }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        abort_unless(
            $user->tokenCan('profile:read') || $user->tokenCan('*'),
            403,
            'Token lacks profile:read ability.',
        );

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'token' => $token ? [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => (array) $token->abilities,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }
}
