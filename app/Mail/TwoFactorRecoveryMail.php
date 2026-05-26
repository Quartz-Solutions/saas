<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorRecoveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public User $user, public array $context = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('A recovery code was used on your account'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.two-factor-recovery',
            with: [
                'user' => $this->user,
                'when' => $this->context['when'] ?? now()->toDayDateTimeString(),
                'ip' => $this->context['ip'] ?? '—',
                'remaining' => $this->context['remaining'] ?? 0,
                'securityUrl' => $this->context['securityUrl'] ?? url('/settings/security'),
            ],
        );
    }
}
