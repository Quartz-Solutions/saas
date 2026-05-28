<?php

namespace Tests\Feature\Admin;

use App\Models\Theme;
use App\Models\User;
use App\Support\Theme\FontArchiveImporter;
use App\Support\Theme\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class ThemeFontImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * @param  array<string, string>  $files
     */
    private function makeZip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fonttest').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    private function importer(): FontArchiveImporter
    {
        return app(FontArchiveImporter::class);
    }

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_rejects_zip_slip_traversal_entries(): void
    {
        $zip = $this->makeZip(['../evil.ttf' => 'x']);

        $this->expectException(RuntimeException::class);
        $this->importer()->readArchive($zip);
    }

    public function test_rejects_absolute_path_entries(): void
    {
        $zip = $this->makeZip(['/etc/passwd.ttf' => 'x']);

        $this->expectException(RuntimeException::class);
        $this->importer()->readArchive($zip);
    }

    public function test_ignores_non_font_entries(): void
    {
        $zip = $this->makeZip([
            'OFL.txt' => 'license',
            'README.md' => 'readme',
            'preview.png' => 'imagedata',
            'Roboto-Regular.ttf' => 'fontdata',
        ]);

        $entries = $this->importer()->readArchive($zip);

        $this->assertCount(1, $entries);
        $this->assertSame('Roboto', $entries[0]['family']);
        $this->assertSame('ttf', $entries[0]['format']);
    }

    public function test_enforces_per_file_size_cap(): void
    {
        config(['themes.fonts.max_file_bytes' => 10]);
        $zip = $this->makeZip(['Roboto-Regular.ttf' => str_repeat('A', 100)]);

        $this->expectException(RuntimeException::class);
        $this->importer()->readArchive($zip);
    }

    public function test_parses_google_fonts_filenames(): void
    {
        $this->assertSame(
            ['family' => 'Roboto', 'weight' => '700', 'style' => 'italic', 'format' => 'ttf'],
            FontArchiveImporter::parseFilename('Roboto-BoldItalic.ttf'),
        );

        $this->assertSame(
            ['family' => 'Open Sans', 'weight' => '400', 'style' => 'normal', 'format' => 'woff2'],
            FontArchiveImporter::parseFilename('OpenSans-Regular.woff2'),
        );

        $this->assertSame(
            ['family' => 'Roboto', 'weight' => '100 900', 'style' => 'normal', 'format' => 'ttf'],
            FontArchiveImporter::parseFilename('Roboto[wght].ttf'),
        );

        $this->assertSame(
            ['family' => 'Lora', 'weight' => '500', 'style' => 'normal', 'format' => 'otf'],
            FontArchiveImporter::parseFilename('Lora-Medium.otf'),
        );
    }

    public function test_upload_creates_font_rows_and_compiles_font_face(): void
    {
        $admin = $this->makeSuperAdmin();
        $theme = app(ThemeService::class)->create(['name' => 'FontTheme']);

        $zipPath = $this->makeZip([
            'static/Roboto-Regular.ttf' => 'fontdata-regular',
            'static/Roboto-Bold.ttf' => 'fontdata-bold',
            'OFL.txt' => 'license',
        ]);
        $upload = new UploadedFile($zipPath, 'roboto.zip', 'application/zip', null, true);

        $this->actingAs($admin)
            ->post("/admin/themes/{$theme->id}/fonts", ['archive' => $upload])
            ->assertRedirect();

        $this->assertSame(2, $theme->fonts()->count());
        $this->assertDatabaseHas('theme_fonts', ['theme_id' => $theme->id, 'family' => 'Roboto', 'weight' => '700']);

        // Pick the family + recompile → @font-face + --font-sans land in the artifact.
        app(ThemeService::class)->update($theme, ['font_family' => 'Roboto']);
        $css = Storage::disk('public')->get($theme->fresh()->compiled_css_path);
        $this->assertStringContainsString('@font-face', $css);
        $this->assertStringContainsString("--font-sans: 'Roboto'", $css);
    }

    public function test_font_upload_requires_super_admin(): void
    {
        $theme = app(ThemeService::class)->create(['name' => 'Guarded']);
        $user = User::factory()->create();

        $zipPath = $this->makeZip(['Roboto-Regular.ttf' => 'fontdata']);
        $upload = new UploadedFile($zipPath, 'roboto.zip', 'application/zip', null, true);

        $this->actingAs($user)
            ->post("/admin/themes/{$theme->id}/fonts", ['archive' => $upload])
            ->assertStatus(403);
    }

    public function test_font_upload_blocked_on_preset(): void
    {
        $admin = $this->makeSuperAdmin();
        $preset = Theme::factory()->preset()->create();

        $zipPath = $this->makeZip(['Roboto-Regular.ttf' => 'fontdata']);
        $upload = new UploadedFile($zipPath, 'roboto.zip', 'application/zip', null, true);

        $this->actingAs($admin)
            ->post("/admin/themes/{$preset->id}/fonts", ['archive' => $upload])
            ->assertStatus(422);
    }
}
