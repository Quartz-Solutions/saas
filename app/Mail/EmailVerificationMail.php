<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $verifyUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Verify your email address'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.email-verification',
            with: [
                'user' => $this->user,
                'verifyUrl' => $this->verifyUrl,
            ],
        );
    }
}
