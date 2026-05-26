<?php

namespace App\Http\Requests\Admin;

class SubscriptionReactivateRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
