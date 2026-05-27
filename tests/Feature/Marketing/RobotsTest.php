<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;

class RobotsTest extends TestCase
{
    public function test_robots_txt_disallows_all_in_non_production(): void
    {
        // Tests run under env=testing, which is not "production".
        $response = $this->get('/robots.txt');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString('User-agent: *', $body);
        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringContainsString('Sitemap:', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
    }

    public function test_robots_txt_in_production_allows_marketing_and_disallows_private_scopes(): void
    {
        app()['env'] = 'production';

        $response = $this->get('/robots.txt');
        $response->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString('User-agent: *', $body);
        // Private scopes blocked
        $this->assertStringContainsString('Disallow: /admin/', $body);
        $this->assertStringContainsString('Disallow: /t/', $body);
        $this->assertStringContainsString('Disallow: /account/', $body);
        $this->assertStringContainsString('Disallow: /checkout/', $body);
        $this->assertStringContainsString('Disallow: /api/', $body);
        // No catch-all disallow
        $this->assertStringNotContainsString("Disallow: /\n", $body);
        $this->assertStringContainsString('Sitemap:', $body);
    }
}
