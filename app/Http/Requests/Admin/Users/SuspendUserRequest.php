<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\Admin\AdminFormRequest;

class SuspendUserRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $targetId = (int) $this->route('user')?->id;

        // Block self-suspension — locking yourself out is never desirable.
        return $targetId !== (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
