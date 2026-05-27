<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Support\Cms\CmsRefsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Signed-URL preview for unpublished pages.
 *
 * Admins generate a signed URL of the form
 *   /preview/page/{id}?signature=... &expires=...
 * via PreviewService::urlFor() (M12 will surface this in the editor).
 *
 * The signed-URL middleware enforces signature integrity; this
 * controller bypasses the published-status filter.
 */
class PreviewController extends Controller
{
    public function __construct(private readonly CmsRefsService $refs) {}

    public function page(Request $request, int $id): Response
    {
        // The `signed` middleware on the route enforces signature integrity.
        abort_unless($request->hasValidSignature(), 403);

        $page = CmsPage::query()->findOrFail($id);

        return Inertia::render('marketing/docs/show', [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'body_html' => $page->body_html ?? '',
                'body_blocks' => $page->body_blocks ?? null,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'template' => $page->template,
                'no_index' => true, // never index a preview
                'published_at' => optional($page->published_at)->toIso8601String(),
            ],
            'cmsRefs' => is_array($page->body_blocks)
                ? $this->refs->forBlocks($page->body_blocks)
                : null,
            'preview' => true,
        ]);
    }

    /**
     * Build a signed preview URL for a page, valid for the given TTL.
     */
    public static function signedUrlFor(int $pageId, int $ttlMinutes = 30): string
    {
        return URL::temporarySignedRoute(
            'marketing.preview.page',
            now()->addMinutes($ttlMinutes),
            ['id' => $pageId],
        );
    }
}
