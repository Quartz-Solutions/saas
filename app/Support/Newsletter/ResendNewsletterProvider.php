<?php

namespace App\Support\Newsletter;

use App\Models\CmsNewsletterSubscriber;
use Illuminate\Support\Facades\Http;

/**
 * Resend Audiences API driver. Posts the subscriber to the configured
 * audience. Falls back to local-only persistence when not configured.
 *
 * Env / config required:
 *   cms.newsletter.resend.api_key
 *   cms.newsletter.resend.audience_id
 */
class ResendNewsletterProvider implements NewsletterProvider
{
    public function id(): string
    {
        return 'resend';
    }

    public function label(): string
    {
        return 'Resend';
    }

    public function subscribe(string $email, ?string $locale = null, ?string $source = null, ?string $ip = null): array
    {
        $apiKey = (string) config('cms.newsletter.resend.api_key');
        $audience = (string) config('cms.newsletter.resend.audience_id');

        if ($apiKey === '' || $audience === '') {
            $local = (new DatabaseNewsletterProvider)->subscribe($email, $locale, $source, $ip);

            return ['ok' => true, 'id' => $local['id'] ?? null, 'message' => 'Resend not configured, stored locally.'];
        }

        $response = Http::withToken($apiKey)
            ->timeout(8)
            ->post("https://api.resend.com/audiences/{$audience}/contacts", [
                'email' => $email,
                'unsubscribed' => false,
            ]);

        $providerId = $response->json('id');

        $row = CmsNewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $row->locale = $locale ?: ($row->locale ?? 'en');
        $row->source = $source ?: $row->source;
        $row->provider = 'resend';
        $row->provider_id = is_string($providerId) ? $providerId : null;
        $row->ip = $ip ?: $row->ip;
        $row->unsubscribed_at = null;
        if ($row->confirmed_at === null) {
            $row->confirmed_at = now();
        }
        $row->save();

        return ['ok' => $response->successful(), 'id' => $row->id, 'provider_id' => $row->provider_id];
    }
}
