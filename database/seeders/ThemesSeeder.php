<?php

namespace Database\Seeders;

use App\Models\Theme;
use App\Support\Theme\ThemeService;
use Illuminate\Database\Seeder;

/**
 * Seeds the built-in theme catalog: the "Default" (Quartz) theme — a verbatim
 * copy of resources/css/app.css so the app renders identically out of the box —
 * plus five presets (Emerald / Indigo / Midnight / Microsoft Fluent / Material)
 * tuned from product screenshots. Idempotent (updateOrCreate by slug); never
 * clobbers an admin's active choice on re-seed. All are presets (clone-to-edit).
 * Microsoft Fluent (Segoe + square corners) and Material (Roboto) ship custom
 * CSS sheets.
 */
class ThemesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var ThemeService $service */
        $service = app(ThemeService::class);

        foreach ($this->themes() as $spec) {
            $existed = Theme::query()->where('slug', $spec['slug'])->exists();

            $theme = Theme::updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'name' => $spec['name'],
                    'description' => $spec['description'],
                    'is_preset' => true,
                    'mode_hint' => $spec['mode_hint'],
                    'tokens' => $spec['tokens'],
                    'radius' => $spec['radius'],
                    'font_family' => $spec['font_family'],
                ],
            );

            // Seed the active flag only on first creation so re-seeding never
            // overrides an admin's later activation choice.
            if (! $existed && ! empty($spec['active'])) {
                $theme->forceFill(['is_active' => true])->save();
            }

            // storeCustomCss writes the stylesheet file + recompiles; plain
            // compile() for presets without a custom escape-hatch sheet.
            if (array_key_exists('custom_css', $spec)) {
                $service->storeCustomCss($theme->fresh() ?? $theme, (string) $spec['custom_css']);
            } else {
                $service->compile($theme->fresh() ?? $theme);
            }
        }

        // Safety net: the app must always have exactly one active theme.
        if (Theme::query()->where('is_active', true)->doesntExist()) {
            Theme::query()->where('slug', 'default')->first()
                ?->forceFill(['is_active' => true])->save();
        }

        $service->invalidate();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function themes(): array
    {
        $defaults = (array) config('themes.defaults');
        $radius = (string) ($defaults['radius'] ?? '0.625rem');
        $font = (string) ($defaults['font_family'] ?? 'Instrument Sans');

        return [
            [
                'slug' => 'default',
                'name' => 'Default (Quartz)',
                'description' => 'The built-in neutral grayscale theme. Matches the un-themed app exactly.',
                'mode_hint' => 'both',
                'active' => true,
                'radius' => $radius,
                'font_family' => $font,
                'tokens' => [
                    'light' => (array) ($defaults['light'] ?? []),
                    'dark' => (array) ($defaults['dark'] ?? []),
                ],
            ],
            [
                'slug' => 'emerald',
                'name' => 'Emerald',
                'description' => 'Finexy-style fintech look — bright lime brand on a soft gray canvas with white cards and a lime/black chart ramp.',
                'mode_hint' => 'both',
                'radius' => '1rem',
                'font_family' => $font,
                'tokens' => $this->merge($defaults, [
                    'light' => [
                        '--background' => 'oklch(0.965 0.003 145)',
                        '--foreground' => 'oklch(0.2 0.01 150)',
                        '--card' => 'oklch(1 0 0)',
                        '--card-foreground' => 'oklch(0.2 0.01 150)',
                        '--popover' => 'oklch(1 0 0)',
                        '--popover-foreground' => 'oklch(0.2 0.01 150)',
                        '--primary' => 'oklch(0.87 0.22 128)',
                        '--primary-foreground' => 'oklch(0.24 0.04 145)',
                        '--secondary' => 'oklch(0.95 0.02 128)',
                        '--secondary-foreground' => 'oklch(0.28 0.04 150)',
                        '--muted' => 'oklch(0.96 0.004 145)',
                        '--muted-foreground' => 'oklch(0.52 0.02 150)',
                        '--accent' => 'oklch(0.94 0.07 128)',
                        '--accent-foreground' => 'oklch(0.32 0.1 150)',
                        '--border' => 'oklch(0.92 0.005 145)',
                        '--input' => 'oklch(0.92 0.005 145)',
                        '--ring' => 'oklch(0.87 0.22 128)',
                        '--chart-1' => 'oklch(0.87 0.22 128)',
                        '--chart-2' => 'oklch(0.24 0.02 150)',
                        '--chart-3' => 'oklch(0.6 0.15 145)',
                        '--chart-4' => 'oklch(0.45 0.09 150)',
                        '--chart-5' => 'oklch(0.78 0.16 130)',
                        '--sidebar' => 'oklch(0.99 0.002 145)',
                        '--sidebar-foreground' => 'oklch(0.22 0.01 150)',
                        '--sidebar-primary' => 'oklch(0.87 0.22 128)',
                        '--sidebar-primary-foreground' => 'oklch(0.24 0.04 145)',
                        '--sidebar-accent' => 'oklch(0.94 0.07 128)',
                        '--sidebar-accent-foreground' => 'oklch(0.32 0.1 150)',
                        '--sidebar-border' => 'oklch(0.92 0.005 145)',
                        '--sidebar-ring' => 'oklch(0.87 0.22 128)',
                    ],
                    'dark' => [
                        '--background' => 'oklch(0.17 0.008 150)',
                        '--foreground' => 'oklch(0.96 0.01 135)',
                        '--card' => 'oklch(0.21 0.01 150)',
                        '--card-foreground' => 'oklch(0.96 0.01 135)',
                        '--popover' => 'oklch(0.21 0.01 150)',
                        '--popover-foreground' => 'oklch(0.96 0.01 135)',
                        '--primary' => 'oklch(0.87 0.22 128)',
                        '--primary-foreground' => 'oklch(0.2 0.03 145)',
                        '--secondary' => 'oklch(0.27 0.02 150)',
                        '--secondary-foreground' => 'oklch(0.96 0.01 135)',
                        '--muted' => 'oklch(0.27 0.02 150)',
                        '--muted-foreground' => 'oklch(0.72 0.02 140)',
                        '--accent' => 'oklch(0.32 0.06 140)',
                        '--accent-foreground' => 'oklch(0.93 0.07 128)',
                        '--border' => 'oklch(0.28 0.015 150)',
                        '--input' => 'oklch(0.28 0.015 150)',
                        '--ring' => 'oklch(0.87 0.22 128)',
                        '--chart-1' => 'oklch(0.87 0.22 128)',
                        '--chart-2' => 'oklch(0.85 0.005 150)',
                        '--chart-3' => 'oklch(0.65 0.15 145)',
                        '--chart-4' => 'oklch(0.5 0.1 150)',
                        '--chart-5' => 'oklch(0.78 0.16 130)',
                        '--sidebar' => 'oklch(0.15 0.008 150)',
                        '--sidebar-foreground' => 'oklch(0.96 0.01 135)',
                        '--sidebar-primary' => 'oklch(0.87 0.22 128)',
                        '--sidebar-primary-foreground' => 'oklch(0.2 0.03 145)',
                        '--sidebar-accent' => 'oklch(0.27 0.02 150)',
                        '--sidebar-accent-foreground' => 'oklch(0.96 0.01 135)',
                        '--sidebar-border' => 'oklch(0.28 0.015 150)',
                        '--sidebar-ring' => 'oklch(0.87 0.22 128)',
                    ],
                ]),
            ],
            [
                'slug' => 'indigo',
                'name' => 'Indigo',
                'description' => 'Metrics-style SaaS look — royal indigo brand on a soft lavender-gray canvas, white cards, light-lavender active nav, indigo→lavender charts with gold/coral accents.',
                'mode_hint' => 'both',
                'radius' => '0.875rem',
                'font_family' => $font,
                'tokens' => $this->merge($defaults, [
                    'light' => [
                        '--background' => 'oklch(0.97 0.006 285)',
                        '--foreground' => 'oklch(0.21 0.02 280)',
                        '--card' => 'oklch(1 0 0)',
                        '--card-foreground' => 'oklch(0.21 0.02 280)',
                        '--popover' => 'oklch(1 0 0)',
                        '--popover-foreground' => 'oklch(0.21 0.02 280)',
                        '--primary' => 'oklch(0.51 0.23 277)',
                        '--primary-foreground' => 'oklch(0.99 0.005 285)',
                        '--secondary' => 'oklch(0.95 0.02 285)',
                        '--secondary-foreground' => 'oklch(0.3 0.05 280)',
                        '--muted' => 'oklch(0.95 0.015 285)',
                        '--muted-foreground' => 'oklch(0.52 0.03 280)',
                        '--accent' => 'oklch(0.93 0.045 285)',
                        '--accent-foreground' => 'oklch(0.45 0.2 277)',
                        '--destructive' => 'oklch(0.62 0.22 25)',
                        '--destructive-foreground' => 'oklch(0.99 0 0)',
                        '--border' => 'oklch(0.92 0.01 285)',
                        '--input' => 'oklch(0.92 0.01 285)',
                        '--ring' => 'oklch(0.51 0.23 277)',
                        '--chart-1' => 'oklch(0.51 0.23 277)',
                        '--chart-2' => 'oklch(0.7 0.13 280)',
                        '--chart-3' => 'oklch(0.83 0.08 285)',
                        '--chart-4' => 'oklch(0.8 0.16 75)',
                        '--chart-5' => 'oklch(0.65 0.2 35)',
                        '--sidebar' => 'oklch(1 0 0)',
                        '--sidebar-foreground' => 'oklch(0.3 0.02 280)',
                        '--sidebar-primary' => 'oklch(0.51 0.23 277)',
                        '--sidebar-primary-foreground' => 'oklch(0.99 0.005 285)',
                        '--sidebar-accent' => 'oklch(0.93 0.045 285)',
                        '--sidebar-accent-foreground' => 'oklch(0.45 0.2 277)',
                        '--sidebar-border' => 'oklch(0.93 0.01 285)',
                        '--sidebar-ring' => 'oklch(0.51 0.23 277)',
                    ],
                    'dark' => [
                        '--background' => 'oklch(0.18 0.02 280)',
                        '--foreground' => 'oklch(0.96 0.01 285)',
                        '--card' => 'oklch(0.22 0.025 280)',
                        '--card-foreground' => 'oklch(0.96 0.01 285)',
                        '--popover' => 'oklch(0.22 0.025 280)',
                        '--popover-foreground' => 'oklch(0.96 0.01 285)',
                        '--primary' => 'oklch(0.62 0.2 277)',
                        '--primary-foreground' => 'oklch(0.99 0.005 285)',
                        '--secondary' => 'oklch(0.28 0.03 280)',
                        '--secondary-foreground' => 'oklch(0.96 0.01 285)',
                        '--muted' => 'oklch(0.28 0.03 280)',
                        '--muted-foreground' => 'oklch(0.72 0.03 285)',
                        '--accent' => 'oklch(0.32 0.07 280)',
                        '--accent-foreground' => 'oklch(0.9 0.05 285)',
                        '--destructive' => 'oklch(0.62 0.21 25)',
                        '--destructive-foreground' => 'oklch(0.96 0.01 285)',
                        '--border' => 'oklch(0.3 0.02 280)',
                        '--input' => 'oklch(0.3 0.02 280)',
                        '--ring' => 'oklch(0.62 0.2 277)',
                        '--chart-1' => 'oklch(0.62 0.2 277)',
                        '--chart-2' => 'oklch(0.72 0.13 280)',
                        '--chart-3' => 'oklch(0.82 0.08 285)',
                        '--chart-4' => 'oklch(0.8 0.16 75)',
                        '--chart-5' => 'oklch(0.68 0.2 35)',
                        '--sidebar' => 'oklch(0.16 0.02 280)',
                        '--sidebar-foreground' => 'oklch(0.96 0.01 285)',
                        '--sidebar-primary' => 'oklch(0.62 0.2 277)',
                        '--sidebar-primary-foreground' => 'oklch(0.99 0.005 285)',
                        '--sidebar-accent' => 'oklch(0.3 0.05 280)',
                        '--sidebar-accent-foreground' => 'oklch(0.92 0.04 285)',
                        '--sidebar-border' => 'oklch(0.3 0.02 280)',
                        '--sidebar-ring' => 'oklch(0.62 0.2 277)',
                    ],
                ]),
            ],
            [
                'slug' => 'midnight',
                'name' => 'Midnight',
                'description' => 'Dark-first navy with a violet accent. Designed around the dark map.',
                'mode_hint' => 'dark',
                'radius' => $radius,
                'font_family' => $font,
                'tokens' => $this->merge($defaults, [
                    'light' => [
                        '--primary' => 'oklch(0.55 0.22 300)',
                        '--primary-foreground' => 'oklch(0.985 0.01 300)',
                        '--accent' => 'oklch(0.95 0.04 300)',
                        '--accent-foreground' => 'oklch(0.45 0.18 300)',
                        '--ring' => 'oklch(0.55 0.22 300)',
                        '--sidebar-primary' => 'oklch(0.55 0.22 300)',
                        '--sidebar-primary-foreground' => 'oklch(0.985 0.01 300)',
                        '--sidebar-accent' => 'oklch(0.95 0.04 300)',
                        '--sidebar-accent-foreground' => 'oklch(0.45 0.18 300)',
                        '--sidebar-ring' => 'oklch(0.55 0.22 300)',
                        '--chart-1' => 'oklch(0.55 0.22 300)',
                        '--chart-2' => 'oklch(0.6 0.18 270)',
                        '--chart-3' => 'oklch(0.65 0.16 330)',
                        '--chart-4' => 'oklch(0.6 0.15 240)',
                        '--chart-5' => 'oklch(0.7 0.14 310)',
                    ],
                    'dark' => [
                        '--background' => 'oklch(0.18 0.02 280)',
                        '--foreground' => 'oklch(0.96 0.01 280)',
                        '--card' => 'oklch(0.21 0.025 280)',
                        '--card-foreground' => 'oklch(0.96 0.01 280)',
                        '--popover' => 'oklch(0.21 0.025 280)',
                        '--popover-foreground' => 'oklch(0.96 0.01 280)',
                        '--primary' => 'oklch(0.62 0.22 300)',
                        '--primary-foreground' => 'oklch(0.16 0.02 300)',
                        '--secondary' => 'oklch(0.27 0.03 280)',
                        '--secondary-foreground' => 'oklch(0.96 0.01 280)',
                        '--muted' => 'oklch(0.27 0.03 280)',
                        '--muted-foreground' => 'oklch(0.72 0.03 280)',
                        '--accent' => 'oklch(0.3 0.06 300)',
                        '--accent-foreground' => 'oklch(0.95 0.03 300)',
                        '--border' => 'oklch(0.28 0.03 280)',
                        '--input' => 'oklch(0.28 0.03 280)',
                        '--ring' => 'oklch(0.62 0.22 300)',
                        '--chart-1' => 'oklch(0.62 0.22 300)',
                        '--chart-2' => 'oklch(0.66 0.18 270)',
                        '--chart-3' => 'oklch(0.72 0.16 330)',
                        '--chart-4' => 'oklch(0.64 0.15 240)',
                        '--chart-5' => 'oklch(0.76 0.14 310)',
                        '--sidebar' => 'oklch(0.16 0.02 280)',
                        '--sidebar-foreground' => 'oklch(0.96 0.01 280)',
                        '--sidebar-primary' => 'oklch(0.62 0.22 300)',
                        '--sidebar-primary-foreground' => 'oklch(0.16 0.02 300)',
                        '--sidebar-accent' => 'oklch(0.27 0.03 280)',
                        '--sidebar-accent-foreground' => 'oklch(0.96 0.01 280)',
                        '--sidebar-border' => 'oklch(0.28 0.03 280)',
                        '--sidebar-ring' => 'oklch(0.62 0.22 300)',
                    ],
                ]),
            ],
            [
                'slug' => 'microsoft',
                'name' => 'Microsoft Fluent',
                'description' => 'Fluent-style — Microsoft blue on neutral grays, Segoe UI, and crisp square (zero-radius) corners. Uses custom CSS for the system font + square corners.',
                'mode_hint' => 'both',
                'radius' => '0rem',
                'font_family' => null,
                'custom_css' => $this->microsoftCss(),
                'tokens' => $this->merge($defaults, [
                    'light' => [
                        '--background' => 'oklch(0.97 0.002 250)',
                        '--foreground' => 'oklch(0.22 0.01 250)',
                        '--card' => 'oklch(1 0 0)',
                        '--card-foreground' => 'oklch(0.22 0.01 250)',
                        '--popover' => 'oklch(1 0 0)',
                        '--popover-foreground' => 'oklch(0.22 0.01 250)',
                        '--primary' => 'oklch(0.52 0.15 250)',
                        '--primary-foreground' => 'oklch(0.99 0 0)',
                        '--secondary' => 'oklch(0.95 0.003 250)',
                        '--secondary-foreground' => 'oklch(0.3 0.01 250)',
                        '--muted' => 'oklch(0.95 0.003 250)',
                        '--muted-foreground' => 'oklch(0.5 0.01 250)',
                        '--accent' => 'oklch(0.93 0.03 250)',
                        '--accent-foreground' => 'oklch(0.4 0.12 250)',
                        '--destructive' => 'oklch(0.55 0.2 27)',
                        '--destructive-foreground' => 'oklch(0.99 0 0)',
                        '--border' => 'oklch(0.88 0.004 250)',
                        '--input' => 'oklch(0.82 0.005 250)',
                        '--ring' => 'oklch(0.52 0.15 250)',
                        '--chart-1' => 'oklch(0.52 0.15 250)',
                        '--chart-2' => 'oklch(0.55 0.13 150)',
                        '--chart-3' => 'oklch(0.8 0.15 85)',
                        '--chart-4' => 'oklch(0.58 0.2 27)',
                        '--chart-5' => 'oklch(0.5 0.13 300)',
                        '--sidebar' => 'oklch(0.985 0.002 250)',
                        '--sidebar-foreground' => 'oklch(0.25 0.01 250)',
                        '--sidebar-primary' => 'oklch(0.52 0.15 250)',
                        '--sidebar-primary-foreground' => 'oklch(0.99 0 0)',
                        '--sidebar-accent' => 'oklch(0.93 0.03 250)',
                        '--sidebar-accent-foreground' => 'oklch(0.4 0.12 250)',
                        '--sidebar-border' => 'oklch(0.9 0.004 250)',
                        '--sidebar-ring' => 'oklch(0.52 0.15 250)',
                    ],
                    'dark' => [
                        '--background' => 'oklch(0.2 0.005 250)',
                        '--foreground' => 'oklch(0.96 0.003 250)',
                        '--card' => 'oklch(0.24 0.006 250)',
                        '--card-foreground' => 'oklch(0.96 0.003 250)',
                        '--popover' => 'oklch(0.24 0.006 250)',
                        '--popover-foreground' => 'oklch(0.96 0.003 250)',
                        '--primary' => 'oklch(0.65 0.15 250)',
                        '--primary-foreground' => 'oklch(0.15 0.01 250)',
                        '--secondary' => 'oklch(0.3 0.008 250)',
                        '--secondary-foreground' => 'oklch(0.96 0.003 250)',
                        '--muted' => 'oklch(0.3 0.008 250)',
                        '--muted-foreground' => 'oklch(0.72 0.006 250)',
                        '--accent' => 'oklch(0.34 0.04 250)',
                        '--accent-foreground' => 'oklch(0.9 0.05 250)',
                        '--destructive' => 'oklch(0.6 0.2 27)',
                        '--destructive-foreground' => 'oklch(0.96 0.003 250)',
                        '--border' => 'oklch(0.33 0.006 250)',
                        '--input' => 'oklch(0.4 0.008 250)',
                        '--ring' => 'oklch(0.65 0.15 250)',
                        '--chart-1' => 'oklch(0.65 0.15 250)',
                        '--chart-2' => 'oklch(0.65 0.14 150)',
                        '--chart-3' => 'oklch(0.82 0.15 85)',
                        '--chart-4' => 'oklch(0.62 0.2 27)',
                        '--chart-5' => 'oklch(0.65 0.13 300)',
                        '--sidebar' => 'oklch(0.17 0.005 250)',
                        '--sidebar-foreground' => 'oklch(0.96 0.003 250)',
                        '--sidebar-primary' => 'oklch(0.65 0.15 250)',
                        '--sidebar-primary-foreground' => 'oklch(0.15 0.01 250)',
                        '--sidebar-accent' => 'oklch(0.32 0.03 250)',
                        '--sidebar-accent-foreground' => 'oklch(0.92 0.04 250)',
                        '--sidebar-border' => 'oklch(0.33 0.006 250)',
                        '--sidebar-ring' => 'oklch(0.65 0.15 250)',
                    ],
                ]),
            ],
            [
                'slug' => 'material',
                'name' => 'Material',
                'description' => 'Google Material / MUI — Google-blue brand, the four Google brand colors as the chart ramp, elevated white cards, and Material-rounded corners. Custom CSS sets the Roboto type stack.',
                'mode_hint' => 'both',
                'radius' => '0.75rem',
                'font_family' => null,
                'custom_css' => $this->materialCss(),
                'tokens' => $this->merge($defaults, [
                    'light' => [
                        '--background' => 'oklch(0.98 0.003 280)',
                        '--foreground' => 'oklch(0.22 0.01 280)',
                        '--card' => 'oklch(1 0 0)',
                        '--card-foreground' => 'oklch(0.22 0.01 280)',
                        '--popover' => 'oklch(1 0 0)',
                        '--popover-foreground' => 'oklch(0.22 0.01 280)',
                        '--primary' => 'oklch(0.62 0.19 262)',
                        '--primary-foreground' => 'oklch(0.99 0 0)',
                        '--secondary' => 'oklch(0.95 0.01 280)',
                        '--secondary-foreground' => 'oklch(0.3 0.02 280)',
                        '--muted' => 'oklch(0.96 0.005 280)',
                        '--muted-foreground' => 'oklch(0.5 0.015 280)',
                        '--accent' => 'oklch(0.93 0.04 262)',
                        '--accent-foreground' => 'oklch(0.45 0.16 262)',
                        '--destructive' => 'oklch(0.58 0.22 27)',
                        '--destructive-foreground' => 'oklch(0.99 0 0)',
                        '--border' => 'oklch(0.9 0.005 280)',
                        '--input' => 'oklch(0.9 0.005 280)',
                        '--ring' => 'oklch(0.62 0.19 262)',
                        '--chart-1' => 'oklch(0.62 0.19 262)',
                        '--chart-2' => 'oklch(0.58 0.22 27)',
                        '--chart-3' => 'oklch(0.8 0.16 85)',
                        '--chart-4' => 'oklch(0.64 0.16 150)',
                        '--chart-5' => 'oklch(0.7 0.13 262)',
                        '--sidebar' => 'oklch(0.99 0.002 280)',
                        '--sidebar-foreground' => 'oklch(0.28 0.01 280)',
                        '--sidebar-primary' => 'oklch(0.62 0.19 262)',
                        '--sidebar-primary-foreground' => 'oklch(0.99 0 0)',
                        '--sidebar-accent' => 'oklch(0.93 0.04 262)',
                        '--sidebar-accent-foreground' => 'oklch(0.45 0.16 262)',
                        '--sidebar-border' => 'oklch(0.92 0.005 280)',
                        '--sidebar-ring' => 'oklch(0.62 0.19 262)',
                    ],
                    'dark' => [
                        '--background' => 'oklch(0.19 0.008 280)',
                        '--foreground' => 'oklch(0.96 0.005 280)',
                        '--card' => 'oklch(0.23 0.01 280)',
                        '--card-foreground' => 'oklch(0.96 0.005 280)',
                        '--popover' => 'oklch(0.23 0.01 280)',
                        '--popover-foreground' => 'oklch(0.96 0.005 280)',
                        '--primary' => 'oklch(0.7 0.16 262)',
                        '--primary-foreground' => 'oklch(0.16 0.02 262)',
                        '--secondary' => 'oklch(0.29 0.015 280)',
                        '--secondary-foreground' => 'oklch(0.96 0.005 280)',
                        '--muted' => 'oklch(0.29 0.015 280)',
                        '--muted-foreground' => 'oklch(0.72 0.01 280)',
                        '--accent' => 'oklch(0.33 0.05 262)',
                        '--accent-foreground' => 'oklch(0.9 0.05 262)',
                        '--destructive' => 'oklch(0.62 0.2 27)',
                        '--destructive-foreground' => 'oklch(0.96 0.005 280)',
                        '--border' => 'oklch(0.31 0.01 280)',
                        '--input' => 'oklch(0.31 0.01 280)',
                        '--ring' => 'oklch(0.7 0.16 262)',
                        '--chart-1' => 'oklch(0.7 0.16 262)',
                        '--chart-2' => 'oklch(0.62 0.2 27)',
                        '--chart-3' => 'oklch(0.82 0.16 85)',
                        '--chart-4' => 'oklch(0.68 0.16 150)',
                        '--chart-5' => 'oklch(0.75 0.12 262)',
                        '--sidebar' => 'oklch(0.17 0.008 280)',
                        '--sidebar-foreground' => 'oklch(0.96 0.005 280)',
                        '--sidebar-primary' => 'oklch(0.7 0.16 262)',
                        '--sidebar-primary-foreground' => 'oklch(0.16 0.02 262)',
                        '--sidebar-accent' => 'oklch(0.31 0.04 262)',
                        '--sidebar-accent-foreground' => 'oklch(0.92 0.04 262)',
                        '--sidebar-border' => 'oklch(0.31 0.01 280)',
                        '--sidebar-ring' => 'oklch(0.7 0.16 262)',
                    ],
                ]),
            ],
        ];
    }

    /**
     * Custom CSS for the Microsoft Fluent preset: the Segoe UI system stack
     * (no uploaded faces needed) plus a hard square-corner override for the
     * rounded-full utilities (avatars, switches, pills) that the --radius
     * token can't reach. Demonstrates the custom-CSS escape hatch.
     */
    protected function microsoftCss(): string
    {
        return implode("\n", [
            '/* Microsoft Fluent — system Segoe UI stack + crisp square corners. */',
            ':root {',
            "    --font-sans: 'Segoe UI', 'Segoe UI Web (West European)', -apple-system, BlinkMacSystemFont, system-ui, 'Helvetica Neue', Arial, sans-serif;",
            '}',
            '',
            '/* Fluent uses square corners everywhere. --radius flattens most',
            '   surfaces, but rounded-full utilities keep a fixed radius — force',
            '   every element square. */',
            '*,',
            '*::before,',
            '*::after {',
            '    border-radius: 0 !important;',
            '}',
        ]);
    }

    /**
     * Custom CSS for the Material preset: Google's Roboto type stack (falls
     * back to Helvetica/Arial off-platform). Demonstrates using custom CSS for
     * typography alone.
     */
    protected function materialCss(): string
    {
        return implode("\n", [
            '/* Material — Google Roboto type stack. */',
            ':root {',
            "    --font-sans: 'Roboto', 'Roboto Flex', 'Helvetica Neue', Helvetica, Arial, sans-serif;",
            '}',
        ]);
    }

    /**
     * Merge per-mode token overrides onto the build-time defaults so each
     * preset ships a complete, cohesive light + dark map.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, array<string, string>>  $overrides
     * @return array<string, array<string, string>>
     */
    protected function merge(array $defaults, array $overrides): array
    {
        return [
            'light' => array_merge((array) ($defaults['light'] ?? []), $overrides['light'] ?? []),
            'dark' => array_merge((array) ($defaults['dark'] ?? []), $overrides['dark'] ?? []),
        ];
    }
}
