<?php

namespace Tests\Feature\Marketing;

use App\Models\CmsNewsletterSubscriber;
use App\Support\Newsletter\NewsletterProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_persists_local_row_with_database_provider(): void
    {
        $this->postJson('/marketing/newsletter/subscribe', [
            'email' => 'eagle@example.test',
            'source' => 'newsletter_block',
        ])
            ->assertOk()
            ->assertJson(['ok' => true, 'provider' => 'database']);

        $row = CmsNewsletterSubscriber::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('eagle@example.test', $row->email);
        $this->assertSame('database', $row->provider);
        $this->assertNotNull($row->confirmed_at);
    }

    public function test_subscribe_is_idempotent_on_repeat_email(): void
    {
        $this->postJson('/marketing/newsletter/subscribe', ['email' => 'eagle@example.test'])->assertOk();
        $this->postJson('/marketing/newsletter/subscribe', ['email' => 'eagle@example.test'])->assertOk();
        $this->postJson('/marketing/newsletter/subscribe', ['email' => 'eagle@example.test'])->assertOk();

        $this->assertSame(1, CmsNewsletterSubscriber::query()->count());
    }

    public function test_subscribe_validates_email(): void
    {
        $this->postJson('/marketing/newsletter/subscribe', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_honeypot_silently_drops(): void
    {
        $this->postJson('/marketing/newsletter/subscribe', [
            'email' => 'bot@example.test',
            '_honey' => 'spam',
        ])->assertOk();

        $this->assertSame(0, CmsNewsletterSubscriber::query()->count());
    }

    public function test_registry_lists_all_drivers_and_active_falls_back_to_database(): void
    {
        $registry = app(NewsletterProviderRegistry::class);

        $ids = $registry->ids();
        $this->assertContains('database', $ids);
        $this->assertContains('mailchimp', $ids);
        $this->assertContains('resend', $ids);
        $this->assertContains('convertkit', $ids);

        // Unconfigured / unset CMS_NEWSLETTER_PROVIDER → defaults to database.
        $this->assertSame('database', $registry->active()->id());
    }

    public function test_mailchimp_provider_falls_back_to_local_when_unconfigured(): void
    {
        config()->set('cms.newsletter.provider', 'mailchimp');
        config()->set('cms.newsletter.mailchimp.api_key', '');

        $this->postJson('/marketing/newsletter/subscribe', ['email' => 'noconfig@example.test'])
            ->assertOk()
            ->assertJson(['provider' => 'mailchimp']);

        $row = CmsNewsletterSubscriber::query()->where('email', 'noconfig@example.test')->first();
        $this->assertNotNull($row);
    }
}
