<?php

use App\Http\Controllers\API\UserSearchController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\Users\UsersController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

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

    // Socialite OAuth — Google / GitHub (and any future providers registered in
    // App\Support\Auth\SocialProviderRegistry).
    Route::get('auth/{provider}/redirect', [SocialController::class, 'redirect'])
        ->whereIn('provider', ['google', 'github'])
        ->name('auth.social.redirect');

    Route::get('auth/{provider}/callback', [SocialController::class, 'callback'])
        ->whereIn('provider', ['google', 'github'])
        ->name('auth.social.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('shared-components', 'shared-components')->name('shared-components');

    Route::get('app/users/search', [UserSearchController::class, 'search'])
        ->name('app.users.search');

    Route::resource('users', UsersController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
