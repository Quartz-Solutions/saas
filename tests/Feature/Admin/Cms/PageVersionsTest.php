<?php

namespace Tests\Feature\Admin\Cms;

use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageVersionsTest extends TestCase
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

    public function test_every_save_creates_a_new_version(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post('/admin/cms/pages', [
            'title' => 'v1', 'slug' => 'v', 'locale' => 'en',
            'template' => CmsPage::TEMPLATE_DEFAULT, 'status' => CmsPage::STATUS_DRAFT,
            'no_index' => false,
            'body_blocks' => [['id' => '01J', 'type' => 'rich_text', 'attrs' => ['html' => '<p>1</p>']]],
        ])->assertRedirect();

        $page = CmsPage::query()->where('slug', 'v')->first();
        $this->assertSame(1, CmsPageVersion::query()->where('cms_page_id', $page->id)->count());

        $this->actingAs($admin)->patch("/admin/cms/pages/{$page->id}", [
            'title' => 'v2', 'slug' => 'v', 'locale' => 'en',
            'template' => CmsPage::TEMPLATE_DEFAULT, 'status' => CmsPage::STATUS_DRAFT,
            'no_index' => false,
            'body_blocks' => [['id' => '01J', 'type' => 'rich_text', 'attrs' => ['html' => '<p>2</p>']]],
        ]);

        $this->assertSame(2, CmsPageVersion::query()->where('cms_page_id', $page->id)->count());
    }

    public function test_restore_rolls_back_and_creates_two_more_versions(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post('/admin/cms/pages', [
            'title' => 'Original', 'slug' => 'rb', 'locale' => 'en',
            'template' => CmsPage::TEMPLATE_DEFAULT, 'status' => CmsPage::STATUS_DRAFT,
            'no_index' => false,
            'body_blocks' => [['id' => '01J', 'type' => 'rich_text', 'attrs' => ['html' => '<p>original</p>']]],
        ]);
        $page = CmsPage::query()->where('slug', 'rb')->first();
        $v1 = CmsPageVersion::query()->where('cms_page_id', $page->id)->orderBy('version_no')->first();

        $this->actingAs($admin)->patch("/admin/cms/pages/{$page->id}", [
            'title' => 'Updated', 'slug' => 'rb', 'locale' => 'en',
            'template' => CmsPage::TEMPLATE_DEFAULT, 'status' => CmsPage::STATUS_DRAFT,
            'no_index' => false,
            'body_blocks' => [['id' => '01J', 'type' => 'rich_text', 'attrs' => ['html' => '<p>updated</p>']]],
        ]);

        $this->actingAs($admin)
            ->post("/admin/cms/pages/{$page->id}/versions/{$v1->id}/restore")
            ->assertRedirect();

        $this->assertSame('Original', $page->fresh()->title);
        $this->assertGreaterThanOrEqual(4, CmsPageVersion::query()->where('cms_page_id', $page->id)->count());
    }

    public function test_versions_index_returns_list(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post('/admin/cms/pages', [
            'title' => 'List', 'slug' => 'list', 'locale' => 'en',
            'template' => CmsPage::TEMPLATE_DEFAULT, 'status' => CmsPage::STATUS_DRAFT,
            'no_index' => false,
        ]);
        $page = CmsPage::query()->where('slug', 'list')->first();

        $this->actingAs($admin)
            ->get("/admin/cms/pages/{$page->id}/versions")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/cms/pages/versions')
                ->where('page.title', 'List')
                ->has('versions', 1)
            );
    }

    public function test_signed_preview_url_serves_draft_page(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'unpublished', 'title' => 'Hidden',
            'template' => CmsPage::TEMPLATE_DEFAULT,
            'status' => CmsPage::STATUS_DRAFT,
            'body_blocks' => [['id' => '01J', 'type' => 'rich_text', 'attrs' => ['html' => '<p>draft</p>']]],
        ]);

        $url = URL::temporarySignedRoute('marketing.preview.page', now()->addMinutes(5), ['id' => $page->id]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('marketing/docs/show')
                ->where('preview', true)
                ->where('page.title', 'Hidden')
            );
    }

    public function test_unsigned_preview_url_is_rejected(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'still-hidden', 'title' => 'Hidden 2',
            'template' => CmsPage::TEMPLATE_DEFAULT,
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $this->get('/preview/page/'.$page->id)->assertStatus(403);
    }

    public function test_publish_scheduled_command_promotes_pages(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'scheduled', 'title' => 'Tomorrow',
            'template' => CmsPage::TEMPLATE_DEFAULT,
            'status' => CmsPage::STATUS_DRAFT,
            'publish_at' => now()->subMinute(),
        ]);

        $this->artisan('cms:publish-scheduled')->assertSuccessful();

        $this->assertSame(CmsPage::STATUS_PUBLISHED, $page->fresh()->status);
        $this->assertNotNull($page->fresh()->published_at);
    }

    public function test_publish_scheduled_command_archives_expired(): void
    {
        $page = CmsPage::query()->create([
            'slug' => 'expired', 'title' => 'Sunset',
            'template' => CmsPage::TEMPLATE_DEFAULT,
            'status' => CmsPage::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'unpublish_at' => now()->subHour(),
        ]);

        $this->artisan('cms:publish-scheduled')->assertSuccessful();

        $this->assertSame(CmsPage::STATUS_ARCHIVED, $page->fresh()->status);
    }
}
