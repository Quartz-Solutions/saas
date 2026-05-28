<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Themes\FontUploadRequest;
use App\Models\Theme;
use App\Models\ThemeFont;
use App\Support\Theme\ThemeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;

class ThemeFontsController extends Controller
{
    public function __construct(private readonly ThemeService $themes) {}

    public function store(FontUploadRequest $request, Theme $theme): RedirectResponse
    {
        abort_if($theme->is_preset, 422, 'Preset themes are read-only. Clone one to make an editable copy.');

        try {
            $created = $this->themes->importFontZip($theme, $request->file('archive'));
        } catch (RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count font face(s) imported.', ['count' => $created->count()]),
        ]);

        return back();
    }

    public function destroy(Theme $theme, ThemeFont $font): RedirectResponse
    {
        abort_if($theme->is_preset, 422, 'Preset themes are read-only.');
        abort_unless($font->theme_id === $theme->id, 404);

        $this->themes->deleteFont($theme, $font);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Font face removed.')]);

        return back();
    }
}
