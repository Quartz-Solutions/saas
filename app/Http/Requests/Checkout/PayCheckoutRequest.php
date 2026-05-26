<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /checkout/{session}/pay — user picked a gateway.
 *
 * Authorization happens in the controller: the session must belong to the
 * current user AND be in `pending` state.
 */
class PayCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gateway' => ['required', 'string', 'max:32'],
        ];
    }
}
