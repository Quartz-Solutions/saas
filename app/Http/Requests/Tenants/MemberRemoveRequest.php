<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class MemberRemoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $this->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return false;
        }

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
        return [];
    }
}
