<?php

namespace App\Providers;

use App\Events\CheckoutAbandoned;
use App\Listeners\AlertOnNewDeviceLogin;
use App\Listeners\SendCheckoutAbandonmentReminder;
use App\Models\AppSetting;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Support\Auth\PwnedPasswords;
use App\Support\Auth\SocialProviderRegistry;
use App\Support\Billing\Aps\ApsGateway;
use App\Support\Billing\Billplz\BillplzGateway;
use App\Support\Billing\Fawry\FawryGateway;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Geidea\GeideaGateway;
use App\Support\Billing\HitPay\HitPayGateway;
use App\Support\Billing\HyperPay\HyperPayGateway;
use App\Support\Billing\Ipay88\Ipay88Gateway;
use App\Support\Billing\MyFatoorah\MyFatoorahGateway;
use App\Support\Billing\Paymob\PaymobGateway;
use App\Support\Billing\PayPal\PayPalGateway;
use App\Support\Billing\PayTabs\PayTabsGateway;
use App\Support\Billing\Stripe\StripeGateway;
use App\Support\Billing\Telr\TelrGateway;
use App\Support\Tenancy\PathTenantResolver;
use App\Support\Tenancy\TenantResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        $this->app->singleton(PwnedPasswords::class);

        $this->app->singleton(StripeClient::class, function () {
            $secret = config('billing.gateways.stripe.secret');

            return new StripeClient([
                'api_key' => $secret ?: 'sk_test_placeholder',
                'stripe_version' => config('billing.gateways.stripe.api_version'),
            ]);
        });

        $this->app->singleton(GatewayRegistry::class, function ($app) {
            $registry = new GatewayRegistry;

            // Stripe — shipped + production-tested.
            if (config('billing.gateways.stripe.enabled')) {
                $registry->register(new StripeGateway(
                    client: $app->make(StripeClient::class),
                    webhookSecret: (string) config('billing.gateways.stripe.webhook_secret', ''),
                    webhookTolerance: (int) config('billing.gateways.stripe.webhook_tolerance_seconds', 300),
                ));
            }

            // Non-Stripe drivers — full CheckoutGateway + PaymentGateway impls
            // shipped in Phase 4. Registered only when explicitly enabled in
            // config; sandbox verification still pending for the open issues
            // listed in agent-os/product/checkout.md §15.
            $plannedDrivers = [
                'paypal' => PayPalGateway::class,
                'paymob' => PaymobGateway::class,
                'fawry' => FawryGateway::class,
                'paytabs' => PayTabsGateway::class,
                'geidea' => GeideaGateway::class,
                'aps' => ApsGateway::class,
                'telr' => TelrGateway::class,
                'hyperpay' => HyperPayGateway::class,
                'myfatoorah' => MyFatoorahGateway::class,
                'hitpay' => HitPayGateway::class,
                'billplz' => BillplzGateway::class,
                'ipay88' => Ipay88Gateway::class,
            ];

            foreach ($plannedDrivers as $id => $class) {
                if (config("billing.gateways.{$id}.enabled") && class_exists($class)) {
                    $registry->register($app->make($class));
                }
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
        $this->configureAuditObservers();
        $this->configureRateLimiters();
        $this->configureLoginAlertListener();
        $this->configureCheckoutListeners();
    }

    protected function configureCheckoutListeners(): void
    {
        Event::listen(
            CheckoutAbandoned::class,
            SendCheckoutAbandonmentReminder::class,
        );
    }

    protected function configureAuditObservers(): void
    {
        AppSetting::observe(AuditObserver::class);
        Plan::observe(AuditObserver::class);
        Tenant::observe(AuditObserver::class);
        TenantMembership::observe(AuditObserver::class);
        User::observe(AuditObserver::class);

        if (class_exists(Subscription::class)) {
            Subscription::observe(AuditObserver::class);
        }

        CheckoutSession::observe(AuditObserver::class);
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            $email = (string) $request->input('email', $request->ip() ?? 'unknown');

            return Limit::perHour(3)->by(Str::lower($email));
        });

        RateLimiter::for('2fa', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier()
                ?? $request->session()->get('login.id')
                ?? $request->ip()
                ?? 'unknown';

            return Limit::perMinute(5)->by((string) $key);
        });

        RateLimiter::for('api', function (Request $request) {
            $perMinute = (int) config('api-abilities.rate_limit_per_minute', 60);
            $tokenId = $request->user()?->currentAccessToken()?->id;

            return Limit::perMinute($perMinute)
                ->by($tokenId !== null ? 'token:'.$tokenId : 'ip:'.$request->ip());
        });
    }

    protected function configureLoginAlertListener(): void
    {
        Event::listen(Login::class, AlertOnNewDeviceLogin::class);
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
