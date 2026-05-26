<?php

namespace App\Providers;

use App\Support\Admin\AppSettingsService;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Hydrates runtime config from the `app_settings` table on every request.
 * Registered FIRST in bootstrap/providers.php so downstream providers —
 * Sentry, Socialite, Stripe — read the overridden values when they boot.
 *
 * Failures here must NOT take the app down: if the DB is unreachable or
 * the migration hasn't run yet, we silently fall back to .env.
 */
class AppSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppSettingsService::class);
    }

    public function boot(): void
    {
        try {
            $this->app->make(AppSettingsService::class)->applyOverrides();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
