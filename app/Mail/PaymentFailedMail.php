<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public User $user, public array $context = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Action required: we couldn\'t process your payment'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-failed',
            with: [
                'user' => $this->user,
                'planName' => $this->context['planName'] ?? null,
                'amount' => $this->context['amount'] ?? null,
                'reason' => $this->context['reason'] ?? null,
                'nextRetryAt' => $this->context['nextRetryAt'] ?? null,
                'billingUrl' => $this->context['billingUrl'] ?? null,
            ],
        );
    }
}
