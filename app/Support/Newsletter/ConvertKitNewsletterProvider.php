<?php

namespace App\Support\Newsletter;

use App\Models\CmsNewsletterSubscriber;
use Illuminate\Support\Facades\Http;

/**
 * ConvertKit v3 API driver. Subscribes via a configured `form_id`.
 * Falls back to local-only persistence when not configured.
 *
 * Env / config required:
 *   cms.newsletter.convertkit.api_key
 *   cms.newsletter.convertkit.form_id
 */
class ConvertKitNewsletterProvider implements NewsletterProvider
{
    public function id(): string
    {
        return 'convertkit';
    }

    public function label(): string
    {
        return 'ConvertKit';
    }

    public function subscribe(string $email, ?string $locale = null, ?string $source = null, ?string $ip = null): array
    {
        $apiKey = (string) config('cms.newsletter.convertkit.api_key');
        $formId = (string) config('cms.newsletter.convertkit.form_id');

        if ($apiKey === '' || $formId === '') {
            $local = (new DatabaseNewsletterProvider)->subscribe($email, $locale, $source, $ip);

            return ['ok' => true, 'id' => $local['id'] ?? null, 'message' => 'ConvertKit not configured, stored locally.'];
        }

        $response = Http::timeout(8)
            ->post("https://api.convertkit.com/v3/forms/{$formId}/subscribe", [
                'api_key' => $apiKey,
                'email' => $email,
            ]);

        $providerId = $response->json('subscription.subscriber.id');

        $row = CmsNewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $row->locale = $locale ?: ($row->locale ?? 'en');
        $row->source = $source ?: $row->source;
        $row->provider = 'convertkit';
        $row->provider_id = $providerId !== null ? (string) $providerId : null;
        $row->ip = $ip ?: $row->ip;
        $row->unsubscribed_at = null;
        if ($row->confirmed_at === null) {
            $row->confirmed_at = now();
        }
        $row->save();

        return ['ok' => $response->successful(), 'id' => $row->id, 'provider_id' => $row->provider_id];
    }
}
