<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;

class CmsPageDestroyRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Soft-delete confirmation captured client-side via AlertDialog.
            // No payload needed; the route param identifies the page.
        ];
    }
}
