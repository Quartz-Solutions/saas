<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class MagicLinkNotification extends Notification
{
    use Queueable;

    public function __construct(public string $plainToken) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'auth.magic-link.consume',
            now()->addMinutes(15),
            ['token' => $this->plainToken],
        );

        return (new MailMessage)
            ->subject(__('Your sign-in link'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name ?? 'there']))
            ->line(__('Click the button below to sign in. This link expires in 15 minutes and can be used only once.'))
            ->action(__('Sign in'), $url)
            ->line(__('If you did not request this, you can safely ignore this email.'));
    }
}
