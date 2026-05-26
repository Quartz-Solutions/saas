<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialEndingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public User $user, public array $context = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your trial is ending soon'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trial-ending',
            with: [
                'user' => $this->user,
                'planName' => $this->context['planName'] ?? null,
                'daysRemaining' => $this->context['daysRemaining'] ?? null,
                'endsAt' => $this->context['endsAt'] ?? null,
                'billingUrl' => $this->context['billingUrl'] ?? null,
            ],
        );
    }
}
