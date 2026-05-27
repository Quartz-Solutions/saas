<?php

use App\Http\Controllers\Marketing\CmsPagesController;
use App\Http\Controllers\Marketing\CookieConsentController;
use App\Http\Controllers\Marketing\DocsController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\LegalController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\Marketing\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing routes (Phase 7)
|--------------------------------------------------------------------------
|
| All public — no auth middleware. Routes are loaded from routes/web.php.
| Page names use the `marketing/*` prefix so app.tsx layout dispatch
| renders them inside PublicLayout.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/pricing', PricingController::class)->name('marketing.pricing');

Route::get('/docs', [DocsController::class, 'index'])->name('marketing.docs.index');
Route::get('/docs/{slug}', [CmsPagesController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]+')
    ->name('marketing.docs.show');

Route::get('/legal/{type}', [LegalController::class, 'show'])
    ->where('type', 'privacy|terms|cookies')
    ->name('marketing.legal.show');

Route::post('/cookie-consent', [CookieConsentController::class, 'store'])
    ->name('marketing.cookie-consent.store');

Route::get('/sitemap.xml', SitemapController::class)->name('marketing.sitemap');
