<?php

namespace App\Http\Requests\Admin;

class PaymentRefundRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reasons = array_keys((array) config('billing-credit-reasons.refund', []));

        return [
            'amount_cents' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            'reason' => ['required', 'string', 'in:'.implode(',', $reasons)],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
