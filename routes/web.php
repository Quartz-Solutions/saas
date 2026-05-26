<?php

use App\Http\Controllers\API\UserSearchController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Tenants\TenantInvitationsController;
use App\Http\Controllers\Tenants\TenantsController;
use App\Http\Controllers\Tenants\TenantSwitchController;
use App\Http\Controllers\Users\UsersController;
use App\Http\Controllers\Webhooks\WebhooksController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/marketing.php';

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

        Route::get('invitations/{token}', [TenantInvitationsController::class, 'accept'])
            ->name('invitations.accept');
    });

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
