<?php

use App\Http\Controllers\API\V1\MeController;
use App\Http\Controllers\API\V1\TenantsController;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| API routes
|---------------------------------------------------------------------------
|
| All requests are stateless and authenticated with Sanctum personal access
| tokens. Rate limited per-token by the `api` limiter registered in
| AppServiceProvider::boot(). Documented automatically by Scribe at /docs.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::get('me', MeController::class)->name('me');
        Route::get('tenants', TenantsController::class)->name('tenants.index');
    });
