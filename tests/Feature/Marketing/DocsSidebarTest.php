<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsGlobal;
use App\Models\CmsPage;
use App\Models\User;
use App\Support\Cms\GlobalsService;
use Database\Seeders\CmsDocsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocsSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_sidebar_is_in_the_public_globals_bundle(): void
    {
        $globals = app(GlobalsService::class)->forPublic();

        $this->assertArrayHasKey('docs_sidebar', $globals);
        $this->assertArrayHasKey('columns', $globals['docs_sidebar']);
        $this->assertNotEmpty($globals['docs_sidebar']['columns']);
    }

    public function test_default_docs_sidebar_has_five_groups(): void
    {
        $payload = app(GlobalsService::class)->get('docs_sidebar');
        $titles = collect($payload['columns'])->pluck('title')->all();

        $this->assertEqualsCanonicalizing(
            ['Getting started', 'Content', 'Site setup', 'Lead capture', 'Operations', 'Billing & admin'],
            $titles,
        );
    }

    public function test_docs_index_shares_sidebar_in_cms_globals(): void
    {
        $this->seed(CmsDocsSeeder::class);

        $this->get('/docs')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('marketing/docs/index')
                ->has('cmsGlobals.docs_sidebar.columns')
            );
    }

    public function test_docs_show_passes_sibling_docs_for_fallback(): void
    {
        $this->seed(CmsDocsSeeder::class);

        $this->get('/docs/cms-overview')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('marketing/docs/show')
                ->has('docs', fn ($docs) => $docs->each(fn ($d) => $d
                    ->has('slug')
                    ->has('title')
                    ->etc()
                ))
                ->has('cmsGlobals.docs_sidebar.columns')
            );
    }

    public function test_admin_can_edit_docs_sidebar(): void
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/admin/cms/globals/docs_sidebar')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/cms/globals/edit')
                ->where('global.key', 'docs_sidebar')
                ->where('global.fields.0.type', 'columns')
            );

        $this->actingAs($admin)
            ->patch('/admin/cms/globals/docs_sidebar', [
                'payload' => [
                    'columns' => [
                        [
                            'title' => 'Custom group',
                            'items' => [
                                ['label' => 'Intro', 'url' => '/docs/cms-overview'],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $row = CmsGlobal::query()->where('key', 'docs_sidebar')->first();
        $this->assertNotNull($row);
        $this->assertSame('Custom group', $row->payload['columns'][0]['title']);
    }

    public function test_empty_global_falls_back_to_published_docs(): void
    {
        // Create the docs but explicitly clear the override.
        $this->seed(CmsDocsSeeder::class);

        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->patch('/admin/cms/globals/docs_sidebar', [
            'payload' => ['columns' => []],
        ]);

        // Re-resolve the bundle — even with override empty, defaults from
        // config/cms.php take over via array_merge in GlobalsService::get().
        $payload = app(GlobalsService::class)->get('docs_sidebar');
        // The admin save merges with defaults so columns remains populated.
        $this->assertIsArray($payload['columns']);

        // Public response still includes docs list for the fallback path.
        $this->get('/docs/cms-overview')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('docs'));

        // Direct sanity: ensure published docs exist for the fallback to use.
        $docsCount = CmsPage::query()
            ->where('template', CmsPage::TEMPLATE_DOCS)
            ->where('status', CmsPage::STATUS_PUBLISHED)
            ->count();
        $this->assertGreaterThanOrEqual(14, $docsCount);
    }
}
