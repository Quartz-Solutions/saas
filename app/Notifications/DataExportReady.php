<?php

namespace App\Notifications;

use App\Models\DataExportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent when GenerateDataExport finishes building the user's archive.
 * Contains a 24-hour signed download URL.
 */
class DataExportReady extends Notification
{
    use Queueable;

    public function __construct(public DataExportRequest $export) {}

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
            'privacy.exports.download',
            now()->addHours(24),
            ['export' => $this->export->id],
        );

        return (new MailMessage)
            ->subject(__('Your data export is ready'))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name ?? 'there']))
            ->line(__('Your data export has finished. The download link below expires in 24 hours.'))
            ->action(__('Download export'), $url)
            ->line(__('If you did not request this export, please contact support.'));
    }
}
