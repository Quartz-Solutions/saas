<?php

namespace App\Support\Theme;

use App\Models\Theme;
use App\Models\ThemeFont;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Extracts a Google-Fonts ZIP into self-hosted font faces (see
 * agent-os/product/custom_theme.md §8). This is the highest-risk surface, so
 * every entry is validated before anything is persisted:
 *
 *   - zip-slip / absolute-path guard (reject `..`, leading `/`, drive letters)
 *   - extension whitelist (font types only — license/readme/images ignored)
 *   - entry-count + per-file + total-uncompressed caps (zip-bomb guard)
 *
 * Files are never extracted to a path derived from the archive name; contents
 * are read in-memory and re-stored under hashed names in themes/{id}/fonts/.
 */
class FontArchiveImporter
{
    /**
     * Validate + extract + persist the archive's font faces for a theme.
     *
     * @return Collection<int, ThemeFont>
     */
    public function import(Theme $theme, string $zipPath): Collection
    {
        $entries = $this->readArchive($zipPath);
        $disk = Storage::disk($this->disk());

        /** @var Collection<int, ThemeFont> $created */
        $created = collect();

        foreach ($entries as $entry) {
            $filename = Str::random(40).'.'.$entry['format'];
            $path = "themes/{$theme->id}/fonts/{$filename}";
            $disk->put($path, $entry['contents']);

            $created->push(ThemeFont::create([
                'theme_id' => $theme->id,
                'family' => $entry['family'],
                'weight' => $entry['weight'],
                'style' => $entry['style'],
                'format' => $entry['format'],
                'path' => $path,
                'original_filename' => $entry['original_filename'],
                'size_bytes' => $entry['size'],
            ]));
        }

        return $created;
    }

    /**
     * Read + validate every font entry. Throws on a malicious or oversized
     * archive; ignores non-font entries; returns the surviving faces.
     *
     * @return array<int, array{original_filename: string, contents: string, size: int, family: string, weight: string, style: string, format: string}>
     */
    public function readArchive(string $zipPath): array
    {
        $caps = (array) config('themes.fonts', []);
        $allowed = (array) ($caps['allowed_extensions'] ?? ['woff2', 'woff', 'ttf', 'otf']);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the uploaded archive.');
        }

        try {
            if ($zip->numFiles > (int) ($caps['max_entries'] ?? 200)) {
                throw new RuntimeException('Archive has too many entries.');
            }

            $entries = [];
            $total = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }

                $name = (string) $stat['name'];

                if (str_ends_with($name, '/')) {
                    continue; // directory entry
                }

                if ($this->isUnsafePath($name)) {
                    throw new RuntimeException("Unsafe path in archive: {$name}");
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($ext, $allowed, true)) {
                    continue; // license, readme, images, etc.
                }

                $size = (int) $stat['size'];
                if ($size > (int) ($caps['max_file_bytes'] ?? PHP_INT_MAX)) {
                    throw new RuntimeException("Font file exceeds the per-file size limit: {$name}");
                }

                $total += $size;
                if ($total > (int) ($caps['max_total_bytes'] ?? PHP_INT_MAX)) {
                    throw new RuntimeException('Archive uncompressed size exceeds the limit.');
                }

                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    continue;
                }

                $meta = self::parseFilename(basename($name));
                $entries[] = [
                    'original_filename' => basename($name),
                    'contents' => $contents,
                    'size' => $size,
                    'family' => $meta['family'],
                    'weight' => $meta['weight'],
                    'style' => $meta['style'],
                    'format' => $ext,
                ];
            }

            if ($entries === []) {
                throw new RuntimeException('No font files (.woff2 / .woff / .ttf / .otf) found in the archive.');
            }

            return $entries;
        } finally {
            $zip->close();
        }
    }

    /**
     * Parse a Google-Fonts filename into family / weight / style / format.
     *
     *   Roboto-BoldItalic.ttf   → Roboto / 700 / italic
     *   OpenSans-Regular.woff2  → Open Sans / 400 / normal
     *   Roboto[wght].ttf        → Roboto / "100 900" / normal (variable)
     *
     * @return array{family: string, weight: string, style: string, format: string}
     */
    public static function parseFilename(string $filename): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $isVariable = str_contains($base, '[');
        $family = $base;
        $variant = '';

        if ($isVariable) {
            $family = substr($base, 0, (int) strpos($base, '['));
        } elseif (str_contains($base, '-')) {
            [$family, $variant] = explode('-', $base, 2);
        }

        $lower = strtolower($base);
        $style = str_contains($lower, 'italic') ? 'italic' : 'normal';
        $weight = $isVariable ? '100 900' : self::weightFromName($variant);

        return [
            'family' => self::humanizeFamily($family),
            'weight' => $weight,
            'style' => $style,
            'format' => $ext,
        ];
    }

    protected function isUnsafePath(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);

        if (str_starts_with($normalized, '/')) {
            return true; // absolute
        }
        if (preg_match('#^[a-zA-Z]:#', $normalized)) {
            return true; // windows drive letter
        }
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                return true; // traversal
            }
        }

        return false;
    }

    protected static function humanizeFamily(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 'Custom';
        }

        // Split camelCase boundaries: OpenSans -> Open Sans; Roboto -> Roboto.
        return (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $raw);
    }

    protected static function weightFromName(string $variant): string
    {
        $v = str_replace(['italic', '-', '_', ' '], '', strtolower($variant));

        return match (true) {
            $v === 'thin' => '100',
            in_array($v, ['extralight', 'ultralight'], true) => '200',
            $v === 'light' => '300',
            in_array($v, ['', 'regular', 'normal'], true) => '400',
            $v === 'medium' => '500',
            in_array($v, ['semibold', 'demibold'], true) => '600',
            $v === 'bold' => '700',
            in_array($v, ['extrabold', 'ultrabold'], true) => '800',
            in_array($v, ['black', 'heavy'], true) => '900',
            ctype_digit($v) => $v,
            default => '400',
        };
    }

    protected function disk(): string
    {
        return (string) config('themes.storage_disk', 'public');
    }
}
