<?php

namespace App\Support\Newsletter;

use App\Models\CmsNewsletterSubscriber;
use Illuminate\Support\Facades\Http;

/**
 * Mailchimp Marketing API v3 driver. Subscribes the email into the
 * configured audience (list) ID. Always also persists a local row so
 * the admin inbox stays in sync.
 *
 * Env / config required:
 *   cms.newsletter.mailchimp.api_key      (XXXX-usX format)
 *   cms.newsletter.mailchimp.audience_id  (audience/list id)
 *   cms.newsletter.mailchimp.server       (extracted from api_key, e.g. us1)
 *   cms.newsletter.mailchimp.double_opt_in (bool)
 */
class MailchimpNewsletterProvider implements NewsletterProvider
{
    public function id(): string
    {
        return 'mailchimp';
    }

    public function label(): string
    {
        return 'Mailchimp';
    }

    public function subscribe(string $email, ?string $locale = null, ?string $source = null, ?string $ip = null): array
    {
        $apiKey = (string) config('cms.newsletter.mailchimp.api_key');
        $audience = (string) config('cms.newsletter.mailchimp.audience_id');

        if ($apiKey === '' || $audience === '') {
            // Mis-configured — still persist locally so the lead isn't lost.
            $local = (new DatabaseNewsletterProvider)->subscribe($email, $locale, $source, $ip);

            return ['ok' => true, 'id' => $local['id'] ?? null, 'provider_id' => null, 'message' => 'Mailchimp not configured, stored locally.'];
        }

        $server = $this->server($apiKey);
        $doubleOptIn = (bool) (config('cms.newsletter.mailchimp.double_opt_in') ?? false);
        $hash = md5(strtolower($email));

        $response = Http::withBasicAuth('anystring', $apiKey)
            ->timeout(8)
            ->put("https://{$server}.api.mailchimp.com/3.0/lists/{$audience}/members/{$hash}", [
                'email_address' => $email,
                'status_if_new' => $doubleOptIn ? 'pending' : 'subscribed',
                'language' => $locale ?: 'en',
            ]);

        $providerId = $response->json('id');

        $row = CmsNewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $row->locale = $locale ?: ($row->locale ?? 'en');
        $row->source = $source ?: $row->source;
        $row->provider = 'mailchimp';
        $row->provider_id = is_string($providerId) ? $providerId : null;
        $row->ip = $ip ?: $row->ip;
        $row->unsubscribed_at = null;
        if ($row->confirmed_at === null) {
            $row->confirmed_at = now();
        }
        $row->save();

        return ['ok' => $response->successful(), 'id' => $row->id, 'provider_id' => $row->provider_id];
    }

    private function server(string $apiKey): string
    {
        $dash = strrpos($apiKey, '-');
        if ($dash === false) {
            return (string) (config('cms.newsletter.mailchimp.server') ?: 'us1');
        }

        return substr($apiKey, $dash + 1) ?: 'us1';
    }
}
