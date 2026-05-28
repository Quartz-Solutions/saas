<?php

namespace Tests\Unit;

use App\Models\Theme;
use App\Models\ThemeFont;
use App\Support\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ThemeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function service(): ThemeService
    {
        return app(ThemeService::class);
    }

    public function test_only_one_theme_is_active_at_a_time(): void
    {
        $svc = $this->service();
        $a = $svc->create(['name' => 'Alpha']);
        $b = $svc->create(['name' => 'Bravo']);

        $svc->activate($a);
        $this->assertSame(1, Theme::query()->where('is_active', true)->count());

        $svc->activate($b);
        $this->assertSame(1, Theme::query()->where('is_active', true)->count());
        $this->assertTrue($b->fresh()->is_active);
        $this->assertFalse($a->fresh()->is_active);
    }

    public function test_compile_emits_root_dark_and_appends_custom_css_last(): void
    {
        $svc = $this->service();
        $theme = $svc->create([
            'name' => 'Compiled',
            'tokens' => [
                'light' => ['--primary' => 'oklch(0.4 0.2 20)'],
                'dark' => ['--primary' => 'oklch(0.7 0.2 20)'],
            ],
        ]);

        $svc->storeCustomCss($theme, '.special-marker { color: red; }');
        $css = Storage::disk('public')->get($theme->fresh()->compiled_css_path);

        $this->assertStringContainsString(':root {', $css);
        $this->assertStringContainsString('--primary: oklch(0.4 0.2 20);', $css);
        $this->assertStringContainsString('.dark {', $css);
        $this->assertStringContainsString('--primary: oklch(0.7 0.2 20);', $css);
        $this->assertStringContainsString('.special-marker', $css);

        // Custom CSS is appended last so it wins over the token blocks.
        $this->assertGreaterThan(strpos($css, '--primary'), strpos($css, '.special-marker'));
    }

    public function test_compile_emits_font_face_and_font_sans_when_family_matches(): void
    {
        $svc = $this->service();
        $theme = $svc->create(['name' => 'Fonted', 'font_family' => 'Roboto']);

        // No matching face yet → no --font-sans override.
        $cssBefore = Storage::disk('public')->get($theme->fresh()->compiled_css_path);
        $this->assertStringNotContainsString('--font-sans', $cssBefore);

        ThemeFont::create([
            'theme_id' => $theme->id,
            'family' => 'Roboto',
            'weight' => '400',
            'style' => 'normal',
            'format' => 'woff2',
            'path' => "themes/{$theme->id}/fonts/roboto.woff2",
            'original_filename' => 'Roboto-Regular.woff2',
            'size_bytes' => 1234,
        ]);

        $svc->compile($theme->fresh());
        $css = Storage::disk('public')->get($theme->fresh()->compiled_css_path);

        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString("font-family: 'Roboto'", $css);
        $this->assertStringContainsString("--font-sans: 'Roboto'", $css);
    }

    public function test_cache_is_invalidated_and_url_changes_on_mutation(): void
    {
        $svc = $this->service();
        $theme = $svc->create(['name' => 'Cacheable']);
        $svc->activate($theme);

        $url1 = $svc->activeCssUrl();
        $this->assertNotNull($url1);
        $this->assertTrue(Cache::has('theme.active'));

        $svc->update($theme, ['tokens' => ['light' => ['--primary' => 'oklch(0.3 0.2 90)']]]);

        $this->assertFalse(Cache::has('theme.active'), 'mutation must forget the cache');
        $this->assertNotSame($url1, $svc->activeCssUrl(), 'recompiled artifact is hash-busted');
    }

    public function test_unsafe_token_values_are_dropped_from_compiled_output(): void
    {
        $svc = $this->service();
        $theme = $svc->create([
            'name' => 'Sanitized',
            'tokens' => [
                'light' => [
                    '--primary' => 'red; } body{display:none}',
                    '--accent' => 'oklch(0.5 0.1 20)',
                ],
            ],
        ]);

        $css = Storage::disk('public')->get($theme->fresh()->compiled_css_path);
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringContainsString('--accent: oklch(0.5 0.1 20);', $css);
    }

    public function test_duplicate_makes_an_editable_non_preset_copy(): void
    {
        $svc = $this->service();
        $preset = Theme::factory()->preset()->create([
            'tokens' => ['light' => ['--primary' => 'oklch(0.5 0.2 150)'], 'dark' => []],
        ]);

        $copy = $svc->duplicate($preset);

        $this->assertFalse($copy->is_preset);
        $this->assertFalse($copy->is_active);
        $this->assertNotSame($preset->slug, $copy->slug);
        $this->assertEquals($preset->tokens, $copy->tokens);
    }

    public function test_delete_refuses_presets(): void
    {
        $svc = $this->service();
        $preset = Theme::factory()->preset()->create();

        $this->expectException(RuntimeException::class);
        $svc->delete($preset);
    }

    public function test_delete_refuses_active_theme(): void
    {
        $svc = $this->service();
        $theme = $svc->create(['name' => 'Live']);
        $svc->activate($theme);

        $this->expectException(RuntimeException::class);
        $svc->delete($theme->fresh());
    }
}
