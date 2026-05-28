<?php

namespace Tests\Feature\Admin;

use App\Models\Theme;
use App\Models\User;
use App\Support\Theme\ThemeService;
use Database\Seeders\ThemesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThemesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function service(): ThemeService
    {
        return app(ThemeService::class);
    }

    public function test_index_requires_super_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/themes')->assertStatus(403);
    }

    public function test_mutations_require_super_admin(): void
    {
        $this->seed(ThemesSeeder::class);
        $user = User::factory()->create();
        $theme = Theme::query()->where('slug', 'emerald')->firstOrFail();

        $this->actingAs($user)->post("/admin/themes/{$theme->id}/activate")->assertStatus(403);
        $this->actingAs($user)->post("/admin/themes/{$theme->id}/clone")->assertStatus(403);
        $this->actingAs($user)->delete("/admin/themes/{$theme->id}")->assertStatus(403);
    }

    public function test_index_lists_seeded_themes(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(ThemesSeeder::class);

        $this->actingAs($admin)
            ->get('/admin/themes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/themes/index')
                ->has('themes', 6)
            );
    }

    public function test_store_creates_theme_and_redirects_to_edit(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post('/admin/themes', [
                'name' => 'Sunset',
                'description' => 'Warm palette',
                'mode_hint' => 'both',
                'radius' => '0.75rem',
                'tokens' => [
                    'light' => ['--primary' => 'oklch(0.6 0.2 40)'],
                    'dark' => ['--primary' => 'oklch(0.7 0.2 40)'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('themes', ['name' => 'Sunset', 'slug' => 'sunset', 'is_preset' => false]);
    }

    public function test_store_rejects_unsafe_token_values(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/themes/create')
            ->post('/admin/themes', [
                'name' => 'Evil',
                'tokens' => ['light' => ['--primary' => 'red; } body{display:none}']],
            ])
            ->assertRedirect('/admin/themes/create')
            ->assertSessionHasErrors('tokens.light.--primary');
    }

    public function test_update_persists_token_changes(): void
    {
        $admin = $this->makeSuperAdmin();
        $theme = $this->service()->create(['name' => 'Editable']);

        $this->actingAs($admin)
            ->put("/admin/themes/{$theme->id}", [
                'name' => 'Editable',
                'mode_hint' => 'both',
                'radius' => '0.5rem',
                'tokens' => ['light' => ['--primary' => 'oklch(0.45 0.18 250)']],
            ])
            ->assertRedirect();

        $this->assertSame('oklch(0.45 0.18 250)', $theme->fresh()->tokens['light']['--primary']);
        $this->assertSame('0.5rem', $theme->fresh()->radius);
    }

    public function test_preset_update_is_blocked(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(ThemesSeeder::class);
        $preset = Theme::query()->where('slug', 'emerald')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/themes/{$preset->id}", ['name' => 'Hacked', 'mode_hint' => 'both'])
            ->assertStatus(422);
    }

    public function test_activate_swaps_the_active_theme(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(ThemesSeeder::class);
        $emerald = Theme::query()->where('slug', 'emerald')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/themes/{$emerald->id}/activate")
            ->assertRedirect();

        $this->assertTrue($emerald->fresh()->is_active);
        $this->assertSame(1, Theme::query()->where('is_active', true)->count());
    }

    public function test_clone_creates_editable_copy_and_redirects(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(ThemesSeeder::class);
        $preset = Theme::query()->where('slug', 'indigo')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/themes/{$preset->id}/clone")
            ->assertRedirect();

        $this->assertDatabaseHas('themes', ['name' => 'Indigo (copy)', 'is_preset' => false]);
    }

    public function test_preset_delete_is_forbidden(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->seed(ThemesSeeder::class);
        $preset = Theme::query()->where('slug', 'emerald')->firstOrFail();

        $this->actingAs($admin)
            ->delete("/admin/themes/{$preset->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('themes', ['id' => $preset->id, 'deleted_at' => null]);
    }

    public function test_active_theme_cannot_be_deleted(): void
    {
        $admin = $this->makeSuperAdmin();
        $theme = $this->service()->create(['name' => 'Live']);
        $this->service()->activate($theme);

        $this->actingAs($admin)
            ->delete("/admin/themes/{$theme->id}")
            ->assertStatus(422);
    }

    public function test_user_theme_can_be_deleted(): void
    {
        $admin = $this->makeSuperAdmin();
        $theme = $this->service()->create(['name' => 'Disposable']);

        $this->actingAs($admin)
            ->delete("/admin/themes/{$theme->id}")
            ->assertRedirect('/admin/themes');

        $this->assertSoftDeleted('themes', ['id' => $theme->id]);
    }

    public function test_active_theme_css_is_linked_in_head_and_changes_on_switch(): void
    {
        $this->seed(ThemesSeeder::class);
        $default = Theme::query()->where('slug', 'default')->firstOrFail();
        $emerald = Theme::query()->where('slug', 'emerald')->firstOrFail();

        $html1 = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString($default->compiled_css_path, $html1);
        $this->assertStringContainsString('rel="stylesheet"', $html1);

        $this->service()->activate($emerald);

        $html2 = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString($emerald->fresh()->compiled_css_path, $html2);
        $this->assertStringNotContainsString($default->compiled_css_path, $html2);
    }

    public function test_custom_css_is_saved_and_compiled(): void
    {
        $admin = $this->makeSuperAdmin();
        $theme = $this->service()->create(['name' => 'CssTheme']);

        $this->actingAs($admin)
            ->put("/admin/themes/{$theme->id}/custom-css", ['css' => '.brand-banner { display: block; }'])
            ->assertRedirect();

        $theme->refresh();
        $this->assertNotNull($theme->custom_css_path);
        $compiled = Storage::disk('public')->get($theme->compiled_css_path);
        $this->assertStringContainsString('.brand-banner', $compiled);
    }

    public function test_custom_css_enforces_size_cap(): void
    {
        $admin = $this->makeSuperAdmin();
        $theme = $this->service()->create(['name' => 'BigCss']);

        $this->actingAs($admin)
            ->from("/admin/themes/{$theme->id}/edit")
            ->put("/admin/themes/{$theme->id}/custom-css", ['css' => str_repeat('a', 300000)])
            ->assertRedirect("/admin/themes/{$theme->id}/edit")
            ->assertSessionHasErrors('css');
    }

    public function test_microsoft_preset_ships_square_corner_custom_css(): void
    {
        $this->seed(ThemesSeeder::class);
        $ms = Theme::query()->where('slug', 'microsoft')->firstOrFail();

        $this->assertNotNull($ms->custom_css_path);
        $this->assertSame('0rem', $ms->radius);

        $css = Storage::disk('public')->get($ms->compiled_css_path);
        $this->assertStringContainsString('border-radius: 0 !important', $css);
        $this->assertStringContainsString("--font-sans: 'Segoe UI'", $css);
    }

    public function test_creating_a_theme_is_audited(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post('/admin/themes', ['name' => 'Audited Theme', 'mode_hint' => 'both'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'created',
            'auditable_type' => Theme::class,
        ]);
    }

    public function test_custom_css_rejects_non_css_file_upload(): void
    {
        $admin = $this->makeSuperAdmin();
        $theme = $this->service()->create(['name' => 'FileCss']);

        $file = UploadedFile::fake()->create('payload.php', 1, 'application/x-php');

        $this->actingAs($admin)
            ->from("/admin/themes/{$theme->id}/edit")
            ->put("/admin/themes/{$theme->id}/custom-css", ['file' => $file])
            ->assertRedirect("/admin/themes/{$theme->id}/edit")
            ->assertSessionHasErrors('file');
    }
}
