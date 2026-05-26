<?php

namespace App\Http\Requests\Webhooks;

use App\Models\OutboundWebhook;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookUpdateRequest extends FormRequest
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
        $events = array_keys((array) config('api-abilities.webhook_events', []));

        return [
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in($events)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
