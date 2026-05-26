<?php

namespace App\Http\Requests\Admin;

class InvoiceManualPaymentRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $methods = array_keys((array) config('billing-credit-reasons.manual_payment_method', []));

        return [
            'amount_cents' => ['required', 'integer', 'min:1', 'max:99999999'],
            'method' => ['required', 'string', 'in:'.implode(',', $methods)],
            'reference' => ['nullable', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
