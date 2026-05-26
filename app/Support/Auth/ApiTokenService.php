<?php

namespace App\Support\Auth;

use App\Models\User;
use InvalidArgumentException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Canonical service for personal API token lifecycle.
 *
 * Per CLAUDE.md "service-layer single seam": every cross-cutting write
 * goes through this class. Controllers never call $user->createToken()
 * directly.
 */
class ApiTokenService
{
    /**
     * Mint a new personal access token for the user.
     *
     * @param  array<int, string>  $abilities
     */
    public function create(User $user, string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Token name is required.');
        }

        $allowed = $this->allowedAbilityKeys();
        $abilities = array_values(array_unique($abilities));

        if ($abilities === []) {
            $abilities = ['*'];
        }

        foreach ($abilities as $ability) {
            if (! in_array($ability, $allowed, true)) {
                throw new InvalidArgumentException("Unknown ability: {$ability}");
            }
        }

        return $user->createToken($name, $abilities, $expiresAt);
    }

    /**
     * Revoke (delete) one of the user's tokens.
     */
    public function revoke(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->whereKey($tokenId)->first();

        if ($token === null) {
            return false;
        }

        $token->delete();

        return true;
    }

    /**
     * Revoke all of the user's tokens.
     */
    public function revokeAll(User $user): int
    {
        return (int) $user->tokens()->delete();
    }

    /**
     * @return array<int, string>
     */
    public function allowedAbilityKeys(): array
    {
        return array_map(
            static fn (array $a): string => (string) $a['key'],
            (array) config('api-abilities.abilities', []),
        );
    }

    /**
     * Shape token rows for the API tokens index page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(User $user): array
    {
        return $user->tokens()
            ->latest('id')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => (array) $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
