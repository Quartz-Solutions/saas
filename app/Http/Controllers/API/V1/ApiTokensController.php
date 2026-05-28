<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Resources\ApiTokenResource;
use App\Support\Auth\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personal API token self-service. Listing + revocation only — minting a
 * token via the API is intentionally not exposed (a misbehaving integration
 * could lock itself out). Tokens must be minted via /settings/api-tokens.
 *
 * @group Auth + identity
 *
 * @authenticated
 */
class ApiTokensController extends ApiController
{
    public function __construct(private readonly ApiTokenService $service) {}

    /**
     * List the caller's tokens.
     *
     * Ability: `profile:read`.
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'profile:read');

        $user = $this->actor($request);
        $tokens = $user->tokens()->latest('id')->get();

        return ApiTokenResource::collection($tokens)
            ->response();
    }

    /**
     * Revoke a token by id. Refuses to revoke the calling token itself
     * (use the SPA to delete it). Ability: `profile:write`.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->requireAbility($request, 'profile:write');

        $user = $this->actor($request);
        $current = $user->currentAccessToken();

        if ($current !== null && (int) $current->id === $id) {
            return response()->json([
                'message' => 'Cannot revoke the calling token via the API. Use /settings/api-tokens.',
            ], 422);
        }

        $deleted = $this->service->revoke($user, $id);

        if (! $deleted) {
            return response()->json(['message' => "Token [{$id}] not found."], 404);
        }

        return response()->json([], 204);
    }
}
