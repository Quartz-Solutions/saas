<?php

namespace App\Support\Admin;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Single seam for reading and writing settings managed through
 * /admin/settings. Persists overrides to the `app_settings` table and
 * applies them to the runtime config on every request via
 * AppSettingsServiceProvider.
 */
class AppSettingsService
{
    public const CACHE_KEY = 'app_settings:overrides';

    public const CACHE_TTL_SECONDS = 86400;

    public const SECRET_MASK = '••••••••';

    /**
     * Catalog -> indexed by group, then by key. Aggregates:
     *
     *   - app-level integrations (mail / OAuth / Sentry / Slack / AWS / app
     *     branding) from config('app-settings.groups')
     *   - payment gateways from config('billing.gateways.*') — each gateway
     *     becomes a group keyed `gateway_{id}`
     *
     * The boot-time Config::set hydration walks this merged catalog, so
     * gateway field overrides flow into `billing.gateways.{id}.*` exactly
     * like app-settings overrides flow into their own paths.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_merge(
            (array) config('app-settings.groups', []),
            $this->gatewayCatalog(),
        );
    }

    /**
     * Gateway catalog reshaped to match the app-settings group shape so the
     * rest of this service can treat them uniformly. Gateways without any
     * field declarations yet (driver_status=planned, awaiting per-gateway
     * agent work) are skipped — they have nothing to render or persist.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function gatewayCatalog(): array
    {
        $out = [];
        foreach ((array) config('billing.gateways', []) as $id => $meta) {
            $fields = $meta['fields'] ?? null;
            if (! is_array($fields) || $fields === []) {
                continue;
            }
            $out['gateway_'.$id] = [
                'label' => $meta['name'] ?? $id,
                'description' => $meta['description'] ?? null,
                'icon' => 'CreditCard',
                'fields' => $fields,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function group(string $group): ?array
    {
        return $this->catalog()[$group] ?? null;
    }

    /**
     * Resolve all overrides as a flat ['KEY' => raw_value] map. The
     * provider applies these to `Config::set` at request boot, and the
     * controller uses them to render the form (re-masking secrets).
     *
     * Cached — invalidated on every write.
     *
     * @return array<string, string|null>
     */
    public function allOverrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            if (! $this->tableReady()) {
                return [];
            }

            return AppSetting::query()
                ->get(['key', 'value', 'is_secret'])
                ->mapWithKeys(fn (AppSetting $row): array => [$row->key => $row->value])
                ->all();
        });
    }

    /**
     * Render-ready payload for the UI: each group with its fields, the
     * current saved value (secrets masked), and the catalog metadata.
     *
     * @return array<string, mixed>
     */
    public function presentForAdmin(): array
    {
        $overrides = $this->allOverrides();
        $groups = [];

        foreach ($this->catalog() as $groupKey => $group) {
            $fields = [];
            foreach ($group['fields'] as $fieldKey => $field) {
                $isSecret = ($field['type'] ?? 'string') === 'secret';
                $raw = $overrides[$fieldKey] ?? null;
                $hasValue = $raw !== null && $raw !== '';

                $fields[$fieldKey] = [
                    'key' => $fieldKey,
                    'label' => $field['label'] ?? $fieldKey,
                    'type' => $field['type'] ?? 'string',
                    'options' => $field['options'] ?? null,
                    'help' => $field['help'] ?? null,
                    'is_secret' => $isSecret,
                    'has_value' => $hasValue,
                    'value' => $isSecret && $hasValue ? self::SECRET_MASK : ($raw ?? $field['default'] ?? null),
                ];
            }

            $groups[$groupKey] = [
                'key' => $groupKey,
                'label' => $group['label'],
                'description' => $group['description'] ?? null,
                'icon' => $group['icon'] ?? null,
                'fields' => $fields,
            ];
        }

        return $groups;
    }

    /**
     * Persist a group's payload. Empty strings clear the override.
     * Secret fields with the mask sentinel are skipped (left untouched).
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateGroup(string $group, array $payload): void
    {
        $catalog = $this->group($group);
        if ($catalog === null) {
            return;
        }

        DB::transaction(function () use ($catalog, $payload, $group): void {
            foreach ($catalog['fields'] as $key => $field) {
                if (! array_key_exists($key, $payload)) {
                    continue;
                }

                $value = $payload[$key];
                $isSecret = ($field['type'] ?? 'string') === 'secret';

                // Don't overwrite a saved secret with the mask sentinel.
                if ($isSecret && $value === self::SECRET_MASK) {
                    continue;
                }

                if ($field['type'] === 'bool') {
                    $value = $value ? '1' : '0';
                }

                if ($value === null || $value === '') {
                    AppSetting::query()->where('key', $key)->delete();

                    continue;
                }

                AppSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $group,
                        'is_secret' => $isSecret,
                        'value' => (string) $value,
                        'updated_by' => Auth::id(),
                    ],
                );
            }
        });

        $this->invalidate();
    }

    /**
     * Apply all overrides to the runtime config. Called from the
     * provider on every request. Safe to call when the table doesn't
     * exist yet (initial install): silently no-ops.
     */
    public function applyOverrides(): void
    {
        $overrides = $this->allOverrides();
        if ($overrides === []) {
            return;
        }

        foreach ($this->catalog() as $group) {
            foreach ($group['fields'] as $key => $field) {
                if (! array_key_exists($key, $overrides)) {
                    continue;
                }

                $value = $this->castForConfig($overrides[$key], $field);
                Config::set($field['config_path'], $value);
            }
        }
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function castForConfig(?string $raw, array $field): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($field['type'] ?? 'string') {
            'int' => (int) $raw,
            'bool' => in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            default => $raw,
        };
    }

    protected function tableReady(): bool
    {
        try {
            return Schema::hasTable('app_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
