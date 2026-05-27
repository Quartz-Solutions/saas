<?php

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\Admin\AdminFormRequest;

/**
 * Generic FormRequest for user-targeting admin actions where the payload is
 * only an optional reason field. Specialised requests below add stricter
 * rules (e.g. SuspendUserRequest disallows self-targeting).
 */
class UserActionRequest extends AdminFormRequest
{
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
