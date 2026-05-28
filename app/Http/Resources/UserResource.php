<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $token = $this->whenLoaded('currentAccessToken');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'avatar_path' => $this->avatar_path,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'token' => $this->when(
                $request->bearerToken() !== null,
                fn () => $this->shapeToken($request->user()?->currentAccessToken()),
            ),
        ];
    }

    private function shapeToken($token): ?array
    {
        if ($token === null) {
            return null;
        }

        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => (array) $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }
}
