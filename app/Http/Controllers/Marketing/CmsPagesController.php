<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Support\Cms\CmsRefsService;
use Inertia\Inertia;
use Inertia\Response;

class CmsPagesController extends Controller
{
    public function __construct(private readonly CmsRefsService $refs) {}

    /**
     * Render a published CMS page by slug.
     *
     * Payload includes both `body_html` (legacy) and `body_blocks`
     * (block-based). The React renderer prefers blocks when present and
     * falls back to body_html. After M2, pages saved through the block
     * editor will have body_blocks populated automatically.
     */
    public function show(string $slug): Response
    {
        $locale = app()->getLocale();
        $fallback = (string) config('cms.default_locale', 'en');
        $cacheKey = "cms.page:{$slug}:{$locale}";
        $ttl = (int) config('cms.cache.page_ttl', 3600);

        // Stale-while-revalidate: fresh for 60s, then serve stale (up to
        // TTL) while a background refresh recomputes. Laravel 11.31+.
        $page = cache()->flexible($cacheKey, [60, $ttl], function () use ($slug, $locale, $fallback): ?array {
            // Prefer the visitor's locale, fall back to the canonical one.
            $page = CmsPage::query()
                ->where('slug', $slug)
                ->where('status', CmsPage::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where('locale', $locale)
                ->first();

            if ($page === null && $locale !== $fallback) {
                $page = CmsPage::query()
                    ->where('slug', $slug)
                    ->where('status', CmsPage::STATUS_PUBLISHED)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->where('locale', $fallback)
                    ->first();
            }

            if ($page === null) {
                return null;
            }

            return [
                'slug' => $page->slug,
                'title' => $page->title,
                'body_html' => $page->body_html ?? '',
                'body_blocks' => $page->body_blocks ?? null,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'template' => $page->template,
                'no_index' => $page->no_index,
                'published_at' => optional($page->published_at)->toIso8601String(),
            ];
        });

        if ($page === null) {
            cache()->forget($cacheKey);
            abort(404);
        }

        return Inertia::render('marketing/docs/show', [
            'page' => $page,
            'cmsRefs' => is_array($page['body_blocks'] ?? null)
                ? $this->refs->forBlocks($page['body_blocks'])
                : null,
            // Sibling docs powering the sidebar's auto-fallback when
            // cmsGlobals.docs_sidebar is empty.
            'docs' => $this->docsList($locale),
        ]);
    }

    /**
     * Cached list of published docs for the sidebar fallback.
     *
     * @return array<int, array{slug: string, title: string, meta_description: ?string}>
     */
    protected function docsList(string $locale): array
    {
        $ttl = (int) config('cms.cache.docs_index_ttl', 3600);

        return cache()->flexible("cms.docs.list:{$locale}", [60, $ttl], function () use ($locale): array {
            $fallback = (string) config('cms.default_locale', 'en');

            return CmsPage::query()
                ->where('template', CmsPage::TEMPLATE_DOCS)
                ->where('status', CmsPage::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereIn('locale', array_unique([$locale, $fallback]))
                ->orderBy('title')
                ->get(['slug', 'title', 'meta_description'])
                ->unique('slug')
                ->values()
                ->map(fn (CmsPage $p) => [
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'meta_description' => $p->meta_description,
                ])
                ->all();
        });
    }
}
