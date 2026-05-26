<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /checkout/start — kicks off a CheckoutSession.
 *
 * Three callers funnel through this:
 *   - /pricing                              (no tenant yet → user signs up first)
 *   - /t/{slug}/billing/plans               (existing tenant changes plan)
 *   - /get-started                          (combined sign-up + checkout)
 */
class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // anonymous users get sent to /get-started first
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_slug' => [
                'required', 'string',
                Rule::exists('plans', 'slug')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'tenant_id' => [
                'nullable', 'integer',
                Rule::exists('tenants', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
