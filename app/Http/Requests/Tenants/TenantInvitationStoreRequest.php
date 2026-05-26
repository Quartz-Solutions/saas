<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Support\Tenancy\TenantService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantInvitationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : $this->route('tenant');
        $user = $this->user();

        return $tenant instanceof Tenant
            && $user !== null
            && ($tenant->owner_id === $user->id
                || $user->hasRole('Owner')
                || $user->hasRole('Admin'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(TenantService::ROLES)],
        ];
    }
}
