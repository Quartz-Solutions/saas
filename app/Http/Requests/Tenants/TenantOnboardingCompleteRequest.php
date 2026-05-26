<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TenantOnboardingCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->resolveTenant();
        $user = $this->user();

        return $tenant !== null
            && $user !== null
            && $tenant->owner_id === $user->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    private function resolveTenant(): ?Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
