<?php

use App\Http\Controllers\API\UserSearchController;
use App\Http\Controllers\Tenants\TenantInvitationsController;
use App\Http\Controllers\Tenants\TenantsController;
use App\Http\Controllers\Tenants\TenantSwitchController;
use App\Http\Controllers\Users\UsersController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// `tenantSlug` is a plain string param — middleware (`SetCurrentTenant`)
// resolves it to a Tenant model and binds it to the container so controllers
// can read it without an extra DB hit. The `{tenant}` param in the
// /tenants/{tenant}/switch route stays a string (slug); the switch
// controller does the lookup itself so Wayfinder generates `string | number`
// typed helpers instead of model-by-id helpers. `{invitation}` is resolved
// by Laravel's standard implicit binding (lookup by primary key on the
// typehinted TenantInvitation controller param).

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

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
