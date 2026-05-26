<?php

namespace App\Http\Requests\Admin;

class SubscriptionCompMonthsRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reasons = array_keys((array) config('billing-credit-reasons.comp', []));

        return [
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'reason' => ['required', 'string', 'in:'.implode(',', $reasons)],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
