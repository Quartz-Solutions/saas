<?php

namespace Tests\Feature\Admin\Cms;

use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PagesControllerTest extends TestCase
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
        $this->actingAs($user)->get('/admin/cms/pages')->assertStatus(403);
    }

    public function test_index_lists_pages_and_block_catalog(): void
    {
        $admin = $this->makeSuperAdmin();
        CmsPage::factory()->create([
            'slug' => 'about',
            'title' => 'About',
            'template' => CmsPage::TEMPLATE_DEFAULT,
            'status' => CmsPage::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/cms/pages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cms/pages/index')
                ->has('pages.data', 1)
                ->where('pages.data.0.slug', 'about')
            );
    }

    public function test_create_form_exposes_block_catalog(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/cms/pages/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cms/pages/edit')
                ->where('page', null)
                ->has('blockCatalog')
                ->where('blockCatalog.0.id', fn ($id) => is_string($id) && $id !== '')
            );
    }

    public function test_store_creates_page_with_blocks(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post('/admin/cms/pages', [
                'title' => 'Marketing landing',
                'slug' => 'landing',
                'locale' => 'en',
                'template' => CmsPage::TEMPLATE_LANDING,
                'status' => CmsPage::STATUS_DRAFT,
                'no_index' => false,
                'body_blocks' => [
                    [
                        'id' => '01JEXMPL00000000000000HERO',
                        'type' => 'hero',
                        'attrs' => [
                            'title' => 'Welcome',
                            'subtitle' => 'A description.',
                            'primary_cta_label' => 'Start',
                            'primary_cta_url' => '/get-started',
                            'layout' => 'centered',
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $page = CmsPage::query()->where('slug', 'landing')->first();
        $this->assertNotNull($page);
        $this->assertSame('Marketing landing', $page->title);
        $this->assertIsArray($page->body_blocks);
        $this->assertSame('hero', $page->body_blocks[0]['type']);
        $this->assertSame('Welcome', $page->body_blocks[0]['attrs']['title']);
    }

    public function test_store_rejects_unknown_block_type(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->postJson('/admin/cms/pages', [
                'title' => 'Bad',
                'locale' => 'en',
                'template' => CmsPage::TEMPLATE_DEFAULT,
                'status' => CmsPage::STATUS_DRAFT,
                'no_index' => false,
                'body_blocks' => [
                    ['id' => '01J', 'type' => 'totally_made_up', 'attrs' => []],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body_blocks']);
    }

    public function test_update_replaces_body_blocks(): void
    {
        $admin = $this->makeSuperAdmin();
        $page = CmsPage::factory()->create([
            'slug' => 'home-v1',
            'title' => 'Home',
            'status' => CmsPage::STATUS_DRAFT,
            'template' => CmsPage::TEMPLATE_LANDING,
            'body_blocks' => null,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/cms/pages/{$page->id}", [
                'title' => 'Home updated',
                'slug' => 'home-v1',
                'locale' => 'en',
                'template' => CmsPage::TEMPLATE_LANDING,
                'status' => CmsPage::STATUS_PUBLISHED,
                'no_index' => false,
                'body_blocks' => [
                    [
                        'id' => '01JEXMPL00000000000RICHTEXT',
                        'type' => 'rich_text',
                        'attrs' => ['html' => '<p>Hi.</p>'],
                    ],
                ],
            ])
            ->assertRedirect();

        $page->refresh();
        $this->assertSame('Home updated', $page->title);
        $this->assertSame('rich_text', $page->body_blocks[0]['type']);
        $this->assertSame(CmsPage::STATUS_PUBLISHED, $page->status);
        $this->assertNotNull($page->published_at);
    }

    public function test_destroy_soft_deletes_the_page(): void
    {
        $admin = $this->makeSuperAdmin();
        $page = CmsPage::factory()->create(['slug' => 'soft', 'title' => 'Soft']);

        $this->actingAs($admin)
            ->delete("/admin/cms/pages/{$page->id}")
            ->assertRedirect();

        $this->assertSoftDeleted($page);
    }

    public function test_restore_brings_back_a_soft_deleted_page(): void
    {
        $admin = $this->makeSuperAdmin();
        $page = CmsPage::factory()->create(['slug' => 'rest', 'title' => 'Rest']);
        $page->delete();

        $this->actingAs($admin)
            ->post("/admin/cms/pages/{$page->id}/restore")
            ->assertRedirect();

        $this->assertNull($page->fresh()->deleted_at);
    }
}
