<?php

namespace App\Support\Cms;

use App\Models\CmsGlobal;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton accessor for CMS globals (brand / nav / footer / announcement /
 * analytics / cookie / contact / social / seo_defaults).
 *
 * - `get(key)` returns the merged payload (defaults from config overlaid by
 *   saved DB values). Cached per key.
 * - `save(key, payload)` validates the keys against the declared schema in
 *   config('cms.globals.{key}.fields'), merges with defaults, persists, and
 *   busts the cache.
 * - `forPublic()` returns the bundle of globals safe to expose to
 *   unauthenticated visitors (brand, header_menu, footer_menu,
 *   announcement, cookie_banner, contact, social, analytics + seo_defaults).
 */
class GlobalsService
{
    private const CACHE_KEY_PREFIX = 'cms.global:';

    public function schema(string $key): ?array
    {
        $schema = config("cms.globals.{$key}");

        return is_array($schema) ? $schema : null;
    }

    public function keys(): array
    {
        return array_keys((array) config('cms.globals', []));
    }

    public function defaults(string $key): array
    {
        $schema = $this->schema($key);

        return (array) ($schema['defaults'] ?? []);
    }

    /**
     * Get the merged payload for a global. Defaults overlaid by DB values.
     *
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        if ($this->schema($key) === null) {
            return [];
        }

        $ttl = (int) config('cms.cache.globals_ttl', 3600);

        // Stale-while-revalidate: fresh for 60s, stale until full TTL.
        return Cache::flexible(self::CACHE_KEY_PREFIX.$key, [60, $ttl], function () use ($key) {
            $defaults = $this->defaults($key);
            $row = CmsGlobal::query()->where('key', $key)->first();

            return array_merge($defaults, $row?->payload ?? []);
        });
    }

    /**
     * Persist a payload for a global. Only keys declared in the schema are
     * kept; unknown keys are silently dropped to prevent admin-side schema
     * drift.
     *
     * @param  array<string, mixed>  $payload
     */
    public function save(string $key, array $payload, ?int $userId = null): CmsGlobal
    {
        $schema = $this->schema($key);
        if ($schema === null) {
            throw new \InvalidArgumentException("Unknown global key [{$key}].");
        }

        $allowedKeys = array_map(fn ($f) => $f['key'], (array) ($schema['fields'] ?? []));
        $clean = array_intersect_key($payload, array_flip($allowedKeys));

        $row = CmsGlobal::query()->firstOrNew(['key' => $key]);
        $row->label = (string) ($schema['label'] ?? ucfirst($key));
        $row->payload = array_merge($this->defaults($key), $clean);
        $row->updated_by_id = $userId;
        $row->save();

        Cache::forget(self::CACHE_KEY_PREFIX.$key);

        return $row;
    }

    /**
     * Bundle of globals exposed to public pages via Inertia share.
     * Analytics IDs are included but the admin/auth scopes should opt out.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forPublic(): array
    {
        return [
            'brand' => $this->get('brand'),
            'header_menu' => $this->get('header_menu'),
            'footer_menu' => $this->get('footer_menu'),
            'announcement' => $this->get('announcement'),
            'cookie_banner' => $this->get('cookie_banner'),
            'contact' => $this->get('contact'),
            'social' => $this->get('social'),
            'analytics' => $this->get('analytics'),
            'seo_defaults' => $this->get('seo_defaults'),
            'docs_sidebar' => $this->get('docs_sidebar'),
        ];
    }

    /**
     * Bust every globals cache key — used after schema changes / seeders.
     */
    public function flush(): void
    {
        foreach ($this->keys() as $key) {
            Cache::forget(self::CACHE_KEY_PREFIX.$key);
        }
    }
}
