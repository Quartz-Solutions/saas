<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwner = $user !== null && $this->owner_id === $user->id;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'logo_path' => $this->logo_path,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'status' => $this->status,
            'role' => $isOwner ? 'Owner' : 'Member',
            'preferred_gateway' => $this->preferred_gateway,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
