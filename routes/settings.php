<?php

use App\Http\Controllers\ApiTokens\ApiTokensController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SessionsController;
use App\Http\Controllers\Settings\UserPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/preferences/{page}', [UserPreferenceController::class, 'show'])
        ->name('preferences.show');
    Route::put('settings/preferences/{page}', [UserPreferenceController::class, 'update'])
        ->name('preferences.update');

    Route::get('settings/sessions', [SessionsController::class, 'index'])
        ->name('sessions.index');
    Route::delete('settings/sessions', [SessionsController::class, 'destroyAll'])
        ->name('sessions.destroyAll');
    Route::delete('settings/sessions/{session}', [SessionsController::class, 'destroy'])
        ->name('sessions.destroy');

    Route::get('settings/api-tokens', [ApiTokensController::class, 'index'])
        ->name('api-tokens.index');
    Route::post('settings/api-tokens', [ApiTokensController::class, 'store'])
        ->name('api-tokens.store');
    Route::delete('settings/api-tokens/{token}', [ApiTokensController::class, 'destroy'])
        ->whereNumber('token')
        ->name('api-tokens.destroy');
});
