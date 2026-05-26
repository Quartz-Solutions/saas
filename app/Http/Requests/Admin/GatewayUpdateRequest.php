<?php

namespace App\Http\Requests\Admin;

class GatewayUpdateRequest extends AdminFormRequest
{
    /**
     * Rules are pulled from the per-gateway field declarations in
     * config('billing.gateways.{id}.fields'). Each field's `rules` string
     * is applied to its own key on the submitted payload.
     *
     * @return array<string, string|array<int, mixed>>
     */
    public function rules(): array
    {
        $id = (string) $this->route('gateway');
        $fields = (array) config("billing.gateways.{$id}.fields", []);

        $rules = [];
        foreach ($fields as $key => $field) {
            $rules[$key] = $field['rules'] ?? 'nullable|string';
        }

        return $rules;
    }
}
