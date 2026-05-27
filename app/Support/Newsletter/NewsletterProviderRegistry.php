<?php

namespace App\Support\Newsletter;

use RuntimeException;

/**
 * Singleton registry of newsletter providers (database, mailchimp,
 * resend, convertkit, …). Bound in AppServiceProvider::register().
 * Mirrors the GatewayRegistry pattern.
 */
class NewsletterProviderRegistry
{
    /**
     * @var array<string, NewsletterProvider>
     */
    private array $providers = [];

    public function register(NewsletterProvider $provider): self
    {
        $this->providers[$provider->id()] = $provider;

        return $this;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->providers);
    }

    public function get(string $id): NewsletterProvider
    {
        if (! $this->has($id)) {
            throw new RuntimeException("Newsletter provider [{$id}] is not registered.");
        }

        return $this->providers[$id];
    }

    /**
     * Resolve the currently-active provider as configured in
     * config('cms.newsletter.provider'). Falls back to the database
     * provider when unset or invalid.
     */
    public function active(): NewsletterProvider
    {
        $configured = (string) (config('cms.newsletter.provider') ?? 'database');

        return $this->has($configured) ? $this->get($configured) : $this->get('database');
    }

    /**
     * @return array<int, NewsletterProvider>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->providers);
    }
}
