<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckoutAbandonmentReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context  expects: planName, resumeUrl, cancelAt
     */
    public function __construct(public User $user, public array $context = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Finish signing up for :plan', [
                'plan' => $this->context['planName'] ?? 'your plan',
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.checkout-abandonment-reminder',
            with: [
                'user' => $this->user,
                'planName' => $this->context['planName'] ?? 'your plan',
                'resumeUrl' => $this->context['resumeUrl'] ?? config('app.url'),
                'cancelAt' => $this->context['cancelAt'] ?? null,
            ],
        );
    }
}
