<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\RecoveryCodeReplaced;

/**
 * On every 2FA recovery-code consumption, fire the `two_factor_recovery`
 * notification so the user is alerted (security event).
 */
class NotifyTwoFactorRecoveryUsed
{
    public function __construct(private readonly Request $request) {}

    public function handle(RecoveryCodeReplaced $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        app(NotificationDispatcher::class)->send($event->user, 'two_factor_recovery', [
            'ip' => (string) ($this->request->ip() ?? ''),
            'userAgent' => (string) ($this->request->userAgent() ?? ''),
            'when' => now()->toDayDateTimeString(),
        ]);
    }
}
