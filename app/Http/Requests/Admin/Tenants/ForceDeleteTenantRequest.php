<?php

namespace App\Http\Requests\Admin\Tenants;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Tenant;

class ForceDeleteTenantRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirm_slug' => ['required', 'string'],
        ];
    }

    /**
     * Require the admin to retype the slug exactly. Protects against
     * accidental purge of the wrong tenant from the dropdown.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $tenant = $this->route('tenant') ?? $this->route('tenantId');
            $expected = is_object($tenant) ? $tenant->slug : null;
            // Fall back to refetching by route param if the model wasn't bound.
            if ($expected === null && is_numeric($tenant)) {
                $row = Tenant::withTrashed()->find($tenant);
                $expected = $row?->slug;
            }
            if ($expected !== null && $this->input('confirm_slug') !== $expected) {
                $v->errors()->add('confirm_slug', 'Slug confirmation does not match.');
            }
        });
    }
}
