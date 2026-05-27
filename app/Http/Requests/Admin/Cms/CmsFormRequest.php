<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;
use Illuminate\Validation\Rule;

class CmsFormRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('form')?->id;

        return [
            'slug' => ['required', 'string', 'alpha_dash', 'max:120', Rule::unique('cms_forms', 'slug')->ignore($id)],
            'name' => ['required', 'string', 'max:240'],
            'fields' => ['nullable', 'array'],
            'fields.*.key' => ['required', 'string', 'alpha_dash', 'max:120'],
            'fields.*.label' => ['required', 'string', 'max:240'],
            'fields.*.type' => ['required', 'in:text,email,tel,textarea,select,checkbox,number,url'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['string', 'max:120'],
            'success_message' => ['nullable', 'string', 'max:1000'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'webhook_url' => ['nullable', 'string', 'max:2048'],
            'store_submissions' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
