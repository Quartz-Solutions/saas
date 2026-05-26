<?php

use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\FeatureFlagOverridesController;
use App\Http\Controllers\Admin\FeatureFlagsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TenantsAdminController;
use App\Http\Controllers\Admin\WebhookEventsController;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| Admin scope — /admin/...
| Super-Admin-only. Bypasses tenant resolution. Sidebar nav lives in
| `resources/js/layouts/admin-layout.tsx`.
|---------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin.scope', 'role:Super Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Dashboard
        Route::inertia('/', 'admin/dashboard')->name('dashboard');

        // Stop impersonation — must be available to anyone in an impersonation
        // session (the Super Admin is logged in as the target), so it bypasses
        // the role gate via its own middleware group below.

        // Tenants
        Route::get('tenants', [TenantsAdminController::class, 'index'])
            ->name('tenants.index');
        Route::get('tenants/{tenant}', [TenantsAdminController::class, 'show'])
            ->name('tenants.show');
        Route::post('tenants/{tenant}/impersonate', [TenantsAdminController::class, 'impersonate'])
            ->name('tenants.impersonate');

        // Webhook events
        Route::get('webhooks', [WebhookEventsController::class, 'index'])
            ->name('webhooks.index');
        Route::get('webhooks/{webhookEvent}', [WebhookEventsController::class, 'show'])
            ->name('webhooks.show');
        Route::post('webhooks/{webhookEvent}/replay', [WebhookEventsController::class, 'replay'])
            ->name('webhooks.replay');

        // Audit log
        Route::get('audit', [AuditLogsController::class, 'index'])
            ->name('audit.index');

        // Feature flags + per-tenant overrides
        Route::resource('feature-flags', FeatureFlagsController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        Route::post('feature-flags/{feature_flag}/overrides', [FeatureFlagOverridesController::class, 'store'])
            ->name('feature-flags.overrides.store');
        Route::patch('feature-flags/{feature_flag}/overrides/{override}', [FeatureFlagOverridesController::class, 'update'])
            ->name('feature-flags.overrides.update');
        Route::delete('feature-flags/{feature_flag}/overrides/{override}', [FeatureFlagOverridesController::class, 'destroy'])
            ->name('feature-flags.overrides.destroy');

        // App settings — Super Admin manages env-style runtime config.
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/{group}/test', [SettingsController::class, 'test'])->name('settings.test');
    });

// Stop impersonation — gated only on `auth`. The current request's auth user
// is the *target*, not the Super Admin, so requiring `role:Super Admin`
// would lock the impersonator out of returning to their original account.
Route::middleware(['auth'])
    ->post('admin/stop-impersonating', [TenantsAdminController::class, 'stopImpersonation'])
    ->name('admin.stop-impersonating');
