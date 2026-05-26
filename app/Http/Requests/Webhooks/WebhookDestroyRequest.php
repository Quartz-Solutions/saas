<?php

namespace App\Http\Requests\Webhooks;

use App\Models\OutboundWebhook;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class WebhookDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        /** @var OutboundWebhook|null $webhook */
        $webhook = $this->route('webhook');

        if (! $tenant instanceof Tenant || $webhook === null || $webhook->tenant_id !== $tenant->id) {
            return false;
        }

        $user = $this->user();

        return $user !== null
            && $tenant->memberships()->where('user_id', $user->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
