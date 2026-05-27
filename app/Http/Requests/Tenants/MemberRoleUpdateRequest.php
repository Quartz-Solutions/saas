<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $this->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return false;
        }

        // Owner OR a tenant Admin can edit roles.
        if ($tenant->owner_id === $user->id) {
            return true;
        }

        setPermissionsTeamId($tenant->id);

        return $user->hasRole('Admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(TenantService::ROLES)],
        ];
    }
}
