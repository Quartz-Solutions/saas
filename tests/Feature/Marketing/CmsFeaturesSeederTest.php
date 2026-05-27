<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsFeature;
use Database\Seeders\CmsFeaturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsFeaturesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_every_capability_feature(): void
    {
        $this->seed(CmsFeaturesSeeder::class);

        $required = [
            // Foundation
            'multi-tenant', 'multi-gateway-billing', 'admin-scope',
            'typed-end-to-end', 'notifications', 'compliance',
            // Content & marketing CMS
            'block-cms', 'media-library', 'globals', 'blog',
            'forms-builder', 'newsletter', 'redirects', 'seo-toolkit',
            'i18n', 'versions-preview', 'live-preview', 'cache-swr',
            // Auth & identity
            'social-login', 'magic-link', 'two-factor', 'session-mgmt', 'pwned-check',
            // API & integrations
            'api-tokens', 'outbound-webhooks', 'feature-flags',
            // Operations
            'impersonation', 'audit-log', 'webhook-replay', 'runtime-settings',
            // Billing extras
            'dunning', 'multi-currency', 'coupons',
            // Compliance & ops
            'gdpr-export', 'daily-backups', 'sentry-ready',
            // DX & polish
            'dark-mode', 'onboarding-wizard', 'demo-seeder',
        ];

        foreach ($required as $slug) {
            $row = CmsFeature::query()->where('slug', $slug)->first();
            $this->assertNotNull($row, "Missing seeded feature: {$slug}");
            $this->assertTrue($row->is_active, "Feature {$slug} should be active");
            $this->assertNotEmpty($row->title, "Feature {$slug} should have a title");
            $this->assertNotEmpty($row->description, "Feature {$slug} should have a description");
            $this->assertNotEmpty($row->icon, "Feature {$slug} should have an icon");
        }

        $this->assertSame(count($required), CmsFeature::query()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CmsFeaturesSeeder::class);
        $first = CmsFeature::query()->count();

        $this->seed(CmsFeaturesSeeder::class);
        $this->seed(CmsFeaturesSeeder::class);

        $this->assertSame($first, CmsFeature::query()->count(), 'Re-running should not duplicate rows.');
    }

    public function test_features_are_sorted_in_seeder_order(): void
    {
        $this->seed(CmsFeaturesSeeder::class);

        $sorted = CmsFeature::query()->orderBy('sort_order')->limit(3)->pluck('slug')->all();
        $this->assertSame(['multi-tenant', 'multi-gateway-billing', 'admin-scope'], $sorted);
    }
}
