<?php

namespace App\Events;

use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by TenantService::acceptInvitation() — see Phase 2 hooks.
 * Mapped to outbound webhook event `tenant.member.joined`.
 */
class TenantMemberJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public TenantMembership $membership,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toWebhookPayload(): array
    {
        return [
            'membership_id' => $this->membership->id,
            'user_id' => $this->membership->user_id,
            'joined_at' => $this->membership->joined_at?->toIso8601String(),
        ];
    }
}
