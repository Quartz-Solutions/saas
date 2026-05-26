<?php

namespace App\Http\Requests\Settings\Sessions;

use Illuminate\Foundation\Http\FormRequest;

class SessionDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
