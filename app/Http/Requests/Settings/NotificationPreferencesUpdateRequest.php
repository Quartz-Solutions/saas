<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class NotificationPreferencesUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Body shape:
     *
     *   {
     *     "preferences": {
     *       "welcome": { "email": true, "database": true },
     *       "login_alert": { "email": false, "database": true }
     *     }
     *   }
     *
     * Channels/events not present in the body are left as-is.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        $events = array_keys(config('notifications.events', []));
        $channels = collect(config('notifications.channels', []))
            ->filter(fn ($c) => (bool) ($c['enabled'] ?? false))
            ->keys()
            ->all();

        return [
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            // We accept any key; the controller validates each pair against
            // the canonical registry via `NotificationDispatcher::setPreference`.
            'preferences.*.*' => ['boolean'],
        ] + array_combine(
            array_map(fn ($e) => "preferences.{$e}", $events),
            array_fill(0, count($events), ['sometimes', 'array']),
        );
    }
}
