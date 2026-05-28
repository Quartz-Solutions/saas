<?php

namespace App\Http\Resources;

use App\Models\TenantMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TenantMembership
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $tenant = $this->tenant;
        $isOwner = $tenant !== null && $user !== null && $tenant->owner_id === $user->id;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'name' => $user?->name,
            'email' => $user?->email,
            'role' => $isOwner
                ? 'Owner'
                : ($user?->getRoleNames()->first() ?? 'Member'),
            'is_owner' => $isOwner,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
