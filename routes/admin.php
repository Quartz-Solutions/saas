<?php

use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\CheckoutSessionsController;
use App\Http\Controllers\Admin\Cms\BlogPostsController;
use App\Http\Controllers\Admin\Cms\CollectionsController;
use App\Http\Controllers\Admin\Cms\FormsController;
use App\Http\Controllers\Admin\Cms\GlobalsController;
use App\Http\Controllers\Admin\Cms\MediaController;
use App\Http\Controllers\Admin\Cms\PagesController;
use App\Http\Controllers\Admin\Cms\PageVersionsController;
use App\Http\Controllers\Admin\Cms\PreviewLinkController;
use App\Http\Controllers\Admin\Cms\RedirectsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureFlagOverridesController;
use App\Http\Controllers\Admin\FeatureFlagsController;
use App\Http\Controllers\Admin\GatewaysController;
use App\Http\Controllers\Admin\PlansController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubscriptionActionsController;
use App\Http\Controllers\Admin\SubscriptionsAdminController;
use App\Http\Controllers\Admin\TenantsAdminController;
use App\Http\Controllers\Admin\ThemeFontsController;
use App\Http\Controllers\Admin\ThemesController;
use App\Http\Controllers\Admin\UsersAdminController;
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
            ->whereNumber('tenant')
            ->name('tenants.show');
        Route::post('tenants/{tenant}/impersonate', [TenantsAdminController::class, 'impersonate'])
            ->whereNumber('tenant')
            ->name('tenants.impersonate');
        Route::post('tenants/{tenant}/suspend', [TenantsAdminController::class, 'suspend'])
            ->whereNumber('tenant')
            ->name('tenants.suspend');
        Route::post('tenants/{tenantId}/restore', [TenantsAdminController::class, 'restore'])
            ->whereNumber('tenantId')
            ->name('tenants.restore');
        Route::delete('tenants/{tenant}', [TenantsAdminController::class, 'destroy'])
            ->whereNumber('tenant')
            ->name('tenants.destroy');
        Route::delete('tenants/{tenantId}/force', [TenantsAdminController::class, 'forceDelete'])
            ->whereNumber('tenantId')
            ->name('tenants.force-delete');
        Route::get('tenants/{tenant}/gdpr-export', [TenantsAdminController::class, 'gdprExport'])
            ->whereNumber('tenant')
            ->name('tenants.gdpr-export');

        // Tenant member operations.
        Route::delete('tenants/{tenant}/members/{user}', [TenantsAdminController::class, 'removeMember'])
            ->whereNumber('tenant')->whereNumber('user')
            ->name('tenants.members.destroy');
        Route::post('tenants/{tenant}/members/{user}/impersonate', [TenantsAdminController::class, 'impersonateMember'])
            ->whereNumber('tenant')->whereNumber('user')
            ->name('tenants.members.impersonate');
        Route::post('tenants/{tenant}/transfer-ownership', [TenantsAdminController::class, 'transferOwnership'])
            ->whereNumber('tenant')
            ->name('tenants.transfer-ownership');

        // Retry a failed outbound webhook delivery from the tenant detail
        // activity panel. The delivery must belong to one of this tenant's
        // configured endpoints (enforced server-side).
        Route::post('tenants/{tenant}/webhook-deliveries/{delivery}/retry', [TenantsAdminController::class, 'retryWebhookDelivery'])
            ->whereNumber('tenant')->whereNumber('delivery')
            ->name('tenants.webhook-deliveries.retry');

        // Users admin.
        Route::get('users', [UsersAdminController::class, 'index'])
            ->name('users.index');
        Route::get('users/{user}', [UsersAdminController::class, 'show'])
            ->whereNumber('user')
            ->name('users.show');
        Route::post('users/{user}/suspend', [UsersAdminController::class, 'suspend'])
            ->whereNumber('user')
            ->name('users.suspend');
        Route::post('users/{user}/restore', [UsersAdminController::class, 'restore'])
            ->whereNumber('user')
            ->name('users.restore');
        Route::post('users/{user}/resend-verification', [UsersAdminController::class, 'resendVerification'])
            ->whereNumber('user')
            ->name('users.resend-verification');
        Route::post('users/{user}/force-password-reset', [UsersAdminController::class, 'forcePasswordReset'])
            ->whereNumber('user')
            ->name('users.force-password-reset');
        Route::post('users/{user}/disable-two-factor', [UsersAdminController::class, 'disableTwoFactor'])
            ->whereNumber('user')
            ->name('users.disable-two-factor');
        Route::post('users/{user}/revoke-sessions', [UsersAdminController::class, 'revokeSessions'])
            ->whereNumber('user')
            ->name('users.revoke-sessions');
        Route::post('users/{user}/revoke-tokens', [UsersAdminController::class, 'revokeTokens'])
            ->whereNumber('user')
            ->name('users.revoke-tokens');
        Route::post('users/{user}/grant-super-admin', [UsersAdminController::class, 'grantSuperAdmin'])
            ->whereNumber('user')
            ->name('users.grant-super-admin');
        Route::post('users/{user}/revoke-super-admin', [UsersAdminController::class, 'revokeSuperAdmin'])
            ->whereNumber('user')
            ->name('users.revoke-super-admin');
        Route::post('users/{user}/impersonate', [UsersAdminController::class, 'impersonate'])
            ->whereNumber('user')
            ->name('users.impersonate');
        Route::get('users/{user}/gdpr-export', [UsersAdminController::class, 'gdprExport'])
            ->whereNumber('user')
            ->name('users.gdpr-export');

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

        // Themes — DB-owned theme catalog (color tokens, fonts, custom CSS).
        // Activating swaps the live look with no front-end rebuild.
        Route::resource('themes', ThemesController::class)
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
            ->whereNumber('theme');
        Route::post('themes/{theme}/activate', [ThemesController::class, 'activate'])
            ->whereNumber('theme')
            ->name('themes.activate');
        Route::post('themes/{theme}/clone', [ThemesController::class, 'duplicate'])
            ->whereNumber('theme')
            ->name('themes.clone');
        Route::put('themes/{theme}/custom-css', [ThemesController::class, 'updateCss'])
            ->whereNumber('theme')
            ->name('themes.custom-css');
        Route::post('themes/{theme}/fonts', [ThemeFontsController::class, 'store'])
            ->whereNumber('theme')
            ->name('themes.fonts.store');
        Route::delete('themes/{theme}/fonts/{font}', [ThemeFontsController::class, 'destroy'])
            ->whereNumber('theme')->whereNumber('font')
            ->name('themes.fonts.destroy');

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

        // Checkout sessions — view abandoned + stuck sessions, force-cancel.
        Route::get('checkout-sessions', [CheckoutSessionsController::class, 'index'])
            ->name('checkout-sessions.index');
        Route::get('checkout-sessions/{checkoutSession}', [CheckoutSessionsController::class, 'show'])
            ->name('checkout-sessions.show');
        Route::post('checkout-sessions/{checkoutSession}/force-cancel', [CheckoutSessionsController::class, 'forceCancel'])
            ->name('checkout-sessions.force-cancel');

        /*
        |---------------------------------------------------------------------
        | CMS scope — /admin/cms/...
        | Marketing/CMS management. All routes named `admin.cms.*`.
        |---------------------------------------------------------------------
        */
        Route::prefix('cms')->name('cms.')->group(function () {
            // Pages
            Route::get('pages', [PagesController::class, 'index'])
                ->name('pages.index');
            Route::get('pages/create', [PagesController::class, 'create'])
                ->name('pages.create');
            Route::post('pages', [PagesController::class, 'store'])
                ->name('pages.store');
            Route::get('pages/{cms_page}/edit', [PagesController::class, 'edit'])
                ->whereNumber('cms_page')
                ->name('pages.edit');
            Route::patch('pages/{cms_page}', [PagesController::class, 'update'])
                ->whereNumber('cms_page')
                ->name('pages.update');
            Route::delete('pages/{cms_page}', [PagesController::class, 'destroy'])
                ->whereNumber('cms_page')
                ->name('pages.destroy');
            Route::post('pages/{id}/restore', [PagesController::class, 'restore'])
                ->whereNumber('id')
                ->name('pages.restore');

            // Signed preview URL (JSON; React drops it into an iframe).
            Route::get('pages/{cms_page}/preview-link', [PreviewLinkController::class, 'show'])
                ->whereNumber('cms_page')
                ->name('pages.preview-link');

            // Page versions
            Route::get('pages/{cms_page}/versions', [PageVersionsController::class, 'index'])
                ->whereNumber('cms_page')
                ->name('pages.versions.index');
            Route::post('pages/{cms_page}/versions/{version}/restore', [PageVersionsController::class, 'restore'])
                ->whereNumber('cms_page')
                ->whereNumber('version')
                ->name('pages.versions.restore');

            // Globals — site-wide singletons
            Route::get('globals', [GlobalsController::class, 'index'])
                ->name('globals.index');
            Route::get('globals/{key}', [GlobalsController::class, 'edit'])
                ->where('key', '[a-z0-9_]+')
                ->name('globals.edit');
            Route::patch('globals/{key}', [GlobalsController::class, 'update'])
                ->where('key', '[a-z0-9_]+')
                ->name('globals.update');

            // Reusable collections (features, testimonials, faqs, logos)
            Route::get('collections/{type}', [CollectionsController::class, 'index'])
                ->where('type', 'features|testimonials|faqs|logos')
                ->name('collections.index');
            Route::post('collections/{type}', [CollectionsController::class, 'store'])
                ->where('type', 'features|testimonials|faqs|logos')
                ->name('collections.store');
            Route::patch('collections/{type}/{id}', [CollectionsController::class, 'update'])
                ->where('type', 'features|testimonials|faqs|logos')
                ->whereNumber('id')
                ->name('collections.update');
            Route::delete('collections/{type}/{id}', [CollectionsController::class, 'destroy'])
                ->where('type', 'features|testimonials|faqs|logos')
                ->whereNumber('id')
                ->name('collections.destroy');

            // Blog posts
            Route::prefix('blog')->name('blog.')->group(function () {
                Route::get('posts', [BlogPostsController::class, 'index'])
                    ->name('posts.index');
                Route::get('posts/create', [BlogPostsController::class, 'create'])
                    ->name('posts.create');
                Route::post('posts', [BlogPostsController::class, 'store'])
                    ->name('posts.store');
                Route::get('posts/{post}/edit', [BlogPostsController::class, 'edit'])
                    ->whereNumber('post')
                    ->name('posts.edit');
                Route::patch('posts/{post}', [BlogPostsController::class, 'update'])
                    ->whereNumber('post')
                    ->name('posts.update');
                Route::delete('posts/{post}', [BlogPostsController::class, 'destroy'])
                    ->whereNumber('post')
                    ->name('posts.destroy');
            });

            // Forms + submissions
            Route::prefix('forms')->name('forms.')->group(function () {
                Route::get('/', [FormsController::class, 'index'])
                    ->name('index');
                Route::get('create', [FormsController::class, 'create'])
                    ->name('create');
                Route::post('/', [FormsController::class, 'store'])
                    ->name('store');
                Route::get('{form}/edit', [FormsController::class, 'edit'])
                    ->whereNumber('form')
                    ->name('edit');
                Route::patch('{form}', [FormsController::class, 'update'])
                    ->whereNumber('form')
                    ->name('update');
                Route::delete('{form}', [FormsController::class, 'destroy'])
                    ->whereNumber('form')
                    ->name('destroy');
                Route::get('{form}/submissions', [FormsController::class, 'submissions'])
                    ->whereNumber('form')
                    ->name('submissions');
                Route::get('{form}/submissions.csv', [FormsController::class, 'submissionsCsv'])
                    ->whereNumber('form')
                    ->name('submissions-csv');
            });

            // Redirects + 404 log
            Route::get('redirects', [RedirectsController::class, 'index'])
                ->name('redirects.index');
            Route::post('redirects', [RedirectsController::class, 'store'])
                ->name('redirects.store');
            Route::patch('redirects/{redirect}', [RedirectsController::class, 'update'])
                ->whereNumber('redirect')
                ->name('redirects.update');
            Route::delete('redirects/{redirect}', [RedirectsController::class, 'destroy'])
                ->whereNumber('redirect')
                ->name('redirects.destroy');
            Route::post('redirects/from-404/{id}', [RedirectsController::class, 'convertNotFound'])
                ->whereNumber('id')
                ->name('redirects.from-404');

            // Media library
            Route::get('media', [MediaController::class, 'index'])
                ->name('media.index');
            Route::post('media', [MediaController::class, 'store'])
                ->name('media.store');
            Route::patch('media/{media_asset}', [MediaController::class, 'update'])
                ->whereNumber('media_asset')
                ->name('media.update');
            Route::delete('media/{media_asset}', [MediaController::class, 'destroy'])
                ->whereNumber('media_asset')
                ->name('media.destroy');
        });
    });

// Stop impersonation — gated only on `auth`. The current request's auth user
// is the *target*, not the Super Admin, so requiring `role:Super Admin`
// would lock the impersonator out of returning to their original account.
Route::middleware(['auth'])
    ->post('admin/stop-impersonating', [TenantsAdminController::class, 'stopImpersonation'])
    ->name('admin.stop-impersonating');
