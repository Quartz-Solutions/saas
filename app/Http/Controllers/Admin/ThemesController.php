<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Themes\CssUpdateRequest;
use App\Http\Requests\Admin\Themes\ThemeStoreRequest;
use App\Http\Requests\Admin\Themes\ThemeUpdateRequest;
use App\Models\Theme;
use App\Support\Theme\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ThemesController extends Controller
{
    public function __construct(private readonly ThemeService $themes) {}

    public function index(): Response
    {
        $themes = Theme::query()
            ->orderByDesc('is_active')
            ->orderByDesc('is_preset')
            ->orderBy('name')
            ->get()
            ->map(fn (Theme $t) => $this->card($t))
            ->all();

        return Inertia::render('admin/themes/index', [
            'themes' => $themes,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/themes/edit', [
            'theme' => null,
            'schema' => $this->themes->tokenSchema(),
        ]);
    }

    public function store(ThemeStoreRequest $request): RedirectResponse
    {
        $theme = $this->themes->create($request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Theme created.')]);

        return to_route('admin.themes.edit', ['theme' => $theme->id]);
    }

    public function edit(Theme $theme): Response
    {
        return Inertia::render('admin/themes/edit', [
            'theme' => $this->serialize($theme->load('fonts')),
            'schema' => $this->themes->tokenSchema(),
        ]);
    }

    public function update(ThemeUpdateRequest $request, Theme $theme): RedirectResponse
    {
        $this->guardPresetEdit($theme);

        $this->themes->update($theme, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Theme updated.')]);

        return back();
    }

    public function destroy(Theme $theme): RedirectResponse
    {
        abort_if($theme->is_preset, 422, 'Preset themes cannot be deleted — clone one to make an editable copy.');
        abort_if($theme->is_active, 422, 'The active theme cannot be deleted. Activate another theme first.');

        $this->themes->delete($theme);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Theme deleted.')]);

        return to_route('admin.themes.index');
    }

    public function activate(Theme $theme): RedirectResponse
    {
        $this->themes->activate($theme);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now the active theme.', ['name' => $theme->name])]);

        return back();
    }

    public function duplicate(Theme $theme): RedirectResponse
    {
        $copy = $this->themes->duplicate($theme);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Theme cloned — edit your copy.')]);

        return to_route('admin.themes.edit', ['theme' => $copy->id]);
    }

    public function updateCss(CssUpdateRequest $request, Theme $theme): RedirectResponse
    {
        $this->guardPresetEdit($theme);

        $css = $request->file('file') ?? (string) $request->input('css', '');
        $this->themes->storeCustomCss($theme, $css);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom CSS saved.')]);

        return back();
    }

    /**
     * Presets are clone-to-edit — block destructive edits to them.
     */
    protected function guardPresetEdit(Theme $theme): void
    {
        abort_if($theme->is_preset, 422, 'Preset themes are read-only. Clone one to make an editable copy.');
    }

    /**
     * Gallery-card payload — just enough to render swatches + actions.
     *
     * @return array<string, mixed>
     */
    protected function card(Theme $theme): array
    {
        $light = (array) ($theme->tokens['light'] ?? []);
        $dark = (array) ($theme->tokens['dark'] ?? []);

        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'is_active' => (bool) $theme->is_active,
            'is_preset' => (bool) $theme->is_preset,
            'mode_hint' => $theme->mode_hint,
            'font_family' => $theme->font_family,
            'swatches' => [
                'background' => $light['--background'] ?? null,
                'foreground' => $light['--foreground'] ?? null,
                'primary' => $light['--primary'] ?? null,
                'accent' => $light['--accent'] ?? null,
                'sidebar' => $light['--sidebar'] ?? null,
            ],
            'dark_swatches' => [
                'background' => $dark['--background'] ?? null,
                'primary' => $dark['--primary'] ?? null,
                'sidebar' => $dark['--sidebar'] ?? null,
            ],
            'created_at' => $theme->created_at?->toIso8601String(),
        ];
    }

    /**
     * Full editor payload.
     *
     * @return array<string, mixed>
     */
    protected function serialize(Theme $theme): array
    {
        return [
            'id' => $theme->id,
            'name' => $theme->name,
            'slug' => $theme->slug,
            'description' => $theme->description,
            'is_active' => (bool) $theme->is_active,
            'is_preset' => (bool) $theme->is_preset,
            'mode_hint' => $theme->mode_hint,
            'radius' => $theme->radius,
            'font_family' => $theme->font_family,
            'tokens' => [
                'light' => (object) ($theme->tokens['light'] ?? []),
                'dark' => (object) ($theme->tokens['dark'] ?? []),
            ],
            'custom_css' => $this->readCustomCss($theme),
            'fonts' => $theme->fonts
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'family' => $f->family,
                    'weight' => $f->weight,
                    'style' => $f->style,
                    'format' => $f->format,
                    'size_bytes' => (int) $f->size_bytes,
                    'original_filename' => $f->original_filename,
                ])
                ->all(),
            'families' => $theme->fonts->pluck('family')->unique()->values()->all(),
            'created_at' => $theme->created_at?->toIso8601String(),
        ];
    }

    protected function readCustomCss(Theme $theme): string
    {
        $path = $theme->custom_css_path;
        if ($path === null) {
            return '';
        }

        $disk = Storage::disk((string) config('themes.storage_disk', 'public'));

        return $disk->exists($path) ? (string) $disk->get($path) : '';
    }
}
