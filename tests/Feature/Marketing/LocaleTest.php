<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_query_param_persists_and_is_shared(): void
    {
        $response = $this->get('/?lang=ar');

        $response->assertOk();
        $response->assertInertia(fn ($p) => $p
            ->where('i18n.locale', 'ar')
            ->where('i18n.locales.0', 'en')
        );

        // Cookie is set (we keep it unencrypted via the bootstrap except list);
        // assert by raw header rather than assertCookie() which round-trips
        // through the encrypter.
        $cookieHeader = (string) $response->headers->get('Set-Cookie');
        $this->assertStringContainsString('cms_locale=ar', $cookieHeader);
    }

    public function test_invalid_locale_is_ignored(): void
    {
        $this->get('/?lang=zz')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('i18n.locale', 'en'));
    }

    public function test_cms_page_resolves_locale_preferred_variant(): void
    {
        CmsPage::query()->create([
            'slug' => 'about', 'title' => 'About (EN)', 'locale' => 'en',
            'status' => 'published', 'published_at' => now()->subHour(),
            'template' => 'docs',
        ]);
        CmsPage::query()->create([
            'slug' => 'about', 'title' => 'حول (AR)', 'locale' => 'ar',
            'status' => 'published', 'published_at' => now()->subHour(),
            'template' => 'docs',
        ]);

        $this->get('/docs/about?lang=ar')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('page.title', 'حول (AR)'));

        $this->get('/docs/about?lang=en')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('page.title', 'About (EN)'));
    }

    public function test_unauthored_locale_falls_back_to_default(): void
    {
        CmsPage::query()->create([
            'slug' => 'fr-only-en', 'title' => 'English source', 'locale' => 'en',
            'status' => 'published', 'published_at' => now()->subHour(),
            'template' => 'docs',
        ]);

        // No French variant authored; visitor asks for fr; fallback to en.
        $this->get('/docs/fr-only-en?lang=fr')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('page.title', 'English source'));
    }
}
