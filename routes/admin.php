<?php

use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureFlagOverridesController;
use App\Http\Controllers\Admin\FeatureFlagsController;
use App\Http\Controllers\Admin\GatewaysController;
use App\Http\Controllers\Admin\PlansController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriptionActionsController;
use App\Http\Controllers\Admin\SubscriptionsAdminController;
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
        Route::get('/', DashboardController::class)->name('dashboard');

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

        // Payment gateways — credentials + enable per gateway.
        Route::get('gateways', [GatewaysController::class, 'index'])->name('gateways.index');
        Route::get('gateways/{gateway}', [GatewaysController::class, 'edit'])->name('gateways.edit');
        Route::patch('gateways/{gateway}', [GatewaysController::class, 'update'])->name('gateways.update');

        // Plans — DB-owned plan catalog (Phase A).
        Route::resource('plans', PlansController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('plans/{plan}/restore', [PlansController::class, 'restore'])
            ->name('plans.restore');

        // Subscriptions — admin index + show (Phase B).
        Route::get('subscriptions', [SubscriptionsAdminController::class, 'index'])
            ->name('subscriptions.index');
        Route::get('subscriptions/export', [SubscriptionsAdminController::class, 'export'])
            ->name('subscriptions.export');
        Route::get('subscriptions/{subscription}', [SubscriptionsAdminController::class, 'show'])
            ->name('subscriptions.show');

        // Subscription admin actions (Phase C).
        Route::post('subscriptions/{subscription}/change-plan', [SubscriptionActionsController::class, 'changePlan'])
            ->name('subscriptions.change-plan');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionActionsController::class, 'cancel'])
            ->name('subscriptions.cancel');
        Route::post('subscriptions/{subscription}/reactivate', [SubscriptionActionsController::class, 'reactivate'])
            ->name('subscriptions.reactivate');
        Route::post('subscriptions/{subscription}/credit', [SubscriptionActionsController::class, 'applyCredit'])
            ->name('subscriptions.credit');
        Route::post('subscriptions/{subscription}/comp', [SubscriptionActionsController::class, 'compMonths'])
            ->name('subscriptions.comp');
        Route::post('payments/{payment}/refund', [SubscriptionActionsController::class, 'refundPayment'])
            ->name('payments.refund');
        Route::post('invoices/{invoice}/manual-payment', [SubscriptionActionsController::class, 'recordManualPayment'])
            ->name('invoices.manual-payment');
    });

// Stop impersonation — gated only on `auth`. The current request's auth user
// is the *target*, not the Super Admin, so requiring `role:Super Admin`
// would lock the impersonator out of returning to their original account.
Route::middleware(['auth'])
    ->post('admin/stop-impersonating', [TenantsAdminController::class, 'stopImpersonation'])
    ->name('admin.stop-impersonating');
