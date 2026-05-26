<?php

namespace App\Providers;

use App\Support\Auth\SocialProviderRegistry;
use App\Support\Tenancy\PathTenantResolver;
use App\Support\Tenancy\TenantResolver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Register the per-token API rate limiter.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $perMinute = (int) config('api-abilities.rate_limit_per_minute', 60);
            $tokenId = $request->user()?->currentAccessToken()?->id;

            return Limit::perMinute($perMinute)
                ->by($tokenId !== null ? 'token:'.$tokenId : 'ip:'.$request->ip());
        });
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
