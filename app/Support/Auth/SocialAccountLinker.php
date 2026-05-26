<?php

namespace App\Support\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Single canonical seam for linking Socialite identities to local users.
 *
 * Account-linking rules:
 *   1. If a SocialAccount already exists for (provider, provider_user_id) -> return its user
 *   2. Else if a User exists by email -> attach a new SocialAccount (no dup user)
 *   3. Else create a new User (random password, email pre-verified since the
 *      provider has already vouched for the address)
 */
class SocialAccountLinker
{
    public function resolve(string $provider, SocialiteUser $remoteUser): User
    {
        $existing = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $remoteUser->getId())
            ->first();

        if ($existing !== null) {
            $this->refresh($existing, $remoteUser);

            return $existing->user;
        }

        $email = $remoteUser->getEmail();
        $user = $email !== null
            ? User::query()->where('email', $email)->first()
            : null;

        if ($user === null) {
            $user = User::query()->create([
                'name' => $remoteUser->getName() ?? $remoteUser->getNickname() ?? (string) Str::of($email ?? 'user')->before('@'),
                'email' => $email ?? sprintf('%s+%s@example.invalid', $provider, $remoteUser->getId()),
                'password' => Str::password(40),
            ]);

            // `email_verified_at` is not in $fillable on the User model — set
            // it explicitly so we trust the provider's vouch for the address.
            if ($email !== null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => (string) $remoteUser->getId(),
            'email' => $email,
            'name' => $remoteUser->getName(),
            'avatar_url' => $remoteUser->getAvatar(),
            'access_token' => $remoteUser->token ?? null,
            'refresh_token' => $remoteUser->refreshToken ?? null,
            'token_expires_at' => isset($remoteUser->expiresIn) && $remoteUser->expiresIn
                ? now()->addSeconds((int) $remoteUser->expiresIn)
                : null,
            'raw_payload' => method_exists($remoteUser, 'getRaw') ? $remoteUser->getRaw() : null,
        ]);

        return $user->fresh();
    }

    protected function refresh(SocialAccount $account, SocialiteUser $remoteUser): void
    {
        $account->fill([
            'email' => $remoteUser->getEmail() ?? $account->email,
            'name' => $remoteUser->getName() ?? $account->name,
            'avatar_url' => $remoteUser->getAvatar() ?? $account->avatar_url,
            'access_token' => $remoteUser->token ?? $account->access_token,
            'refresh_token' => $remoteUser->refreshToken ?? $account->refresh_token,
            'token_expires_at' => isset($remoteUser->expiresIn) && $remoteUser->expiresIn
                ? now()->addSeconds((int) $remoteUser->expiresIn)
                : $account->token_expires_at,
            'raw_payload' => method_exists($remoteUser, 'getRaw')
                ? $remoteUser->getRaw()
                : $account->raw_payload,
        ])->save();
    }
}
