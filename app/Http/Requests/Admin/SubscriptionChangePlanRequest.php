<?php

namespace App\Http\Requests\Admin;

class SubscriptionChangePlanRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'prorate' => ['nullable', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
