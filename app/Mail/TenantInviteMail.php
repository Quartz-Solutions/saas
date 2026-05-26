<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TenantInvitation $invitation) {}

    public function envelope(): Envelope
    {
        $tenant = $this->invitation->tenant;

        return new Envelope(
            subject: __('You\'re invited to :tenant', ['tenant' => $tenant?->name ?? 'a workspace']),
        );
    }

    public function content(): Content
    {
        $tenant = $this->invitation->tenant;
        $inviter = $this->invitation->inviter;

        return new Content(
            markdown: 'mail.tenant-invite',
            with: [
                'tenantName' => $tenant?->name ?? 'a workspace',
                'inviterName' => $inviter?->name ?? 'A teammate',
                'role' => $this->invitation->role,
                'acceptUrl' => url(route('account.invitations.accept', ['token' => $this->invitation->token])),
                'expiresAt' => optional($this->invitation->expires_at)->toDayDateTimeString() ?? '—',
            ],
        );
    }
}
