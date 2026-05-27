<?php

namespace App\Http\Requests\Admin\Tenants;

use App\Http\Requests\Admin\AdminFormRequest;

class GdprExportTenantRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
