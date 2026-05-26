<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared base for every admin-scope FormRequest. Authorization is centralised:
 * the route group is already gated on `role:Super Admin`, but FormRequests get
 * an explicit second seam in case a controller is mounted elsewhere later.
 */
abstract class AdminFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('Super Admin');
    }
}
