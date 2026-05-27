<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsPage;
use Database\Seeders\CmsDocsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsDocsSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_SLUGS = [
        'cms-overview',
        'cms-pages',
        'cms-blocks',
        'cms-globals',
        'cms-media',
        'cms-collections',
        'cms-blog',
        'cms-forms',
        'cms-newsletter',
        'cms-redirects',
        'cms-seo',
        'cms-i18n',
        'cms-versions-preview',
        'cms-cache',
    ];

    public function test_seeder_creates_all_cms_doc_pages_as_published_docs(): void
    {
        $this->seed(CmsDocsSeeder::class);

        foreach (self::EXPECTED_SLUGS as $slug) {
            $page = CmsPage::query()->where('slug', $slug)->first();
            $this->assertNotNull($page, "Missing seeded doc: {$slug}");
            $this->assertSame(CmsPage::STATUS_PUBLISHED, $page->status, "Doc {$slug} should be published");
            $this->assertSame(CmsPage::TEMPLATE_DOCS, $page->template, "Doc {$slug} should be template=docs");
            $this->assertIsArray($page->body_blocks, "Doc {$slug} should have block-based body");
            $this->assertGreaterThan(0, count($page->body_blocks), "Doc {$slug} should have at least one block");
        }
    }

    public function test_overview_page_links_appear_in_docs_index(): void
    {
        $this->seed(CmsDocsSeeder::class);

        $this->get('/docs')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('marketing/docs/index')
                ->has('pages', count(self::EXPECTED_SLUGS))
            );
    }

    public function test_overview_page_renders_with_blocks(): void
    {
        $this->seed(CmsDocsSeeder::class);

        $this->get('/docs/cms-overview')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('marketing/docs/show')
                ->where('page.title', 'CMS overview')
                ->where('page.body_blocks.0.type', 'rich_text')
            );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CmsDocsSeeder::class);
        $this->seed(CmsDocsSeeder::class);
        $this->seed(CmsDocsSeeder::class);

        $count = CmsPage::query()->whereIn('slug', self::EXPECTED_SLUGS)->count();
        $this->assertSame(count(self::EXPECTED_SLUGS), $count, 'Re-running the seeder should not create duplicates.');
    }

    public function test_every_doc_uses_only_registered_block_types(): void
    {
        $this->seed(CmsDocsSeeder::class);
        $registered = app(\App\Support\Cms\BlockTypeRegistry::class)->ids();

        $pages = CmsPage::query()->whereIn('slug', self::EXPECTED_SLUGS)->get();
        foreach ($pages as $page) {
            foreach ((array) $page->body_blocks as $block) {
                $this->assertContains(
                    $block['type'] ?? null,
                    $registered,
                    "Doc {$page->slug} references unknown block type [{$block['type']}]",
                );
            }
        }
    }
}
