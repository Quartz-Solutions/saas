<?php

use App\Http\Controllers\Marketing\BlogController;
use App\Http\Controllers\Marketing\CmsPagesController;
use App\Http\Controllers\Marketing\CookieConsentController;
use App\Http\Controllers\Marketing\DocsController;
use App\Http\Controllers\Marketing\FormSubmissionsController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\LegalController;
use App\Http\Controllers\Marketing\NewsletterController;
use App\Http\Controllers\Marketing\PreviewController;
use App\Http\Controllers\Marketing\PricingController;
use App\Http\Controllers\Marketing\RobotsController;
use App\Http\Controllers\Marketing\SitemapController;
use App\Models\NotFoundLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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

Route::middleware('cms.locale')->group(function () {

    Route::get('/', HomeController::class)->name('home');
    Route::get('/pricing', PricingController::class)->name('marketing.pricing');

    Route::get('/docs', [DocsController::class, 'index'])->name('marketing.docs.index');
    Route::get('/docs/{slug}', [CmsPagesController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name('marketing.docs.show');

    Route::get('/legal/{type}', [LegalController::class, 'show'])
        ->where('type', 'privacy|terms|cookies')
        ->name('marketing.legal.show');

    // Blog
    Route::get('/blog', [BlogController::class, 'index'])->name('marketing.blog.index');
    Route::get('/blog/feed.xml', [BlogController::class, 'feed'])->name('marketing.blog.feed');
    Route::get('/blog/category/{slug}', [BlogController::class, 'byCategory'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name('marketing.blog.category');
    Route::get('/blog/tag/{slug}', [BlogController::class, 'byTag'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name('marketing.blog.tag');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->name('marketing.blog.show');

    Route::post('/cookie-consent', [CookieConsentController::class, 'store'])
        ->name('marketing.cookie-consent.store');

    // Public form submissions — rate-limited.
    Route::post('/marketing/forms/{slug}', [FormSubmissionsController::class, 'store'])
        ->where('slug', '[A-Za-z0-9\-_]+')
        ->middleware('throttle:20,1')
        ->name('marketing.forms.submit');

    // Newsletter subscribe — used by the newsletter block and the contact form footer.
    Route::post('/marketing/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
        ->middleware('throttle:20,1')
        ->name('marketing.newsletter.subscribe');

    Route::get('/sitemap.xml', SitemapController::class)->name('marketing.sitemap');
    Route::get('/robots.txt', RobotsController::class)->name('marketing.robots');

    // Signed preview URL for unpublished CMS pages.
    Route::get('/preview/page/{id}', [PreviewController::class, 'page'])
        ->where('id', '[0-9]+')
        ->middleware('signed')
        ->name('marketing.preview.page');

}); // end cms.locale group

/*
|--------------------------------------------------------------------------
| Fallback — 404 with logging
|--------------------------------------------------------------------------
| Catches GET requests that didn't match any other route. Logs the path
| to cms_not_found_log so admins can convert popular 404s into redirects,
| then returns a standard 404 response. Loaded LAST in the web routes.
*/
Route::fallback(function (Request $request) {
    if ($request->isMethod('GET') && Schema::hasTable('cms_not_found_log')) {
        try {
            $path = '/'.ltrim($request->path(), '/');
            $row = NotFoundLog::query()->firstOrNew(['path' => $path]);
            $row->hits = (int) ($row->hits ?? 0) + 1;
            $row->last_hit_at = now();
            $row->referer = $request->headers->get('referer');
            $row->saveQuietly();
        } catch (Throwable) {
            // Never break the 404 page on logging failure.
        }
    }
    abort(404);
});
