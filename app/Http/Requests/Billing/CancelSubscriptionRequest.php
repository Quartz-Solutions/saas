<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the cancel-subscription form (reason capture).
 */
class CancelSubscriptionRequest extends FormRequest
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
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'immediately' => ['nullable', 'boolean'],
        ];
    }
}
