<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class UserDestroyRequest extends FormRequest
{
    /**
     * Refuse to delete the currently authenticated user.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target !== null && $target->id !== $this->user()?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
