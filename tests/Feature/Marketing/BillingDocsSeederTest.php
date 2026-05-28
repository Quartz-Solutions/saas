<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsPage;
use App\Support\Cms\BlockTypeRegistry;
use App\Support\Cms\GlobalsService;
use Database\Seeders\BillingDocsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingDocsSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_SLUGS = [
        'admin-plans',
        'admin-subscriptions',
        'admin-checkout',
        'admin-gateways',
        'admin-settings',
    ];

    public function test_seeder_creates_all_admin_doc_pages_as_published_docs(): void
    {
        $this->seed(BillingDocsSeeder::class);

        foreach (self::EXPECTED_SLUGS as $slug) {
            $page = CmsPage::query()->where('slug', $slug)->first();
            $this->assertNotNull($page, "Missing seeded doc: {$slug}");
            $this->assertSame(CmsPage::STATUS_PUBLISHED, $page->status);
            $this->assertSame(CmsPage::TEMPLATE_DOCS, $page->template);
            $this->assertIsArray($page->body_blocks);
            $this->assertGreaterThan(0, count($page->body_blocks));
        }
    }

    public function test_admin_plans_doc_renders_with_blocks(): void
    {
        $this->seed(BillingDocsSeeder::class);

        $this->get('/docs/admin-plans')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('marketing/docs/show')
                ->where('page.title', 'Plans')
                ->where('page.body_blocks.0.type', 'rich_text')
            );
    }

    public function test_sidebar_groups_include_billing_admin_group(): void
    {
        $this->seed(BillingDocsSeeder::class);

        $payload = app(GlobalsService::class)->get('docs_sidebar');
        $titles = collect($payload['columns'])->pluck('title')->all();

        $this->assertContains('Billing & admin', $titles);

        $billing = collect($payload['columns'])->firstWhere('title', 'Billing & admin');
        $urls = collect($billing['items'])->pluck('url')->all();

        foreach (self::EXPECTED_SLUGS as $slug) {
            $this->assertContains('/docs/'.$slug, $urls);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(BillingDocsSeeder::class);
        $this->seed(BillingDocsSeeder::class);
        $this->seed(BillingDocsSeeder::class);

        $count = CmsPage::query()->whereIn('slug', self::EXPECTED_SLUGS)->count();
        $this->assertSame(count(self::EXPECTED_SLUGS), $count);
    }

    public function test_every_admin_doc_uses_only_registered_block_types(): void
    {
        $this->seed(BillingDocsSeeder::class);
        $registered = app(BlockTypeRegistry::class)->ids();

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
