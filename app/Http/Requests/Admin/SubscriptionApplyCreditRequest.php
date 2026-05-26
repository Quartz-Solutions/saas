<?php

namespace App\Http\Requests\Admin;

class SubscriptionApplyCreditRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reasons = array_keys((array) config('billing-credit-reasons.credit', []));

        return [
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999'],
            'reason' => ['required', 'string', 'in:'.implode(',', $reasons)],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
