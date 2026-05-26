<?php

namespace App\Http\Requests\Onboarding;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Single-shot signup: account + tenant + plan choice. Used by the public
 * /get-started page. Picking a paid plan redirects to gateway-hosted
 * checkout after the user + tenant + (pending) subscription land in the DB.
 */
class GetStartedRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user() === null; // guest-only
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Account
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),

            // Tenant
            'tenant_name' => ['required', 'string', 'max:120'],
            'tenant_slug' => ['nullable', 'string', 'alpha_dash', 'min:2', 'max:64', 'unique:tenants,slug'],

            // Plan + gateway
            'plan_slug' => [
                'required',
                'string',
                Rule::exists('plans', 'slug')
                    ->where('is_active', true)
                    ->where('is_public', true)
                    ->whereNull('deleted_at'),
            ],
            'gateway' => ['nullable', 'string'],
        ];
    }
}
