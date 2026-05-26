<?php

namespace App\Providers;

use App\Support\Auth\SocialProviderRegistry;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Stripe\StripeGateway;
use App\Support\Tenancy\PathTenantResolver;
use App\Support\Tenancy\TenantResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SocialProviderRegistry::class, function () {
            $registry = new SocialProviderRegistry;

            if (config('services.google.client_id')) {
                $registry->register('google', 'Google', 'google');
            }

            if (config('services.github.client_id')) {
                $registry->register('github', 'GitHub', 'github');
            }

            return $registry;
        });

        $this->app->singleton(TenantResolver::class, PathTenantResolver::class);

        // Billing gateway registry. Drivers are registered here based on
        // config/billing.php flags. Resolve gateways via the registry —
        // never via app(StripeGateway::class) directly.
        $this->app->singleton(StripeClient::class, function () {
            $secret = config('billing.gateways.stripe.secret');

            return new StripeClient([
                'api_key' => $secret ?: 'sk_test_placeholder',
                'stripe_version' => config('billing.gateways.stripe.api_version'),
            ]);
        });

        $this->app->singleton(GatewayRegistry::class, function ($app) {
            $registry = new GatewayRegistry;

            if (config('billing.gateways.stripe.enabled')) {
                $registry->register(new StripeGateway(
                    client: $app->make(StripeClient::class),
                    webhookSecret: (string) config('billing.gateways.stripe.webhook_secret', ''),
                    webhookTolerance: (int) config('billing.gateways.stripe.webhook_tolerance_seconds', 300),
                ));
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
