<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class TenantDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : $this->route('tenant');
        $user = $this->user();

        return $tenant instanceof Tenant
            && $user !== null
            && $tenant->owner_id === $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
