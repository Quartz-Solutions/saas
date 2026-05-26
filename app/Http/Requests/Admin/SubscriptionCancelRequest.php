<?php

namespace App\Http\Requests\Admin;

class SubscriptionCancelRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reasons = array_keys((array) config('billing-credit-reasons.cancellation', []));

        return [
            'reason' => ['required', 'string', 'in:'.implode(',', $reasons)],
            'immediately' => ['nullable', 'boolean'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
