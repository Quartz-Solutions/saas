<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsBlogCategory;
use App\Models\CmsBlogPost;
use App\Models\CmsBlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlogTest extends TestCase
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

    public function test_blog_index_lists_published_posts_only(): void
    {
        CmsBlogPost::query()->create([
            'slug' => 'first', 'title' => 'First', 'status' => 'published', 'published_at' => now()->subHour(),
        ]);
        CmsBlogPost::query()->create([
            'slug' => 'draft-one', 'title' => 'Draft', 'status' => 'draft',
        ]);

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('marketing/blog/index')
                ->has('posts.data', 1)
                ->where('posts.data.0.slug', 'first')
            );
    }

    public function test_blog_show_returns_post_with_blocks(): void
    {
        CmsBlogPost::query()->create([
            'slug' => 'block-post',
            'title' => 'Block post',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'body_blocks' => [
                ['id' => '01J', 'type' => 'rich_text', 'attrs' => ['html' => '<p>hi</p>']],
            ],
        ]);

        $this->get('/blog/block-post')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('marketing/blog/show')
                ->where('post.slug', 'block-post')
                ->where('post.body_blocks.0.type', 'rich_text')
            );
    }

    public function test_blog_show_404s_for_draft_post(): void
    {
        CmsBlogPost::query()->create(['slug' => 'wip', 'title' => 'WIP', 'status' => 'draft']);
        $this->get('/blog/wip')->assertNotFound();
    }

    public function test_blog_rss_returns_xml(): void
    {
        CmsBlogPost::query()->create([
            'slug' => 'rss-one', 'title' => 'RSS one', 'status' => 'published', 'published_at' => now()->subHour(),
            'excerpt' => 'a short summary',
        ]);

        $response = $this->get('/blog/feed.xml');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $this->assertStringContainsString('<rss', $response->getContent());
        $this->assertStringContainsString('RSS one', $response->getContent());
    }

    public function test_admin_can_create_post_with_block_and_publish(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post('/admin/cms/blog/posts', [
                'title' => 'Hello world',
                'slug' => 'hello-world',
                'status' => 'published',
                'no_index' => false,
                'body_blocks' => [
                    ['id' => '01J', 'type' => 'rich_text', 'attrs' => ['html' => '<p>body</p>']],
                ],
            ])
            ->assertRedirect();

        $post = CmsBlogPost::query()->where('slug', 'hello-world')->first();
        $this->assertNotNull($post);
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertGreaterThanOrEqual(1, (int) $post->reading_minutes);
    }

    public function test_admin_post_can_attach_categories_and_tags(): void
    {
        $admin = $this->makeSuperAdmin();
        $cat = CmsBlogCategory::query()->create(['slug' => 'news', 'name' => 'News']);
        $tag = CmsBlogTag::query()->create(['slug' => 'release', 'name' => 'release']);

        $this->actingAs($admin)
            ->post('/admin/cms/blog/posts', [
                'title' => 'Taxonomy',
                'status' => 'draft',
                'no_index' => false,
                'category_ids' => [$cat->id],
                'tag_ids' => [$tag->id],
            ])
            ->assertRedirect();

        $post = CmsBlogPost::query()->where('title', 'Taxonomy')->first();
        $this->assertCount(1, $post->categories);
        $this->assertCount(1, $post->tags);
    }

    public function test_category_archive_lists_only_that_category(): void
    {
        $cat = CmsBlogCategory::query()->create(['slug' => 'announcements', 'name' => 'Announcements']);
        $p1 = CmsBlogPost::query()->create([
            'slug' => 'a1', 'title' => 'A1', 'status' => 'published', 'published_at' => now()->subHour(),
        ]);
        $p2 = CmsBlogPost::query()->create([
            'slug' => 'a2', 'title' => 'A2', 'status' => 'published', 'published_at' => now()->subHour(),
        ]);
        $p1->categories()->attach($cat->id);

        $this->get('/blog/category/announcements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('marketing/blog/archive')
                ->has('posts', 1)
                ->where('posts.0.slug', 'a1')
            );
        unset($p2);
    }
}
