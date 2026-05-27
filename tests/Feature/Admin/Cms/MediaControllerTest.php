<?php

namespace Tests\Feature\Admin\Cms;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_index_requires_super_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/cms/media')->assertStatus(403);
    }

    public function test_upload_stores_file_and_creates_media_row(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();

        $file = UploadedFile::fake()->image('logo.png', 200, 100);

        $this->actingAs($admin)
            ->postJson('/admin/cms/media', ['file' => $file])
            ->assertOk()
            ->assertJsonStructure(['asset' => ['id', 'url', 'filename', 'mime_type', 'size_bytes']]);

        $this->assertDatabaseCount('media_library', 1);
        $asset = MediaAsset::first();
        Storage::disk('public')->assertExists($asset->path);
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();

        $file = UploadedFile::fake()->create('archive.zip', 100);

        $this->actingAs($admin)
            ->postJson('/admin/cms/media', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_update_persists_alt_and_focal(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();

        $file = UploadedFile::fake()->image('hero.jpg', 800, 400);
        $this->actingAs($admin)->postJson('/admin/cms/media', ['file' => $file]);
        $asset = MediaAsset::first();

        $this->actingAs($admin)
            ->patch("/admin/cms/media/{$asset->id}", [
                'alt' => 'A heroic banner',
                'focal_x' => 0.3,
                'focal_y' => 0.7,
            ])
            ->assertRedirect();

        $asset->refresh();
        $this->assertSame('A heroic banner', $asset->metadata['alt']);
        $this->assertSame(0.3, (float) $asset->metadata['focal_x']);
        $this->assertSame(0.7, (float) $asset->metadata['focal_y']);
    }

    public function test_destroy_soft_deletes_and_removes_file(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();

        $file = UploadedFile::fake()->image('gone.jpg');
        $this->actingAs($admin)->postJson('/admin/cms/media', ['file' => $file]);
        $asset = MediaAsset::first();
        $path = $asset->path;

        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)
            ->delete("/admin/cms/media/{$asset->id}")
            ->assertRedirect();

        $this->assertSoftDeleted($asset);
        Storage::disk('public')->assertMissing($path);
    }
}
