<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public User $user, public array $context = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :app receipt', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-receipt',
            with: [
                'user' => $this->user,
                'description' => $this->context['description'] ?? null,
                'amount' => $this->context['amount'] ?? null,
                'invoiceNumber' => $this->context['invoiceNumber'] ?? null,
                'paidAt' => $this->context['paidAt'] ?? null,
                'invoiceUrl' => $this->context['invoiceUrl'] ?? null,
            ],
        );
    }
}
