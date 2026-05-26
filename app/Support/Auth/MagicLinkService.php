<?php

namespace App\Support\Auth;

use App\Models\MagicLoginToken;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Issues + consumes one-time magic-link tokens (15-min TTL).
 *
 * The token is generated as a random plain string, hashed (sha256) for at-rest
 * storage, and emailed to the user. Consumption requires a token whose hash
 * matches an unexpired, un-consumed record.
 */
class MagicLinkService
{
    public const TTL_MINUTES = 15;

    /**
     * Issue a magic-link to a user (if one exists for the email) and notify them.
     * We deliberately return the same shape whether a user exists or not so the
     * client cannot enumerate accounts.
     */
    public function issue(string $email, Request $request): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $plain = Str::random(48);

        MagicLoginToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'requested_ip' => $request->ip(),
            'requested_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $user->notify(new MagicLinkNotification($plain));
    }

    /**
     * Consume a plain token. Returns the matching user or null when the token
     * is unknown, expired, or already consumed.
     */
    public function consume(string $plain, Request $request): ?User
    {
        $token = MagicLoginToken::query()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($token === null) {
            return null;
        }

        $token->forceFill([
            'consumed_at' => now(),
            'consumed_ip' => $request->ip(),
        ])->save();

        return $token->user;
    }
}
