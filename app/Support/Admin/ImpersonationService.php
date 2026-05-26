<?php

namespace App\Support\Admin;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Canonical service for staff "log in as user" flows.
 *
 * Per CLAUDE.md "service-layer single seam": every impersonation start/stop
 * routes through this class. Controllers must not poke the session directly.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public const LOG_SESSION_KEY = 'impersonation_log_id';

    /**
     * Start impersonating $target. The current user id is stashed in the
     * session so it can be restored later. An ImpersonationLog row is
     * created so we have an audit trail.
     */
    public function start(User $impersonator, User $target, ?Request $request = null, ?string $reason = null): ImpersonationLog
    {
        if ($impersonator->id === $target->id) {
            throw new RuntimeException('Cannot impersonate yourself.');
        }

        $log = ImpersonationLog::create([
            'impersonator_id' => $impersonator->id,
            'impersonated_id' => $target->id,
            'tenant_id' => null,
            'started_at' => now(),
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 512),
            'reason' => $reason,
        ]);

        session()->put(self::SESSION_KEY, $impersonator->id);
        session()->put(self::LOG_SESSION_KEY, $log->id);

        Auth::loginUsingId($target->id);

        return $log;
    }

    /**
     * Stop impersonating and restore the original user. No-op if no
     * impersonation is in progress.
     */
    public function stop(?Request $request = null): ?User
    {
        unset($request);

        $impersonatorId = session()->pull(self::SESSION_KEY);
        $logId = session()->pull(self::LOG_SESSION_KEY);

        if (! $impersonatorId) {
            return null;
        }

        if ($logId) {
            ImpersonationLog::whereKey($logId)->update(['ended_at' => now()]);
        }

        $impersonator = User::find($impersonatorId);

        if ($impersonator === null) {
            Auth::logout();

            return null;
        }

        Auth::loginUsingId($impersonator->id);

        return $impersonator;
    }

    /**
     * True when the current session has an impersonator stashed.
     */
    public function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * The original user id, if any.
     */
    public function impersonatorId(): ?int
    {
        $id = session()->get(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }
}
