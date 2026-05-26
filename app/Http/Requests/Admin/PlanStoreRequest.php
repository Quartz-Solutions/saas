<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidFeaturesMap;

class PlanStoreRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:64', 'unique:plans,slug'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'billing_period' => ['required', 'in:day,week,month,year,one_time'],
            'billing_interval' => ['required', 'integer', 'min:1', 'max:24'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'features' => ['nullable', 'array', new ValidFeaturesMap],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
