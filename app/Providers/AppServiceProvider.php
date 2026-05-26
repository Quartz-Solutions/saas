<?php

namespace App\Providers;

use App\Listeners\AlertOnNewDeviceLogin;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Support\Auth\PwnedPasswords;
use App\Support\Auth\SocialProviderRegistry;
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
    }

    protected function configureAuditObservers(): void
    {
        Tenant::observe(AuditObserver::class);
        TenantMembership::observe(AuditObserver::class);
        User::observe(AuditObserver::class);

        if (class_exists(Subscription::class)) {
            Subscription::observe(AuditObserver::class);
        }
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
