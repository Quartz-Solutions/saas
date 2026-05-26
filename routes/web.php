<?php

use App\Http\Controllers\API\UserSearchController;
use App\Http\Controllers\Users\UsersController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/marketing.php';

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('shared-components', 'shared-components')->name('shared-components');

    Route::get('app/users/search', [UserSearchController::class, 'search'])
        ->name('app.users.search');

    Route::resource('users', UsersController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
