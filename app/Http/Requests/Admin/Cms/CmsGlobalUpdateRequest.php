<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;

class CmsGlobalUpdateRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payload' => ['present', 'array'],
        ];
    }
}
