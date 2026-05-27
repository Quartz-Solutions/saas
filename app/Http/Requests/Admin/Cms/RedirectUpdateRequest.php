<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;
use Illuminate\Validation\Rule;

class RedirectUpdateRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('redirect')?->id;

        return [
            'from_path' => ['required', 'string', 'max:2048', 'starts_with:/', Rule::unique('redirects', 'from_path')->ignore($id)],
            'to_path' => ['required', 'string', 'max:2048'],
            'status_code' => ['required', 'integer', 'in:301,302,307,308'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
