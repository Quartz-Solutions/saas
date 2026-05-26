<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GatewayUpdateRequest;
use App\Models\Subscription;
use App\Support\Admin\AppSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-Admin management for payment gateways.
 *
 * Catalog: config('billing.gateways'). Each entry carries UI metadata
 * (name, region, capabilities, driver_status, fields) + runtime values
 * (enabled, secret, webhook_secret, …).
 *
 * Storage: app_settings table via AppSettingsService. Group key is
 * `gateway_{id}` so settings flow through the same hydration path as the
 * other admin settings (Mail/OAuth/Sentry/Slack/AWS).
 */
class GatewaysController extends Controller
{
    public function __construct(private readonly AppSettingsService $settings) {}

    public function index(): Response
    {
        return Inertia::render('admin/gateways/index', [
            'gateways' => $this->present(),
        ]);
    }

    public function edit(string $gateway): Response
    {
        $catalog = (array) config("billing.gateways.{$gateway}");
        if ($catalog === []) {
            abort(404);
        }

        $group = 'gateway_'.$gateway;
        $groupCatalog = $this->settings->group($group);
        if ($groupCatalog === null) {
            // Driver is planned and has no fields yet — show a read-only
            // placeholder so the admin knows the slot exists.
            return Inertia::render('admin/gateways/edit', [
                'gateway' => $this->serialize($gateway, $catalog),
                'fields' => null,
                'values' => [],
            ]);
        }

        $overrides = $this->settings->allOverrides();
        $fields = [];
        foreach ($groupCatalog['fields'] as $key => $field) {
            $isSecret = ($field['type'] ?? 'string') === 'secret';
            $raw = $overrides[$key] ?? null;
            $hasValue = $raw !== null && $raw !== '';
            $fields[$key] = [
                'key' => $key,
                'label' => $field['label'] ?? $key,
                'type' => $field['type'] ?? 'string',
                'options' => $field['options'] ?? null,
                'help' => $field['help'] ?? null,
                'is_secret' => $isSecret,
                'has_value' => $hasValue,
                'value' => $isSecret && $hasValue
                    ? AppSettingsService::SECRET_MASK
                    : ($raw ?? $field['default'] ?? null),
            ];
        }

        return Inertia::render('admin/gateways/edit', [
            'gateway' => $this->serialize($gateway, $catalog),
            'fields' => $fields,
            'values' => $overrides,
        ]);
    }

    public function update(GatewayUpdateRequest $request, string $gateway): RedirectResponse
    {
        $catalog = (array) config("billing.gateways.{$gateway}");
        if ($catalog === []) {
            abort(404);
        }

        $group = 'gateway_'.$gateway;
        if ($this->settings->group($group) === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No editable fields for this gateway yet.'),
            ]);

            return back();
        }

        // Planned gateways can save credentials but can't be enabled until
        // the driver lands. Quietly drop the enabled key when planned.
        $payload = $request->validated();
        if (
            ($catalog['driver_status'] ?? 'planned') === 'planned'
            && array_key_exists(strtoupper($gateway).'_ENABLED', $payload)
        ) {
            $payload[strtoupper($gateway).'_ENABLED'] = false;
        }

        $this->settings->updateGroup($group, $payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name credentials saved.', ['name' => $catalog['name'] ?? $gateway]),
        ]);

        return to_route('admin.gateways.edit', ['gateway' => $gateway]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function present(): array
    {
        $gateways = (array) config('billing.gateways', []);
        $out = [];
        foreach ($gateways as $id => $meta) {
            $out[] = $this->serialize($id, $meta);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function serialize(string $id, array $meta): array
    {
        $hasFields = ! empty($meta['fields']);
        $shipped = ($meta['driver_status'] ?? 'planned') === 'shipped';

        // "Configured" means: enabled flag is true AND a secret/auth field
        // is populated. For planned gateways, we count any saved value.
        $enabled = (bool) ($meta['enabled'] ?? false);
        $configured = false;
        if ($hasFields) {
            $overrides = $this->settings->allOverrides();
            foreach (array_keys($meta['fields']) as $key) {
                if (! empty($overrides[$key])) {
                    $configured = true;
                    break;
                }
            }
        }

        $activeSubscriptions = 0;
        if ($shipped) {
            $activeSubscriptions = Subscription::query()
                ->where('gateway', $id)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->count();
        }

        return [
            'id' => $id,
            'name' => $meta['name'] ?? $id,
            'description' => $meta['description'] ?? null,
            'regions' => (array) ($meta['regions'] ?? []),
            'capabilities' => (array) ($meta['capabilities'] ?? []),
            'driver_status' => $meta['driver_status'] ?? 'planned',
            'documentation_url' => $meta['documentation_url'] ?? null,
            'enabled' => $enabled,
            'configured' => $configured,
            'has_fields' => $hasFields,
            'active_subscriptions' => $activeSubscriptions,
        ];
    }
}
