<?php

namespace App\Support\Newsletter;

/**
 * Driver interface for newsletter providers.
 *
 * Implementations are registered in NewsletterProviderRegistry. The
 * `subscribe` method should be idempotent — calling it twice with the
 * same email returns the existing subscriber instead of erroring.
 *
 * Drivers MUST persist a row in `cms_newsletter_subscribers` so admins
 * always have the local source of truth, even when sending to a 3rd
 * party (Mailchimp/Resend/ConvertKit).
 */
interface NewsletterProvider
{
    /**
     * Driver id (matches NewsletterProviderRegistry key).
     */
    public function id(): string;

    /**
     * Display label for the admin UI.
     */
    public function label(): string;

    /**
     * Subscribe an email. Returns a normalized result.
     *
     * @return array{ok: bool, id?: int, provider_id?: string|null, message?: string}
     */
    public function subscribe(string $email, ?string $locale = null, ?string $source = null, ?string $ip = null): array;
}
