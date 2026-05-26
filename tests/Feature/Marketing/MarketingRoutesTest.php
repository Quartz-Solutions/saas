<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsPage;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MarketingRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_public_layout(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/home')
            ->has('features')
            ->has('features.0.title')
        );
    }

    public function test_home_page_is_public_no_redirect(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_pricing_page_renders_with_seeded_plans(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->get(route('marketing.pricing'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/pricing')
            ->has('plans', 3)
            ->where('plans.0.slug', 'free')
            ->where('plans.1.slug', 'pro')
            ->where('plans.2.slug', 'enterprise')
            ->where('plans.0.price_cents', 0)
            ->where('plans.1.price_cents', 2900)
            ->where('trialDays', 14)
        );
    }

    public function test_docs_index_lists_published_docs_pages(): void
    {
        CmsPage::factory()->create([
            'slug' => 'getting-started',
            'title' => 'Getting Started',
            'template' => CmsPage::TEMPLATE_DOCS,
            'status' => CmsPage::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        CmsPage::factory()->draft()->create([
            'template' => CmsPage::TEMPLATE_DOCS,
        ]);

        $response = $this->get(route('marketing.docs.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/docs/index')
            ->has('pages', 1)
            ->where('pages.0.slug', 'getting-started')
            ->where('pages.0.title', 'Getting Started')
        );
    }

    public function test_docs_show_returns_cached_published_page(): void
    {
        CmsPage::factory()->create([
            'slug' => 'getting-started',
            'title' => 'Getting Started',
            'body_html' => '<h1>Hello world</h1>',
            'template' => CmsPage::TEMPLATE_DOCS,
            'status' => CmsPage::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
        ]);

        $response = $this->get(route('marketing.docs.show', ['slug' => 'getting-started']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/docs/show')
            ->where('page.slug', 'getting-started')
            ->where('page.title', 'Getting Started')
            ->where('page.body_html', '<h1>Hello world</h1>')
        );
    }

    public function test_docs_show_404s_for_unknown_slug(): void
    {
        $this->get(route('marketing.docs.show', ['slug' => 'nope-not-real']))
            ->assertNotFound();
    }

    public function test_docs_show_404s_for_draft_page(): void
    {
        CmsPage::factory()->draft()->create([
            'slug' => 'draft-doc',
            'template' => CmsPage::TEMPLATE_DOCS,
        ]);

        $this->get(route('marketing.docs.show', ['slug' => 'draft-doc']))
            ->assertNotFound();
    }

    public function test_legal_privacy_renders(): void
    {
        $response = $this->get(route('marketing.legal.show', ['type' => 'privacy']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/legal/privacy')
            ->where('type', 'privacy')
            ->has('effectiveDate')
            ->has('companyName')
        );
    }

    public function test_legal_terms_renders(): void
    {
        $response = $this->get(route('marketing.legal.show', ['type' => 'terms']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/legal/terms')
            ->where('type', 'terms')
        );
    }

    public function test_legal_cookies_renders(): void
    {
        $response = $this->get(route('marketing.legal.show', ['type' => 'cookies']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('marketing/legal/cookies')
            ->where('type', 'cookies')
        );
    }

    public function test_legal_show_404s_for_unknown_type(): void
    {
        $this->get('/legal/refund')->assertNotFound();
    }

    public function test_cookie_consent_accepted_sets_cookie(): void
    {
        $response = $this->from(route('home'))->post(
            route('marketing.cookie-consent.store'),
            ['choice' => 'accepted'],
        );

        $response->assertRedirect(route('home'));
        $response->assertCookie('cookie_consent', 'accepted');
    }

    public function test_cookie_consent_rejected_sets_cookie(): void
    {
        $response = $this->from(route('home'))->post(
            route('marketing.cookie-consent.store'),
            ['choice' => 'rejected'],
        );

        $response->assertRedirect(route('home'));
        $response->assertCookie('cookie_consent', 'rejected');
    }

    public function test_cookie_consent_rejects_invalid_choice(): void
    {
        $response = $this->from(route('home'))->post(
            route('marketing.cookie-consent.store'),
            ['choice' => 'maybe'],
        );

        $response->assertRedirect(route('home'));
        $response->assertCookieMissing('cookie_consent');
    }
}
