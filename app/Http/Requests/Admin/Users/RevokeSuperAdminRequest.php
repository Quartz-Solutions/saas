<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\Admin\AdminFormRequest;

class RevokeSuperAdminRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $targetId = (int) $this->route('user')?->id;

        // Refuse to drop our own Super Admin role; have to do it from another account.
        return $targetId !== (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
