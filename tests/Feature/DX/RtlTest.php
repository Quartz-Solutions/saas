<?php

namespace Tests\Feature\DX;

use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Spot-check that the root `<html>` element flips to RTL when the active
 * locale starts with `ar`, and stays LTR otherwise. Touches Inertia's
 * pre-render path indirectly — we just inspect the raw HTML.
 */
class RtlTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_dir_is_rtl_when_locale_starts_with_ar(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        // The cleanest seam is the middleware: it reads ?locale=. We tag
        // the request directly here.
        $response = $this->actingAs($user)
            ->get(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]).'?locale=ar');

        $response->assertOk();
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }

    public function test_html_dir_is_ltr_by_default(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create($user, ['name' => 'Acme']);

        $response = $this->actingAs($user)
            ->get(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));

        $response->assertOk();
        $this->assertStringContainsString('dir="ltr"', $response->getContent());
    }

    public function test_dir_attribute_set_when_locale_facade_used_directly(): void
    {
        App::setLocale('ar');

        // The blade view reads app()->getLocale() at render time. Re-render
        // by visiting any auth page; we don't need a real tenant here.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('dir="rtl"', $content);
        $this->assertStringContainsString('lang="ar"', $content);
    }
}
