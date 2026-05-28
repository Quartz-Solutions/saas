<?php

/*
|--------------------------------------------------------------------------
| Themes
|--------------------------------------------------------------------------
|
| Drives the DB-owned theme catalog (see agent-os/product/custom_theme.md).
| `groups` defines the editable token set grouped for the admin UI; `defaults`
| mirror the build-time tokens in resources/css/app.css so the seeded "Default"
| theme renders identically to the un-themed app and so any token a theme
| omits falls back to a sensible value in the editor.
|
*/

return [

    // Cache discipline mirrors App\Support\Admin\AppSettingsService.
    'cache_key' => 'theme.active',
    'cache_ttl' => 86400,

    // All theme artifacts (compiled CSS, custom CSS, fonts) live on this disk.
    'storage_disk' => 'public',

    'radius' => [
        'min' => 0,
        'max' => 1.5,
        'step' => 0.025,
    ],

    // Editable color tokens, grouped for the editor. Every key here is a CSS
    // custom property already consumed by app.css / shadcn components. Each is
    // edited per light + dark map. Radius + font live outside this list (Shape
    // + Typography tabs).
    'groups' => [
        'brand' => [
            'label' => 'Brand',
            'description' => 'Primary action color, accent, and focus ring.',
            'tokens' => [
                '--primary' => 'Primary',
                '--primary-foreground' => 'Primary text',
                '--accent' => 'Accent',
                '--accent-foreground' => 'Accent text',
                '--ring' => 'Focus ring',
            ],
        ],
        'surfaces' => [
            'label' => 'Surfaces',
            'description' => 'Page, card, popover, and form surfaces.',
            'tokens' => [
                '--background' => 'Background',
                '--foreground' => 'Foreground',
                '--card' => 'Card',
                '--card-foreground' => 'Card text',
                '--popover' => 'Popover',
                '--popover-foreground' => 'Popover text',
                '--muted' => 'Muted',
                '--muted-foreground' => 'Muted text',
                '--secondary' => 'Secondary',
                '--secondary-foreground' => 'Secondary text',
                '--border' => 'Border',
                '--input' => 'Input border',
            ],
        ],
        'sidebar' => [
            'label' => 'Sidebar',
            'description' => 'App shell navigation surface.',
            'tokens' => [
                '--sidebar' => 'Sidebar',
                '--sidebar-foreground' => 'Sidebar text',
                '--sidebar-primary' => 'Sidebar primary',
                '--sidebar-primary-foreground' => 'Sidebar primary text',
                '--sidebar-accent' => 'Sidebar accent',
                '--sidebar-accent-foreground' => 'Sidebar accent text',
                '--sidebar-border' => 'Sidebar border',
                '--sidebar-ring' => 'Sidebar ring',
            ],
        ],
        'charts' => [
            'label' => 'Charts',
            'description' => 'Recharts series palette (dashboard analytics).',
            'tokens' => [
                '--chart-1' => 'Chart 1',
                '--chart-2' => 'Chart 2',
                '--chart-3' => 'Chart 3',
                '--chart-4' => 'Chart 4',
                '--chart-5' => 'Chart 5',
            ],
        ],
        'status' => [
            'label' => 'Status',
            'description' => 'Destructive / danger color.',
            'tokens' => [
                '--destructive' => 'Destructive',
                '--destructive-foreground' => 'Destructive text',
            ],
        ],
    ],

    // Build-time defaults — kept byte-for-byte in sync with the :root / .dark
    // blocks in resources/css/app.css. The "Default" preset seeds from these.
    'defaults' => [
        'radius' => '0.625rem',
        'font_family' => 'Instrument Sans',
        'font_fallback' => 'ui-sans-serif, system-ui, sans-serif',
        'light' => [
            '--background' => 'oklch(1 0 0)',
            '--foreground' => 'oklch(0.145 0 0)',
            '--card' => 'oklch(1 0 0)',
            '--card-foreground' => 'oklch(0.145 0 0)',
            '--popover' => 'oklch(1 0 0)',
            '--popover-foreground' => 'oklch(0.145 0 0)',
            '--primary' => 'oklch(0.205 0 0)',
            '--primary-foreground' => 'oklch(0.985 0 0)',
            '--secondary' => 'oklch(0.97 0 0)',
            '--secondary-foreground' => 'oklch(0.205 0 0)',
            '--muted' => 'oklch(0.97 0 0)',
            '--muted-foreground' => 'oklch(0.556 0 0)',
            '--accent' => 'oklch(0.97 0 0)',
            '--accent-foreground' => 'oklch(0.205 0 0)',
            '--destructive' => 'oklch(0.577 0.245 27.325)',
            '--destructive-foreground' => 'oklch(0.577 0.245 27.325)',
            '--border' => 'oklch(0.922 0 0)',
            '--input' => 'oklch(0.922 0 0)',
            '--ring' => 'oklch(0.87 0 0)',
            '--chart-1' => 'oklch(0.646 0.222 41.116)',
            '--chart-2' => 'oklch(0.6 0.118 184.704)',
            '--chart-3' => 'oklch(0.398 0.07 227.392)',
            '--chart-4' => 'oklch(0.828 0.189 84.429)',
            '--chart-5' => 'oklch(0.769 0.188 70.08)',
            '--sidebar' => 'oklch(0.985 0 0)',
            '--sidebar-foreground' => 'oklch(0.145 0 0)',
            '--sidebar-primary' => 'oklch(0.205 0 0)',
            '--sidebar-primary-foreground' => 'oklch(0.985 0 0)',
            '--sidebar-accent' => 'oklch(0.97 0 0)',
            '--sidebar-accent-foreground' => 'oklch(0.205 0 0)',
            '--sidebar-border' => 'oklch(0.922 0 0)',
            '--sidebar-ring' => 'oklch(0.87 0 0)',
        ],
        'dark' => [
            '--background' => 'oklch(0.145 0 0)',
            '--foreground' => 'oklch(0.985 0 0)',
            '--card' => 'oklch(0.145 0 0)',
            '--card-foreground' => 'oklch(0.985 0 0)',
            '--popover' => 'oklch(0.145 0 0)',
            '--popover-foreground' => 'oklch(0.985 0 0)',
            '--primary' => 'oklch(0.985 0 0)',
            '--primary-foreground' => 'oklch(0.205 0 0)',
            '--secondary' => 'oklch(0.269 0 0)',
            '--secondary-foreground' => 'oklch(0.985 0 0)',
            '--muted' => 'oklch(0.269 0 0)',
            '--muted-foreground' => 'oklch(0.708 0 0)',
            '--accent' => 'oklch(0.269 0 0)',
            '--accent-foreground' => 'oklch(0.985 0 0)',
            '--destructive' => 'oklch(0.396 0.141 25.723)',
            '--destructive-foreground' => 'oklch(0.637 0.237 25.331)',
            '--border' => 'oklch(0.269 0 0)',
            '--input' => 'oklch(0.269 0 0)',
            '--ring' => 'oklch(0.439 0 0)',
            '--chart-1' => 'oklch(0.488 0.243 264.376)',
            '--chart-2' => 'oklch(0.696 0.17 162.48)',
            '--chart-3' => 'oklch(0.769 0.188 70.08)',
            '--chart-4' => 'oklch(0.627 0.265 303.9)',
            '--chart-5' => 'oklch(0.645 0.246 16.439)',
            '--sidebar' => 'oklch(0.205 0 0)',
            '--sidebar-foreground' => 'oklch(0.985 0 0)',
            '--sidebar-primary' => 'oklch(0.985 0 0)',
            '--sidebar-primary-foreground' => 'oklch(0.985 0 0)',
            '--sidebar-accent' => 'oklch(0.269 0 0)',
            '--sidebar-accent-foreground' => 'oklch(0.985 0 0)',
            '--sidebar-border' => 'oklch(0.269 0 0)',
            '--sidebar-ring' => 'oklch(0.439 0 0)',
        ],
    ],

    // Custom CSS escape hatch (Phase C).
    'custom_css' => [
        'max_bytes' => 262144, // 256 KB
    ],

    // Google-Fonts ZIP import (Phase D). The extractor enforces every cap here.
    'fonts' => [
        'max_archive_bytes' => 20 * 1024 * 1024,  // 20 MB upload cap
        'max_entries' => 200,                      // zip-bomb guard (entry count)
        'max_file_bytes' => 5 * 1024 * 1024,       // per-extracted-file cap
        'max_total_bytes' => 60 * 1024 * 1024,     // total uncompressed cap
        'allowed_extensions' => ['woff2', 'woff', 'ttf', 'otf'],
    ],
];
