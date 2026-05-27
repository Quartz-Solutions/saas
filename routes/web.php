<?php

use App\Http\Controllers\API\UserSearchController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\CheckoutHistoryController;
use App\Http\Controllers\Billing\InvoicesController;
use App\Http\Controllers\Billing\WebhookController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Onboarding\GetStartedController;
use App\Http\Controllers\Tenants\TenantInvitationsController;
use App\Http\Controllers\Tenants\TenantOnboardingController;
use App\Http\Controllers\Tenants\TenantsController;
use App\Http\Controllers\Tenants\TenantSwitchController;
use App\Http\Controllers\Users\UsersController;
use App\Http\Controllers\Webhooks\WebhooksController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/marketing.php';

/*
|---------------------------------------------------------------------------
| Webhooks — public, CSRF-exempt (see bootstrap/app.php).
|---------------------------------------------------------------------------
*/
Route::post('webhooks/{gateway}', WebhookController::class)
    ->where('gateway', '[a-z0-9_-]+')
    ->name('webhooks.gateway');

// Magic-link login (passwordless).
Route::middleware('guest')->group(function () {
    Route::get('auth/magic-link', [MagicLinkController::class, 'create'])
        ->name('auth.magic-link.create');

    Route::post('auth/magic-link', [MagicLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('auth.magic-link.store');

    Route::get('auth/magic-link/{token}', [MagicLinkController::class, 'consume'])
        ->middleware('signed')
        ->name('auth.magic-link.consume');

    Route::get('auth/{provider}/redirect', [SocialController::class, 'redirect'])
        ->whereIn('provider', ['google', 'github'])
        ->name('auth.social.redirect');

    Route::get('auth/{provider}/callback', [SocialController::class, 'callback'])
        ->whereIn('provider', ['google', 'github'])
        ->name('auth.social.callback');

    // Combined sign-up + tenant creation + (free or paid) plan checkout.
    Route::get('get-started', [GetStartedController::class, 'show'])
        ->name('onboarding.get-started');
    Route::post('get-started', [GetStartedController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('onboarding.get-started.store');
});

// Stripe Checkout return URL — gateway redirects here after the user
// completes (or skips) payment. Lands at /t/{slug}/dashboard.
Route::middleware(['auth'])
    ->get('onboarding/{tenantSlug}/return', [GetStartedController::class, 'return'])
    ->name('onboarding.return');

/*
|---------------------------------------------------------------------------
| Polymorphic checkout
|---------------------------------------------------------------------------
| Single funnel for all plan-buy actions. See agent-os/product/checkout.md.
*/
Route::middleware(['auth'])->group(function () {
    Route::post('checkout/start', [CheckoutController::class, 'start'])
        ->name('checkout.start');

    Route::get('checkout/{session}', [CheckoutController::class, 'show'])
        ->where('session', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('checkout.show');

    Route::post('checkout/{session}/pay', [CheckoutController::class, 'pay'])
        ->where('session', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('checkout.pay');

    Route::get('checkout/{session}/return', [CheckoutController::class, 'return'])
        ->where('session', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('checkout.return');

    Route::get('checkout/{session}/status', [CheckoutController::class, 'status'])
        ->where('session', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('checkout.status');

    Route::post('checkout/{session}/cancel', [CheckoutController::class, 'cancel'])
        ->where('session', '[0-9A-HJKMNP-TV-Z]{26}')
        ->name('checkout.cancel');
});

/*
|---------------------------------------------------------------------------
| Personal scope — /account/...
| Routes that don't belong to a tenant: tenant chooser, accept invitation.
|---------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::get('tenants', [TenantsController::class, 'index'])
            ->name('tenants.index');

        Route::post('tenants', [TenantsController::class, 'store'])
            ->name('tenants.store');

        Route::delete('tenants/{tenant}', [TenantsController::class, 'destroyFromAccount'])
            ->whereNumber('tenant')
            ->name('tenants.destroy');
    });

// Invitation acceptance — public. Unauthenticated visitors get a landing
// page with sign-in/sign-up CTAs prefilled with the invitee email; the
// intended URL is stashed in the session so post-login they land back here.
Route::get('account/invitations/{token}', [TenantInvitationsController::class, 'accept'])
    ->name('account.invitations.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('tenants/{tenant}/switch', TenantSwitchController::class)
        ->name('tenants.switch');
});

/*
|---------------------------------------------------------------------------
| Tenant scope — /t/{tenantSlug}/...
| Per-tenant URLs. `tenant` middleware resolves and shares the model, then
| `tenant.member` enforces membership.
|---------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'tenant', 'tenant.member'])
    ->prefix('t/{tenantSlug}')
    ->name('tenants.')
    ->group(function () {
        Route::inertia('dashboard', 'dashboard')->name('dashboard');
        Route::inertia('shared-components', 'shared-components')->name('shared-components');

        Route::get('app/users/search', [UserSearchController::class, 'search'])
            ->name('app.users.search');

        Route::resource('users', UsersController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::get('settings', [TenantsController::class, 'edit'])
            ->name('settings');

        Route::patch('settings', [TenantsController::class, 'update'])
            ->name('settings.update');

        Route::delete('settings', [TenantsController::class, 'destroy'])
            ->name('settings.destroy');

        Route::get('invitations', [TenantInvitationsController::class, 'index'])
            ->name('invitations.index');

        Route::post('invitations', [TenantInvitationsController::class, 'store'])
            ->name('invitations.store');

        Route::patch('invitations/{invitation}', [TenantInvitationsController::class, 'update'])
            ->name('invitations.update');

        Route::delete('invitations/{invitation}', [TenantInvitationsController::class, 'destroy'])
            ->name('invitations.destroy');

        Route::post('onboarding/complete', [TenantOnboardingController::class, 'complete'])
            ->name('onboarding.complete');

        Route::get('settings/webhooks', [WebhooksController::class, 'index'])
            ->name('webhooks.index');
        Route::post('settings/webhooks', [WebhooksController::class, 'store'])
            ->name('webhooks.store');
        Route::patch('settings/webhooks/{webhook}', [WebhooksController::class, 'update'])
            ->whereNumber('webhook')
            ->name('webhooks.update');
        Route::delete('settings/webhooks/{webhook}', [WebhooksController::class, 'destroy'])
            ->whereNumber('webhook')
            ->name('webhooks.destroy');
        Route::post('settings/webhooks/{webhook}/rotate-secret', [WebhooksController::class, 'rotateSecret'])
            ->whereNumber('webhook')
            ->name('webhooks.rotate-secret');
        Route::post('settings/webhooks/{webhook}/test-fire', [WebhooksController::class, 'testFire'])
            ->whereNumber('webhook')
            ->name('webhooks.test-fire');

        Route::get('billing/plans', [BillingController::class, 'plans'])
            ->name('billing.plans');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])
            ->name('billing.cancel');
        Route::post('billing/resume', [BillingController::class, 'resume'])
            ->name('billing.resume');
        Route::get('billing/invoices', [InvoicesController::class, 'index'])
            ->name('billing.invoices.index');
        Route::get('billing/checkout-history', [CheckoutHistoryController::class, 'index'])
            ->name('billing.checkout-history');
        Route::get('billing/invoices/{invoice}/pdf', [InvoicesController::class, 'pdf'])
            ->name('billing.invoices.pdf');
    });

// Convenience: /dashboard redirects to the user's current tenant dashboard
// (or to /account/tenants if they have none).
Route::middleware(['auth', 'verified'])->get('dashboard', function () {
    $user = request()->user();
    $tenant = $user?->currentTenant ?? $user?->tenants()->first();

    if ($tenant) {
        return redirect()->route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
    }

    return redirect()->route('account.tenants.index');
})->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
