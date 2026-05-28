<?php

namespace App\Support\Theme;

use App\Models\Theme;
use App\Models\ThemeFont;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Single seam for the theme catalog (CLAUDE.md service-layer rule — all theme
 * writes route through here, never Theme::create in a controller). Owns the
 * one-active invariant, compilation to the public disk, and cache discipline
 * (mirrors App\Support\Admin\AppSettingsService).
 */
class ThemeService
{
    public function __construct(
        private readonly ThemeCompiler $compiler,
        private readonly FontArchiveImporter $fonts,
    ) {}

    /**
     * The one active theme. Null only before the table exists (fresh install)
     * or if nothing is active yet. Resolved from the cached active id — a
     * cheap PK lookup; the per-request hot path uses activeCssUrl() (cache
     * only, no DB).
     */
    public function active(): ?Theme
    {
        $meta = $this->activeMeta();

        return $meta === null ? null : Theme::find($meta['id']);
    }

    /**
     * Compiled stylesheet URL for the blade <link>, hash-busted via the
     * compiled filename. Null when there's no active/compiled theme. Served
     * entirely from cache — no DB query per request.
     */
    public function activeCssUrl(): ?string
    {
        $meta = $this->activeMeta();
        if ($meta === null || empty($meta['css'])) {
            return null;
        }

        return Storage::disk($this->disk())->url($meta['css']);
    }

    /**
     * Cached {id, css} for the active theme. Plain array (always serializable
     * — caching the Eloquent model itself breaks on persistent stores).
     *
     * @return array{id: int, css: string|null}|null
     */
    protected function activeMeta(): ?array
    {
        if (! $this->tableReady()) {
            return null;
        }

        return Cache::remember($this->cacheKey(), $this->ttl(), function (): ?array {
            $theme = Theme::query()
                ->where('is_active', true)
                ->first(['id', 'compiled_css_path']);

            if ($theme === null) {
                return null;
            }

            return ['id' => (int) $theme->id, 'css' => $theme->compiled_css_path];
        });
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    public function create(array $attrs, ?User $by = null): Theme
    {
        return DB::transaction(function () use ($attrs, $by): Theme {
            $theme = new Theme;
            $this->fill($theme, $attrs);
            $theme->is_active = false;     // activation is an explicit action
            $theme->is_preset = false;     // only the seeder mints presets
            $theme->created_by_id = $by?->id;
            $theme->save();

            $this->compile($theme);
            $this->invalidate();

            return $theme->fresh() ?? $theme;
        });
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    public function update(Theme $theme, array $attrs): Theme
    {
        return DB::transaction(function () use ($theme, $attrs): Theme {
            $this->fill($theme, $attrs);
            $theme->save();

            $this->compile($theme);
            $this->invalidate();

            return $theme->fresh() ?? $theme;
        });
    }

    /**
     * Flip the active theme. Enforces the one-active invariant in a
     * transaction, recompiles, and invalidates the resolution cache.
     */
    public function activate(Theme $theme): Theme
    {
        DB::transaction(function () use ($theme): void {
            Theme::query()
                ->where('is_active', true)
                ->where('id', '!=', $theme->id)
                ->update(['is_active' => false]);

            $theme->forceFill(['is_active' => true])->save();
        });

        $this->compile($theme->fresh() ?? $theme);
        $this->invalidate();

        return $theme->fresh() ?? $theme;
    }

    /**
     * Duplicate a theme into a new editable (non-preset, inactive) copy,
     * including its custom CSS and uploaded font faces.
     */
    public function duplicate(Theme $theme): Theme
    {
        $theme->loadMissing('fonts');

        return DB::transaction(function () use ($theme): Theme {
            $copy = new Theme;
            $copy->fill([
                'name' => $theme->name.' (copy)',
                'description' => $theme->description,
                'mode_hint' => $theme->mode_hint,
                'tokens' => $theme->tokens,
                'radius' => $theme->radius,
                'font_family' => $theme->font_family,
            ]);
            $copy->slug = $this->uniqueSlug($copy->name);
            $copy->is_active = false;
            $copy->is_preset = false;
            $copy->save();

            $disk = Storage::disk($this->disk());

            // Duplicate the custom CSS file (so editing the copy can't mutate
            // the original's stylesheet).
            if ($theme->custom_css_path !== null && $disk->exists($theme->custom_css_path)) {
                $sha = substr(hash('sha256', (string) $disk->get($theme->custom_css_path)), 0, 8);
                $cssPath = "themes/{$copy->id}/custom-{$sha}.css";
                $disk->put($cssPath, (string) $disk->get($theme->custom_css_path));
                $copy->forceFill(['custom_css_path' => $cssPath])->saveQuietly();
            }

            // Duplicate font faces + their files.
            foreach ($theme->fonts as $font) {
                /** @var ThemeFont $font */
                if (! $disk->exists($font->path)) {
                    continue;
                }
                $filename = Str::random(40).'.'.$font->format;
                $path = "themes/{$copy->id}/fonts/{$filename}";
                $disk->put($path, (string) $disk->get($font->path));

                ThemeFont::create([
                    'theme_id' => $copy->id,
                    'family' => $font->family,
                    'weight' => $font->weight,
                    'style' => $font->style,
                    'format' => $font->format,
                    'path' => $path,
                    'original_filename' => $font->original_filename,
                    'size_bytes' => $font->size_bytes,
                ]);
            }

            $this->compile($copy->fresh() ?? $copy);
            $this->invalidate();

            return $copy->fresh() ?? $copy;
        });
    }

    /**
     * Soft-delete a user theme. Presets and the active theme are protected.
     */
    public function delete(Theme $theme): void
    {
        if ($theme->is_preset) {
            throw new RuntimeException('Preset themes cannot be deleted — clone one to make an editable copy.');
        }
        if ($theme->is_active) {
            throw new RuntimeException('The active theme cannot be deleted. Activate another theme first.');
        }

        $theme->delete();
        $this->invalidate();
    }

    /**
     * Write the compiled CSS artifact (§4) to the public disk and record its
     * hashed path on the theme. Returns the storage-relative path.
     */
    public function compile(Theme $theme): string
    {
        $css = $this->compiler->compile($theme);
        $disk = Storage::disk($this->disk());

        $sha = substr(hash('sha256', $css), 0, 8);
        $path = "themes/{$theme->id}/compiled-{$sha}.css";

        // Clean up a stale artifact with a different hash.
        $old = $theme->compiled_css_path;
        if ($old !== null && $old !== $path && $disk->exists($old)) {
            $disk->delete($old);
        }

        $disk->put($path, $css);
        $this->markWritable($theme);

        $theme->forceFill([
            'compiled_css_path' => $path,
            'compiled_at' => now(),
        ])->saveQuietly();

        return $path;
    }

    /**
     * Keep the theme's storage directories writable by the web user.
     *
     * `db:seed` may run as root (CLI), which would create root-owned theme
     * directories the php-fpm worker (www-data) can't later write to — e.g.
     * cloning a theme would fail to create themes/{newId}. Opening these dirs
     * mirrors the dev entrypoint's `chmod -R 0777 storage`; they live under the
     * publicly-served disk anyway. Best-effort: a chmod failure never breaks a
     * mutation.
     */
    protected function markWritable(Theme $theme): void
    {
        if ($this->disk() !== 'public') {
            return;
        }

        try {
            $disk = Storage::disk($this->disk());
            foreach (['themes', "themes/{$theme->id}"] as $rel) {
                $abs = $disk->path($rel);
                if (is_dir($abs)) {
                    @chmod($abs, 0777);
                }
            }
        } catch (Throwable) {
            // best-effort
        }
    }

    /**
     * Store (or clear) the theme's custom CSS escape-hatch file, then
     * recompile so it lands in the artifact. Accepts an UploadedFile or raw
     * string (inline editor).
     */
    public function storeCustomCss(Theme $theme, UploadedFile|string $css): void
    {
        $contents = $css instanceof UploadedFile
            ? (string) file_get_contents($css->getRealPath())
            : $css;

        $contents = $this->compiler->stripRemoteImports($contents);
        $contents = trim($contents);

        $disk = Storage::disk($this->disk());
        $old = $theme->custom_css_path;

        if ($contents === '') {
            if ($old !== null && $disk->exists($old)) {
                $disk->delete($old);
            }
            $theme->forceFill(['custom_css_path' => null])->saveQuietly();
        } else {
            $sha = substr(hash('sha256', $contents), 0, 8);
            $path = "themes/{$theme->id}/custom-{$sha}.css";
            if ($old !== null && $old !== $path && $disk->exists($old)) {
                $disk->delete($old);
            }
            $disk->put($path, $contents);
            $theme->forceFill(['custom_css_path' => $path])->saveQuietly();
        }

        $this->compile($theme->fresh() ?? $theme);
        $this->invalidate();
    }

    /**
     * Import a Google-Fonts ZIP into theme_fonts rows + recompile.
     *
     * @return Collection<int, ThemeFont>
     */
    public function importFontZip(Theme $theme, UploadedFile $zip): Collection
    {
        $created = $this->fonts->import($theme, $zip->getRealPath());

        $this->compile($theme->fresh() ?? $theme);
        $this->invalidate();

        return $created;
    }

    /**
     * Remove a single uploaded font face + its file. If it was the theme's
     * chosen family and no faces remain for that family, the family pointer is
     * cleared. Recompiles + invalidates.
     */
    public function deleteFont(Theme $theme, ThemeFont $font): void
    {
        $disk = Storage::disk($this->disk());
        if ($disk->exists($font->path)) {
            $disk->delete($font->path);
        }
        $font->delete();

        $theme->load('fonts');
        if ($theme->font_family !== null
            && ! $theme->fonts->contains(fn (ThemeFont $f): bool => mb_strtolower($f->family) === mb_strtolower((string) $theme->font_family))
        ) {
            $theme->forceFill(['font_family' => null])->saveQuietly();
        }

        $this->compile($theme->fresh() ?? $theme);
        $this->invalidate();
    }

    /**
     * Canonical editable-token schema for the editor UI. Groups + per-token
     * label/type, plus the build-time light/dark defaults so the editor can
     * show inherited values and the radius/font bounds.
     *
     * @return array<string, mixed>
     */
    public function tokenSchema(): array
    {
        $groups = [];
        foreach ((array) config('themes.groups', []) as $key => $group) {
            $tokens = [];
            foreach ((array) ($group['tokens'] ?? []) as $tokenKey => $label) {
                $tokens[] = ['key' => $tokenKey, 'label' => $label, 'type' => 'color'];
            }
            $groups[] = [
                'key' => $key,
                'label' => $group['label'] ?? $key,
                'description' => $group['description'] ?? null,
                'tokens' => $tokens,
            ];
        }

        return [
            'groups' => $groups,
            'defaults' => [
                'light' => (array) config('themes.defaults.light', []),
                'dark' => (array) config('themes.defaults.dark', []),
                'radius' => config('themes.defaults.radius', '0.625rem'),
            ],
            'radius' => (array) config('themes.radius', ['min' => 0, 'max' => 1.5, 'step' => 0.025]),
        ];
    }

    public function invalidate(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * Map validated attributes onto the model, sanitizing free-form values.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function fill(Theme $theme, array $attrs): void
    {
        if (array_key_exists('name', $attrs)) {
            $theme->name = (string) $attrs['name'];
        }
        if (array_key_exists('description', $attrs)) {
            $theme->description = $attrs['description'] !== null ? (string) $attrs['description'] : null;
        }
        if (array_key_exists('mode_hint', $attrs)) {
            $theme->mode_hint = in_array($attrs['mode_hint'], ['light', 'dark', 'both'], true)
                ? $attrs['mode_hint']
                : 'both';
        }
        if (array_key_exists('radius', $attrs)) {
            $theme->radius = $this->compiler->safeValue((string) $attrs['radius'])
                ?? (string) config('themes.defaults.radius', '0.625rem');
        }
        if (array_key_exists('font_family', $attrs)) {
            $family = is_string($attrs['font_family']) ? trim($attrs['font_family']) : '';
            $theme->font_family = $family !== '' ? $family : null;
        }
        if (array_key_exists('tokens', $attrs)) {
            $theme->tokens = $this->sanitizeTokens((array) $attrs['tokens']);
        }

        // Slug: derive on first save, immutable thereafter.
        if (! $theme->exists || $theme->slug === null) {
            $base = $attrs['slug'] ?? $attrs['name'] ?? $theme->name ?? 'theme';
            $theme->slug = $this->uniqueSlug((string) $base);
        }
    }

    /**
     * Keep only known token keys with safe values, in the {light, dark} shape.
     *
     * @param  array<string, mixed>  $tokens
     * @return array<string, array<string, string>>
     */
    protected function sanitizeTokens(array $tokens): array
    {
        $allowed = $this->allowedTokenKeys();
        $out = ['light' => [], 'dark' => []];

        foreach (['light', 'dark'] as $mode) {
            $map = (array) ($tokens[$mode] ?? []);
            foreach ($map as $key => $value) {
                if (! is_string($key) || ! in_array($key, $allowed, true)) {
                    continue;
                }
                if (! is_string($value) && ! is_numeric($value)) {
                    continue;
                }
                $clean = $this->compiler->safeValue((string) $value);
                if ($clean === null) {
                    continue;
                }
                $out[$mode][$key] = $clean;
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    protected function allowedTokenKeys(): array
    {
        $keys = [];
        foreach ((array) config('themes.groups', []) as $group) {
            foreach (array_keys((array) ($group['tokens'] ?? [])) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        if ($slug === '') {
            $slug = 'theme';
        }

        $candidate = $slug;
        $i = 2;
        while (Theme::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }

    protected function tableReady(): bool
    {
        try {
            return Schema::hasTable('themes');
        } catch (Throwable) {
            return false;
        }
    }

    protected function cacheKey(): string
    {
        return (string) config('themes.cache_key', 'theme.active');
    }

    protected function ttl(): int
    {
        return (int) config('themes.cache_ttl', 86400);
    }

    protected function disk(): string
    {
        return (string) config('themes.storage_disk', 'public');
    }
}
