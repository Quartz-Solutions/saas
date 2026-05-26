<?php

namespace App\Events;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by TenantService::invite() — see Phase 2 hooks.
 * Mapped to outbound webhook event `tenant.member.invited`.
 */
class TenantMemberInvited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public TenantInvitation $invitation,
    ) {}

    /**
     * Payload shipped to subscribers.
     *
     * @return array<string, mixed>
     */
    public function toWebhookPayload(): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'email' => $this->invitation->email,
            'role' => $this->invitation->role,
            'invited_by_id' => $this->invitation->invited_by_id,
            'expires_at' => $this->invitation->expires_at?->toIso8601String(),
        ];
    }
}
