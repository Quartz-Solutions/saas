<?php

namespace App\Http\Requests\ApiTokens;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiTokenStoreRequest extends FormRequest
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
        $allowed = array_map(
            static fn (array $a): string => (string) $a['key'],
            (array) config('api-abilities.abilities', []),
        );

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in($allowed)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
