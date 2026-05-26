<?php

namespace App\Http\Requests\Admin;

class SettingsUpdateRequest extends AdminFormRequest
{
    /**
     * @return array<string, string|array<int, mixed>>
     */
    public function rules(): array
    {
        $group = (string) $this->route('group');
        $catalog = config("app-settings.groups.{$group}");

        if (! is_array($catalog) || ! isset($catalog['fields'])) {
            return [];
        }

        $rules = [];
        foreach ($catalog['fields'] as $key => $field) {
            $rules[$key] = $field['rules'] ?? 'nullable|string';
        }

        return $rules;
    }
}
