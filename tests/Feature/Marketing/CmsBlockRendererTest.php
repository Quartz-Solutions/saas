<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsPage;
use App\Support\Cms\BlockTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CmsBlockRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_page_with_blocks_exposes_them_to_react(): void
    {
        CmsPage::factory()->create([
            'slug' => 'with-blocks',
            'title' => 'Blocks Page',
            'status' => CmsPage::STATUS_PUBLISHED,
            'template' => CmsPage::TEMPLATE_DOCS,
            'published_at' => now()->subHour(),
            'body_html' => '<p>legacy</p>',
            'body_blocks' => [
                [
                    'id' => '01JEXMPL000000000000000001',
                    'type' => 'rich_text',
                    'attrs' => ['html' => '<h2>Hello blocks</h2>'],
                ],
                [
                    'id' => '01JEXMPL000000000000000002',
                    'type' => 'divider',
                    'attrs' => ['style' => 'line'],
                ],
            ],
        ]);

        $this->get(route('marketing.docs.show', ['slug' => 'with-blocks']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('marketing/docs/show')
                ->where('page.slug', 'with-blocks')
                ->where('page.body_blocks.0.type', 'rich_text')
                ->where('page.body_blocks.0.attrs.html', '<h2>Hello blocks</h2>')
                ->where('page.body_blocks.1.type', 'divider')
            );
    }

    public function test_published_page_without_blocks_still_returns_body_html(): void
    {
        CmsPage::factory()->create([
            'slug' => 'legacy-page',
            'title' => 'Legacy Page',
            'status' => CmsPage::STATUS_PUBLISHED,
            'template' => CmsPage::TEMPLATE_DOCS,
            'published_at' => now()->subHour(),
            'body_html' => '<p>legacy content</p>',
            'body_blocks' => null,
        ]);

        $this->get(route('marketing.docs.show', ['slug' => 'legacy-page']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('page.body_html', '<p>legacy content</p>')
                ->where('page.body_blocks', null)
            );
    }

    public function test_block_registry_loads_all_configured_types(): void
    {
        $registry = app(BlockTypeRegistry::class);

        $ids = $registry->ids();

        $this->assertContains('rich_text', $ids);
        $this->assertContains('hero', $ids);
        $this->assertContains('feature_grid', $ids);
        $this->assertContains('pricing', $ids);
        $this->assertContains('testimonials', $ids);
        $this->assertContains('faq', $ids);
        $this->assertContains('newsletter', $ids);
    }

    public function test_validate_tree_rejects_unknown_block_type(): void
    {
        $registry = app(BlockTypeRegistry::class);

        $this->expectException(ValidationException::class);

        $registry->validateTree([
            ['id' => '01J', 'type' => 'totally_not_a_block', 'attrs' => []],
        ]);
    }

    public function test_validate_tree_rejects_invalid_attribute_value(): void
    {
        $registry = app(BlockTypeRegistry::class);

        $this->expectException(ValidationException::class);

        $registry->validateTree([
            [
                'id' => '01J',
                'type' => 'hero',
                'attrs' => [
                    // 'title' is required by the hero rule set.
                    'subtitle' => 'no title',
                ],
            ],
        ]);
    }

    public function test_validate_tree_accepts_a_known_block(): void
    {
        $registry = app(BlockTypeRegistry::class);

        $registry->validateTree([
            [
                'id' => '01J',
                'type' => 'hero',
                'attrs' => [
                    'title' => 'Hello',
                    'subtitle' => 'World',
                ],
            ],
        ]);

        $this->assertTrue(true); // reached without exception
    }
}
