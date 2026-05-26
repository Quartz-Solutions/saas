<?php

namespace App\Support\Billing;

use RuntimeException;

/**
 * Driver registry for payment / subscription gateways.
 *
 * Mirrors the SocialProviderRegistry pattern from Phase 1. Bound as a
 * singleton in AppServiceProvider::register() and populated based on
 * config('billing.gateways.*.enabled'). Controllers + the BillingService
 * resolve gateways through here — never via app(StripeGateway::class).
 */
class GatewayRegistry
{
    /**
     * @var array<string, PaymentGateway>
     */
    private array $gateways = [];

    /**
     * Register a gateway instance under its `id()`.
     */
    public function register(PaymentGateway $gateway): self
    {
        $this->gateways[$gateway->id()] = $gateway;

        return $this;
    }

    /**
     * Find a gateway by id or return null.
     */
    public function find(string $id): ?PaymentGateway
    {
        return $this->gateways[$id] ?? null;
    }

    /**
     * Resolve a gateway by id or throw.
     */
    public function get(string $id): PaymentGateway
    {
        $gateway = $this->find($id);

        if ($gateway === null) {
            throw new RuntimeException("Gateway [{$id}] is not registered. Check config/billing.php gateways.{$id}.enabled and the matching env vars.");
        }

        return $gateway;
    }

    /**
     * Check membership without resolving.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->gateways);
    }

    /**
     * @return array<int, string> Ids of all registered gateways.
     */
    public function ids(): array
    {
        return array_keys($this->gateways);
    }

    /**
     * @return array<int, PaymentGateway>
     */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    /**
     * Resolve a subscription-capable gateway, or throw if the driver
     * exists but does not handle subscriptions.
     */
    public function subscriptions(string $id): SubscriptionGateway
    {
        $gateway = $this->get($id);

        if (! $gateway instanceof SubscriptionGateway) {
            throw new RuntimeException("Gateway [{$id}] does not implement SubscriptionGateway.");
        }

        return $gateway;
    }
}
