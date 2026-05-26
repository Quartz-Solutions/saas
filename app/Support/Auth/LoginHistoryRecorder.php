<?php

namespace App\Support\Auth;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records login attempts to the `login_history` table.
 *
 * Centralising this keeps the schema for Phase-8 login-alerts predictable —
 * once we add login alerts we hook off this writer rather than every caller.
 */
class LoginHistoryRecorder
{
    public function record(
        ?User $user,
        string $outcome,
        string $method,
        Request $request,
        ?string $email = null,
        array $context = [],
    ): void {
        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'email' => $email ?? $user?->email,
            'outcome' => $outcome,
            'method' => $method,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
