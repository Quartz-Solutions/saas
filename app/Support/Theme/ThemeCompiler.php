<?php

namespace App\Support\Theme;

use App\Models\Theme;
use App\Models\ThemeFont;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a Theme (token maps + uploaded fonts + custom CSS) into a single
 * stylesheet string. The output is written to the public disk by
 * ThemeService::compile() and linked in <head> after the build-time app.css,
 * so its :root / .dark rules win on the cascade (equal specificity, later
 * source order).
 *
 * Order (see agent-os/product/custom_theme.md §4):
 *   1. @font-face for every theme_fonts row (self-hosted /storage URLs)
 *   2. :root token overrides (+ --radius, + --font-sans when a face matches)
 *   3. .dark token overrides
 *   4. the theme's custom CSS, verbatim, last (highest priority)
 */
class ThemeCompiler
{
    public function compile(Theme $theme): string
    {
        $theme->loadMissing('fonts');

        $parts = array_filter([
            $this->fontFaces($theme),
            $this->rootBlock($theme),
            $this->darkBlock($theme),
            $this->customCss($theme),
        ], static fn (string $part): bool => $part !== '');

        return implode("\n\n", $parts)."\n";
    }

    /**
     * Sanitize a single token / declaration value. Rejects anything that could
     * break out of the declaration and inject arbitrary rules. Returns null
     * when the value is unsafe or empty (caller skips it → build-time default
     * applies via the cascade).
     */
    public function safeValue(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // No declaration/rule terminators, no markup, no at-rules, no CSS
        // comments (which could hide a payload), no url()/expression() tricks.
        if (preg_match('/[;{}<>@]/', $value)) {
            return null;
        }
        if (str_contains($value, '/*') || str_contains($value, '*/')) {
            return null;
        }
        if (preg_match('/url\s*\(|expression\s*\(/i', $value)) {
            return null;
        }

        return $value;
    }

    protected function fontFaces(Theme $theme): string
    {
        $disk = Storage::disk($this->disk());
        $lines = [];

        foreach ($theme->fonts as $font) {
            /** @var ThemeFont $font */
            $family = $this->safeFamily($font->family);
            if ($family === null) {
                continue;
            }

            $url = $disk->url($font->path);
            $format = $this->safeValue((string) $font->format) ?? 'woff2';
            $weight = $this->safeValue((string) $font->weight) ?? '400';
            $style = $this->safeValue((string) $font->style) ?? 'normal';

            $lines[] = sprintf(
                "@font-face {\n".
                "    font-family: '%s';\n".
                "    font-style: %s;\n".
                "    font-weight: %s;\n".
                "    font-display: swap;\n".
                "    src: url('%s') format('%s');\n".
                '}',
                $family,
                $style,
                $weight,
                $this->safeUrl($url),
                $format,
            );
        }

        return implode("\n", $lines);
    }

    protected function rootBlock(Theme $theme): string
    {
        $tokens = (array) ($theme->tokens['light'] ?? []);
        $decls = $this->declarations($tokens);

        $radius = $this->safeValue((string) ($theme->radius ?? ''));
        if ($radius !== null) {
            $decls[] = "    --radius: {$radius};";
        }

        $fontDecl = $this->fontSansDeclaration($theme);
        if ($fontDecl !== null) {
            $decls[] = $fontDecl;
        }

        if ($decls === []) {
            return '';
        }

        return ":root {\n".implode("\n", $decls)."\n}";
    }

    protected function darkBlock(Theme $theme): string
    {
        $tokens = (array) ($theme->tokens['dark'] ?? []);
        $decls = $this->declarations($tokens);

        if ($decls === []) {
            return '';
        }

        return ".dark {\n".implode("\n", $decls)."\n}";
    }

    /**
     * Only emit --font-sans when the theme picks a family AND a matching
     * uploaded face exists. Otherwise the theme inherits the build-time
     * --font-sans (Instrument Sans) so the seeded presets/Default stay exact.
     */
    protected function fontSansDeclaration(Theme $theme): ?string
    {
        $family = $theme->font_family;
        if ($family === null || trim($family) === '') {
            return null;
        }

        $hasFace = $theme->fonts->contains(
            fn (ThemeFont $f): bool => mb_strtolower($f->family) === mb_strtolower($family)
        );
        if (! $hasFace) {
            return null;
        }

        $safe = $this->safeFamily($family);
        if ($safe === null) {
            return null;
        }

        $fallback = (string) config('themes.defaults.font_fallback', 'ui-sans-serif, system-ui, sans-serif');

        return "    --font-sans: '{$safe}', {$fallback};";
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @return array<int, string>
     */
    protected function declarations(array $tokens): array
    {
        $allowed = $this->allowedTokenKeys();
        $decls = [];

        foreach ($tokens as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                continue;
            }
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }
            $clean = $this->safeValue((string) $value);
            if ($clean === null) {
                continue;
            }
            $decls[] = "    {$key}: {$clean};";
        }

        return $decls;
    }

    protected function customCss(Theme $theme): string
    {
        $path = $theme->custom_css_path;
        if ($path === null || $path === '') {
            return '';
        }

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($path)) {
            return '';
        }

        $css = (string) $disk->get($path);

        return trim($this->stripRemoteImports($css));
    }

    /**
     * Strip remote @import (http(s):// or protocol-relative //) so a custom
     * stylesheet can't pull cross-origin styles past CSP. First-party imports
     * are left intact.
     */
    public function stripRemoteImports(string $css): string
    {
        return (string) preg_replace(
            '/@import\s+(?:url\(\s*)?["\']?\s*(?:https?:)?\/\/[^;]*;/i',
            '',
            $css,
        );
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

    protected function safeFamily(string $family): ?string
    {
        $family = trim($family);
        if ($family === '') {
            return null;
        }
        // Drop quotes / structure chars; a font family name is plain text.
        if (preg_match("/[;{}<>@'\"\\\\]/", $family)) {
            return null;
        }

        return $family;
    }

    protected function safeUrl(string $url): string
    {
        // App-generated /storage URL — strip quotes/parens defensively.
        return str_replace(["'", '"', '(', ')', "\n", "\r"], '', $url);
    }

    protected function disk(): string
    {
        return (string) config('themes.storage_disk', 'public');
    }
}
