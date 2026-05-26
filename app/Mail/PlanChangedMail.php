<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlanChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(public User $user, public array $context = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your plan has been updated'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.plan-changed',
            with: [
                'user' => $this->user,
                'fromPlan' => $this->context['fromPlan'] ?? null,
                'toPlan' => $this->context['toPlan'] ?? null,
                'effectiveAt' => $this->context['effectiveAt'] ?? null,
                'billingUrl' => $this->context['billingUrl'] ?? null,
            ],
        );
    }
}
