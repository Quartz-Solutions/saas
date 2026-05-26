<?php

namespace Tests\Feature\Billing;

use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Stripe\StripeGateway;
use RuntimeException;
use Stripe\StripeClient;
use Tests\TestCase;

class GatewayRegistryTest extends TestCase
{
    public function test_get_throws_when_gateway_not_registered(): void
    {
        $registry = new GatewayRegistry;

        $this->expectException(RuntimeException::class);
        $registry->get('paypal');
    }

    public function test_find_returns_null_when_missing(): void
    {
        $registry = new GatewayRegistry;

        $this->assertNull($registry->find('paypal'));
        $this->assertFalse($registry->has('paypal'));
    }

    public function test_register_and_resolve_stripe(): void
    {
        $registry = new GatewayRegistry;
        $gateway = new StripeGateway(new StripeClient(['api_key' => 'sk_test_x']));
        $registry->register($gateway);

        $this->assertTrue($registry->has('stripe'));
        $this->assertSame($gateway, $registry->get('stripe'));
        $this->assertSame(['stripe'], $registry->ids());
    }

    public function test_subscriptions_resolver_returns_subscription_gateway(): void
    {
        $registry = new GatewayRegistry;
        $gateway = new StripeGateway(new StripeClient(['api_key' => 'sk_test_x']));
        $registry->register($gateway);

        $this->assertSame($gateway, $registry->subscriptions('stripe'));
    }

    public function test_stripe_is_registered_when_secret_present(): void
    {
        config()->set('billing.gateways.stripe.enabled', true);
        config()->set('billing.gateways.stripe.secret', 'sk_test_xxx');
        $this->app->forgetInstance(GatewayRegistry::class);

        /** @var GatewayRegistry $registry */
        $registry = app(GatewayRegistry::class);

        $this->assertTrue($registry->has('stripe'));
    }

    public function test_stripe_is_not_registered_when_disabled(): void
    {
        config()->set('billing.gateways.stripe.enabled', false);
        $this->app->forgetInstance(GatewayRegistry::class);

        /** @var GatewayRegistry $registry */
        $registry = app(GatewayRegistry::class);

        $this->assertFalse($registry->has('stripe'));
    }
}
