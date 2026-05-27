<?php

namespace App\Http\Requests\Admin\Tenants;

use App\Http\Requests\Admin\AdminFormRequest;

class RestoreTenantRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
