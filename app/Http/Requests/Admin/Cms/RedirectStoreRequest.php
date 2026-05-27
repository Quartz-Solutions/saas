<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;

class RedirectStoreRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_path' => ['required', 'string', 'max:2048', 'starts_with:/', 'unique:redirects,from_path'],
            'to_path' => ['required', 'string', 'max:2048'],
            'status_code' => ['nullable', 'integer', 'in:301,302,307,308'],
        ];
    }
}
