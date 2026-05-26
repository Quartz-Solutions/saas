<?php

namespace App\Http\Requests\Settings\Privacy;

use Illuminate\Foundation\Http\FormRequest;

class DataExportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * No user input — request is identity-scoped. Kept as a FormRequest
     * to maintain the controller-conventions invariant (every write
     * action gets a FormRequest).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
