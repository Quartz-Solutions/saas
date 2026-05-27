<?php

namespace Tests\Feature\Admin\Cms;

use App\Events\CmsContentPublished;
use App\Models\CmsPage;
use App\Models\User;
use App\Support\Cms\PageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
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

    public function test_page_cache_is_warmed_on_first_read(): void
    {
        CmsPage::query()->create([
            'slug' => 'cached', 'title' => 'Cached', 'locale' => 'en',
            'status' => 'published', 'published_at' => now()->subHour(), 'template' => 'docs',
        ]);

        $this->assertNull(Cache::get('cms.page:cached:en'));

        $this->get('/docs/cached')->assertOk();

        $this->assertNotNull(Cache::get('cms.page:cached:en'));
    }

    public function test_admin_save_busts_per_locale_cache(): void
    {
        $admin = $this->makeSuperAdmin();
        $page = CmsPage::query()->create([
            'slug' => 'editable', 'title' => 'v1', 'locale' => 'en',
            'status' => 'published', 'published_at' => now()->subHour(), 'template' => 'docs',
        ]);

        $this->get('/docs/editable')->assertOk();
        $this->assertNotNull(Cache::get('cms.page:editable:en'));

        $this->actingAs($admin)->patch("/admin/cms/pages/{$page->id}", [
            'title' => 'v2', 'slug' => 'editable', 'locale' => 'en',
            'template' => 'docs', 'status' => 'published',
            'no_index' => false,
        ])->assertRedirect();

        $this->assertNull(Cache::get('cms.page:editable:en'));
    }

    public function test_save_dispatches_content_published_event(): void
    {
        Event::fake([CmsContentPublished::class]);

        $admin = $this->makeSuperAdmin();
        $page = CmsPage::query()->create([
            'slug' => 'dispatched', 'title' => 'one', 'locale' => 'en',
            'status' => 'draft', 'template' => 'docs',
        ]);

        app(PageService::class)->save($page, [
            'title' => 'two',
            'slug' => 'dispatched',
            'locale' => 'en',
            'template' => 'docs',
            'status' => 'published',
            'no_index' => false,
        ]);

        Event::assertDispatched(CmsContentPublished::class, function (CmsContentPublished $e) {
            return $e->page->slug === 'dispatched' && $e->page->locale === 'en';
        });
    }

    public function test_globals_cache_is_busted_on_save(): void
    {
        $admin = $this->makeSuperAdmin();

        // Warm the cache.
        $this->actingAs($admin)->get('/admin/cms/globals/brand');
        $this->assertNotNull(Cache::get('cms.global:brand'));

        $this->actingAs($admin)->patch('/admin/cms/globals/brand', [
            'payload' => ['brand_color' => '#123456'],
        ])->assertRedirect();

        $this->assertNull(Cache::get('cms.global:brand'));
    }
}
