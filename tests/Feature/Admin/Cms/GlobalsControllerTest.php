<?php

namespace Tests\Feature\Admin\Cms;

use App\Models\CmsGlobal;
use App\Models\User;
use App\Support\Cms\GlobalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalsControllerTest extends TestCase
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
        $this->actingAs($user)->get('/admin/cms/globals')->assertStatus(403);
    }

    public function test_index_lists_known_globals(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/cms/globals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cms/globals/index')
                ->has('globals')
                ->where('globals.0.key', fn ($k) => is_string($k) && $k !== '')
            );
    }

    public function test_edit_returns_schema_and_merged_payload(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/cms/globals/brand')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/cms/globals/edit')
                ->where('global.key', 'brand')
                ->has('global.fields')
                ->has('global.payload.brand_color')
            );
    }

    public function test_update_persists_payload_and_busts_cache(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->patch('/admin/cms/globals/brand', [
                'payload' => [
                    'brand_color' => '#ff0000',
                    'accent_color' => '#00ff00',
                ],
            ])
            ->assertRedirect();

        $row = CmsGlobal::query()->where('key', 'brand')->first();
        $this->assertNotNull($row);
        $this->assertSame('#ff0000', $row->payload['brand_color']);
        $this->assertSame('#00ff00', $row->payload['accent_color']);

        $svc = app(GlobalsService::class);
        $this->assertSame('#ff0000', $svc->get('brand')['brand_color']);
    }

    public function test_update_drops_unknown_payload_keys(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->patch('/admin/cms/globals/brand', [
            'payload' => [
                'brand_color' => '#abcdef',
                'someone_else' => 'nope',
            ],
        ]);

        $row = CmsGlobal::query()->where('key', 'brand')->first();
        $this->assertSame('#abcdef', $row->payload['brand_color']);
        $this->assertArrayNotHasKey('someone_else', $row->payload);
    }

    public function test_unknown_global_404s(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin)->get('/admin/cms/globals/nope')->assertNotFound();
    }

    public function test_public_share_exposes_globals_to_marketing_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('cmsGlobals.brand')
                ->has('cmsGlobals.header_menu.items')
                ->has('cmsGlobals.footer_menu.columns')
            );
    }
}
