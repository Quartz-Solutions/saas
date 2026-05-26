<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\View;

/**
 * Sent when the user logs in from an IP or user-agent different from
 * their last recorded LoginHistory row. Email channel only — in-app
 * notification bell will arrive with Phase 5.
 *
 * Uses Phase 6's `mail.login-alert` Blade template if it exists,
 * otherwise falls back to an inline MailMessage.
 */
class NewDeviceLoginAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $ip,
        public string $userAgent,
        public ?string $loggedInAt = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loggedInAt = $this->loggedInAt ?? now()->toDateTimeString();

        if (View::exists('mail.login-alert')) {
            return (new MailMessage)
                ->subject(__('New sign-in to your account'))
                ->markdown('mail.login-alert', [
                    'user' => $notifiable,
                    'ip' => $this->ip,
                    'userAgent' => $this->userAgent,
                    'loggedInAt' => $loggedInAt,
                ]);
        }

        return (new MailMessage)
            ->subject(__('New sign-in to your account'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name ?? 'there']))
            ->line(__('We noticed a sign-in to your account from a device or location we don\'t recognise.'))
            ->line(__('IP: :ip', ['ip' => $this->ip]))
            ->line(__('Device: :ua', ['ua' => $this->userAgent]))
            ->line(__('When: :at', ['at' => $loggedInAt]))
            ->line(__('If this was you, no action is needed. If not, reset your password and review your active sessions.'));
    }
}
