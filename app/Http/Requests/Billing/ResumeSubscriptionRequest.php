<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class ResumeSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $this->user();

        if ($tenant === null || $user === null) {
            return false;
        }

        setPermissionsTeamId($tenant->id);

        return $user->hasAnyRole(['Owner', 'Admin']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
