<?php

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class FeatureFlagOverrideStoreRequest extends AdminFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', Rule::exists(Tenant::class, 'id')],
            'user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'enabled' => ['required', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $tenant = $this->input('tenant_id');
            $user = $this->input('user_id');
            if (empty($tenant) && empty($user)) {
                $v->errors()->add('tenant_id', __('Either tenant or user must be specified.'));
            }
        });
    }
}
