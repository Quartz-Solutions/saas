<?php

use App\Http\Controllers\API\V1\ApiTokensController;
use App\Http\Controllers\API\V1\AuditLogController;
use App\Http\Controllers\API\V1\InvitationsController;
use App\Http\Controllers\API\V1\InvoicesController;
use App\Http\Controllers\API\V1\MeController;
use App\Http\Controllers\API\V1\MembersController;
use App\Http\Controllers\API\V1\NotificationsPreferencesController;
use App\Http\Controllers\API\V1\OwnershipTransferController;
use App\Http\Controllers\API\V1\PaymentsController;
use App\Http\Controllers\API\V1\PlansController;
use App\Http\Controllers\API\V1\SessionsController;
use App\Http\Controllers\API\V1\SubscriptionController;
use App\Http\Controllers\API\V1\TenantsController;
use App\Http\Controllers\API\V1\WebhookDeliveriesController;
use App\Http\Controllers\API\V1\WebhooksController;
use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| Public REST API (/api/v1/*) — see agent-os/product/api.md for the spec.
|
| All endpoints authenticate with Sanctum personal access tokens and are
| gated by per-token abilities (config/api-abilities.php). Per-category
| rate limiters live in AppServiceProvider::configureRateLimiters().
|
| Tenant-scoped resources mount under `/v1/tenants/{slug}/...` and use the
| `api.tenant` middleware to resolve + enforce membership.
|---------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {

        // -------------------------------------------------------------
        // Read bucket — GETs
        // -------------------------------------------------------------
        Route::middleware(['throttle:api.read', 'api.rate:api.read'])->group(function () {
            // Me
            Route::get('me', [MeController::class, 'show'])->name('me.show');
            Route::get('me/api-tokens', [ApiTokensController::class, 'index'])->name('me.tokens.index');

            // Tenants
            Route::get('tenants', [TenantsController::class, 'index'])->name('tenants.index');

            // Plans (token-required; public pricing is at /pricing in the SPA)
            Route::get('plans', [PlansController::class, 'index'])->name('plans.index');

            // Notification preferences (read)
            Route::get('notification-preferences', [NotificationsPreferencesController::class, 'show'])
                ->name('notification-preferences.show');

            // Tenant-scoped reads
            Route::middleware('api.tenant')->prefix('tenants/{slug}')->group(function () {
                Route::get('/', [TenantsController::class, 'show'])->name('tenants.show');

                Route::get('members', [MembersController::class, 'index'])->name('members.index');
                Route::get('invitations', [InvitationsController::class, 'index'])->name('invitations.index');

                Route::get('subscription', [SubscriptionController::class, 'current'])->name('subscription.current');
                Route::get('subscriptions', [SubscriptionController::class, 'history'])->name('subscriptions.index');

                Route::get('invoices', [InvoicesController::class, 'index'])->name('invoices.index');
                Route::get('invoices/{id}', [InvoicesController::class, 'show'])->whereNumber('id')->name('invoices.show');
                Route::get('invoices/{id}/pdf', [InvoicesController::class, 'pdf'])->whereNumber('id')->name('invoices.pdf');

                Route::get('payments', [PaymentsController::class, 'index'])->name('payments.index');
                Route::get('payments/{id}', [PaymentsController::class, 'show'])->whereNumber('id')->name('payments.show');

                Route::get('webhooks', [WebhooksController::class, 'index'])->name('webhooks.index');
                Route::get('webhooks/{id}', [WebhooksController::class, 'show'])->whereNumber('id')->name('webhooks.show');
                Route::get('webhooks/{id}/deliveries', [WebhookDeliveriesController::class, 'index'])
                    ->whereNumber('id')->name('webhooks.deliveries.index');

                Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
                Route::get('audit-log/{id}', [AuditLogController::class, 'show'])->whereNumber('id')->name('audit.show');
            });
        });

        // -------------------------------------------------------------
        // Write bucket — POST/PATCH/DELETE
        // -------------------------------------------------------------
        Route::middleware(['throttle:api.write', 'api.rate:api.write'])->group(function () {
            Route::patch('me', [MeController::class, 'update'])->name('me.update');

            Route::patch('notification-preferences', [NotificationsPreferencesController::class, 'update'])
                ->name('notification-preferences.update');

            Route::post('tenants', [TenantsController::class, 'store'])->name('tenants.store');

            Route::middleware('api.tenant')->prefix('tenants/{slug}')->group(function () {
                Route::patch('/', [TenantsController::class, 'update'])->name('tenants.update');
                Route::delete('/', [TenantsController::class, 'destroy'])->name('tenants.destroy');

                Route::post('transfer-ownership', [OwnershipTransferController::class, 'store'])
                    ->name('tenants.transfer');

                Route::post('invitations', [InvitationsController::class, 'store'])->name('invitations.store');
                Route::delete('invitations/{id}', [InvitationsController::class, 'destroy'])
                    ->whereNumber('id')->name('invitations.destroy');

                Route::patch('members/{userId}/role', [MembersController::class, 'updateRole'])
                    ->whereNumber('userId')->name('members.updateRole');
                Route::delete('members/{userId}', [MembersController::class, 'destroy'])
                    ->whereNumber('userId')->name('members.destroy');

                Route::post('subscription/change-plan', [SubscriptionController::class, 'changePlan'])
                    ->name('subscription.changePlan');
                Route::post('subscription/cancel', [SubscriptionController::class, 'cancel'])
                    ->name('subscription.cancel');
                Route::post('subscription/reactivate', [SubscriptionController::class, 'reactivate'])
                    ->name('subscription.reactivate');

                Route::post('webhooks', [WebhooksController::class, 'store'])->name('webhooks.store');
                Route::patch('webhooks/{id}', [WebhooksController::class, 'update'])
                    ->whereNumber('id')->name('webhooks.update');
                Route::delete('webhooks/{id}', [WebhooksController::class, 'destroy'])
                    ->whereNumber('id')->name('webhooks.destroy');
                Route::post('webhooks/{id}/rotate-secret', [WebhooksController::class, 'rotateSecret'])
                    ->whereNumber('id')->name('webhooks.rotateSecret');
                Route::post('webhooks/{id}/test', [WebhooksController::class, 'testFire'])
                    ->whereNumber('id')->name('webhooks.test');
                Route::post('webhooks/{id}/deliveries/{deliveryId}/retry', [WebhookDeliveriesController::class, 'retry'])
                    ->whereNumber('id')->whereNumber('deliveryId')->name('webhooks.deliveries.retry');
            });
        });

        // -------------------------------------------------------------
        // Auth-sensitive bucket — small per-token quota
        // -------------------------------------------------------------
        Route::middleware(['throttle:api.auth', 'api.rate:api.auth'])->group(function () {
            Route::post('me/email-change', [MeController::class, 'requestEmailChange'])
                ->name('me.emailChange');
            Route::post('me/sessions/revoke-all', [SessionsController::class, 'revokeAll'])
                ->name('me.sessions.revokeAll');
            Route::delete('me/api-tokens/{id}', [ApiTokensController::class, 'destroy'])
                ->whereNumber('id')->name('me.tokens.destroy');
        });
    });
