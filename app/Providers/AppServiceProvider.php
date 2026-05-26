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

    /**
     * Attach the AuditObserver to models whose writes we want diffed
     * into `audit_logs`. Subscription is gated on its class existing
     * because Phase 3 may not have merged yet.
     */
    protected function configureAuditObservers(): void
    {
        Tenant::observe(AuditObserver::class);
        TenantMembership::observe(AuditObserver::class);
        User::observe(AuditObserver::class);

        if (class_exists(Subscription::class)) {
            Subscription::observe(AuditObserver::class);
        }
    }

    /**
     * Named rate limiters for auth endpoints. Apply to routes via
     * `->middleware('throttle:login')` etc. Fortify-managed routes are
     * covered by the `login` + `two-factor` limiters in
     * FortifyServiceProvider; the others (`register`, `forgot-password`)
     * are picked up by Fortify's own throttling config and by any custom
     * routes that opt in.
     */
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
    }

    /**
     * Auth\Events\Login fires on every successful authentication. We
     * inspect the previous LoginHistory row for the user and, if the
     * IP/UA has changed, dispatch a NewDeviceLoginAlert notification.
     *
     * Listener implementation lives in App\Listeners\AlertOnNewDeviceLogin.
     */
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
