<?php

namespace App\Http\Requests\Admin;

use App\Models\FeatureFlag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class FeatureFlagUpdateRequest extends AdminFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $flag = $this->route('feature_flag');
        $id = is_object($flag) ? $flag->id : $flag;

        return [
            'key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique(FeatureFlag::class, 'key')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled_globally' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'array'],
        ];
    }
}
