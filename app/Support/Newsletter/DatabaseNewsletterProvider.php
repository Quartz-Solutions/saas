<?php

namespace App\Support\Newsletter;

use App\Models\CmsNewsletterSubscriber;

/**
 * Default provider — stores subscribers locally in
 * `cms_newsletter_subscribers`. Admins can later export the list to a
 * real ESP. Idempotent on email; second subscribe restores
 * unsubscribed entries.
 */
class DatabaseNewsletterProvider implements NewsletterProvider
{
    public function id(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return 'Database (local list)';
    }

    public function subscribe(string $email, ?string $locale = null, ?string $source = null, ?string $ip = null): array
    {
        $row = CmsNewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $row->locale = $locale ?: ($row->locale ?? 'en');
        $row->source = $source ?: ($row->source ?? null);
        $row->provider = 'database';
        $row->ip = $ip ?: $row->ip;
        $row->unsubscribed_at = null; // resubscribe restores
        if ($row->confirmed_at === null) {
            $row->confirmed_at = now();
        }
        $row->save();

        return ['ok' => true, 'id' => $row->id, 'provider_id' => null];
    }
}
