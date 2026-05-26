<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use App\Notifications\NewDeviceLoginAlert;
use App\Support\Auth\LoginHistoryRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * On every successful Auth\Events\Login, compare the request IP + UA
 * against the LATEST LoginHistory row for the user. If either differs
 * (i.e. it's a new device), send a NewDeviceLoginAlert email.
 *
 * After deciding, defer to LoginHistoryRecorder to write the new row —
 * which means the "latest" row we just compared against is the previous
 * one (correct), not the one we just wrote.
 *
 * Magic-link + social login flows already write their own
 * LoginHistory entries via the recorder; this listener handles the
 * password + 2FA paths Fortify drives through Auth::login().
 */
class AlertOnNewDeviceLogin
{
    public function __construct(
        protected LoginHistoryRecorder $recorder,
        protected Request $request,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $ip = (string) ($this->request->ip() ?? '');
        $ua = (string) ($this->request->userAgent() ?? '');

        $latest = LoginHistory::query()
            ->where('user_id', $user->id)
            ->where('outcome', 'succeeded')
            ->orderByDesc('created_at')
            ->first();

        $isNewDevice = $latest === null
            ? false // first ever login — not "new device" by definition
            : ($latest->ip !== $ip || $latest->user_agent !== $ua);

        if ($isNewDevice) {
            $user->notify(new NewDeviceLoginAlert(
                ip: $ip,
                userAgent: $ua !== '' ? $ua : 'unknown',
            ));
        }

        $this->recorder->record(
            user: $user,
            outcome: 'succeeded',
            method: 'password',
            request: $this->request,
        );
    }
}
