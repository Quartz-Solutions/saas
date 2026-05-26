<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the "subscribe / upgrade to plan" form.
 *
 * Used by both initial subscribe and change-plan flows — the controller
 * branches on whether a current subscription exists.
 */
class SubscribeRequest extends FormRequest
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
            'plan' => [
                'required', 'string',
                Rule::exists('plans', 'slug')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'gateway' => ['nullable', 'string'],
        ];
    }
}
