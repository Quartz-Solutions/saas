<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use Illuminate\Foundation\Http\FormRequest;

class TenantInvitationDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : $this->route('tenant');
        $invitation = $this->route('invitation');
        $user = $this->user();

        return $tenant instanceof Tenant
            && $invitation instanceof TenantInvitation
            && $invitation->tenant_id === $tenant->id
            && $user !== null
            && ($tenant->owner_id === $user->id
                || $user->hasRole('Owner')
                || $user->hasRole('Admin'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
