<?php

namespace Tests\Feature\DX;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The command palette is a client-side component mounted in the app shell;
 * we verify that the app shell renders for an authenticated user and that
 * the bundle ships `command-palette.tsx`. The actual cmd+k keybind is
 * exercised in JS land — those tests live separately in the JS suite.
 */
class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_palette_module_exists(): void
    {
        $this->assertFileExists(
            resource_path('js/components/command-palette.tsx'),
            'command-palette.tsx must exist for the global cmd+k UI.'
        );
    }

    public function test_command_palette_mounted_in_app_shell(): void
    {
        $contents = file_get_contents(
            resource_path('js/layouts/app/app-sidebar-layout.tsx')
        );

        $this->assertStringContainsString(
            '<CommandPalette />',
            $contents,
            'CommandPalette must be mounted globally in the sidebar layout.'
        );
    }

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $this->actingAs($user)
            ->get(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('dashboard'));
    }
}
