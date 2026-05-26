<?php

namespace App\Support\Auth;

use InvalidArgumentException;

/**
 * Driver registry for Socialite providers.
 *
 * Mirrors the HardwareRegistry pattern: register provider keys at boot in
 * AppServiceProvider::register(), then resolve through this registry rather
 * than calling Socialite::driver() directly. This gives us a single seam to
 * gate which providers are enabled in a given environment and to keep the
 * provider list discoverable for UI affordances (login page social buttons).
 */
class SocialProviderRegistry
{
    /**
     * @var array<string, array{label: string, icon: string}>
     */
    protected array $providers = [];

    /**
     * Register an enabled provider.
     */
    public function register(string $key, string $label, string $icon = ''): void
    {
        $this->providers[$key] = [
            'label' => $label,
            'icon' => $icon,
        ];
    }

    /**
     * Whether a given provider is registered (and therefore enabled).
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->providers);
    }

    /**
     * Assert a provider is registered or throw.
     */
    public function ensure(string $key): void
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Social provider [{$key}] is not registered.");
        }
    }

    /**
     * Get all enabled providers keyed by provider id.
     *
     * @return array<string, array{label: string, icon: string}>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Provider keys as a flat list — useful for the login page Inertia share.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
