<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->resolveTenant();
        $user = $this->user();

        return $tenant !== null
            && $user !== null
            && ($tenant->owner_id === $user->id || $user->hasRole('Owner') || $user->hasRole('Admin'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = $this->resolveTenant();
        $id = $tenant?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Tenant::class, 'slug')->ignore($id),
            ],
            'timezone' => ['required', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', 'string', 'max:8'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    private function resolveTenant(): ?Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        $route = $this->route('tenant');

        return $route instanceof Tenant ? $route : null;
    }
}
