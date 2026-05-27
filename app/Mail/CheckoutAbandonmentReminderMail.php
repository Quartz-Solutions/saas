<?php

namespace App\Mail;

use App\Models\CheckoutSession;
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

    public function __construct(
        public User $user,
        public CheckoutSession $session,
        public string $resumeUrl,
    ) {}

    public function envelope(): Envelope
    {
        $planName = $this->session->plan?->name ?? 'a plan';

        return new Envelope(
            subject: __('Finish signing up for :plan', ['plan' => $planName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.checkout-abandonment-reminder',
            with: [
                'user' => $this->user,
                'session' => $this->session,
                'planName' => $this->session->plan?->name,
                'resumeUrl' => $this->resumeUrl,
            ],
        );
    }
}
