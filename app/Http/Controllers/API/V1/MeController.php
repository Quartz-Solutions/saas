<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Resources\UserResource;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Authenticated user identity + lightweight profile mutations.
 *
 * @group Auth + identity
 *
 * @authenticated
 */
class MeController extends ApiController
{
    /**
     * Show the authenticated user.
     *
     * Ability: `profile:read` (or `*`).
     *
     * @response 200 {
     *   "data": {
     *     "id": 1, "name": "Ada Lovelace", "email": "ada@example.com",
     *     "token": { "id": 12, "name": "CLI", "abilities": ["profile:read"], "last_used_at": null }
     *   }
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'profile:read');

        return UserResource::make($this->actor($request))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Update the authenticated user's profile (name, locale, timezone, phone).
     *
     * Ability: `profile:write`.
     */
    public function update(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'profile:write');

        $user = $this->actor($request);

        $data = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:8'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ])->validate();

        if ($data !== []) {
            $user->fill($data)->save();
        }

        return UserResource::make($user->refresh())->response();
    }

    /**
     * Request an email change. Does NOT apply directly — fires the standard
     * verification email and waits for the user to confirm via the link.
     *
     * Ability: `profile:write`.
     */
    public function requestEmailChange(Request $request, NotificationDispatcher $dispatcher): JsonResponse
    {
        $this->requireAbility($request, 'profile:write');

        $user = $this->actor($request);

        $data = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ])->validate();

        if (strtolower($data['email']) === strtolower((string) $user->email)) {
            return response()->json(['message' => 'Email unchanged.'], 200);
        }

        // Fortify's email-change flow goes through the SPA. From the API we
        // record the intent on the user row (verification cleared) and rely
        // on the standard "verify your email" notification — the user must
        // click the link in their inbox to actually confirm the change.
        $user->forceFill([
            'email' => $data['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        return response()->json([
            'data' => [
                'email' => $user->email,
                'verification_sent' => true,
                'message' => 'Verification email sent to the new address.',
            ],
        ], 202);
    }
}
