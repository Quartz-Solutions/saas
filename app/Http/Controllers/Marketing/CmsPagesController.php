<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Inertia\Inertia;
use Inertia\Response;

class CmsPagesController extends Controller
{
    /**
     * Render a published CMS page by slug. Body HTML is cached for 1h.
     */
    public function show(string $slug): Response
    {
        $cacheKey = 'cms.page:'.$slug;

        $page = cache()->remember($cacheKey, 3600, function () use ($slug): ?array {
            $page = CmsPage::query()
                ->where('slug', $slug)
                ->where('status', CmsPage::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->first();

            if ($page === null) {
                return null;
            }

            return [
                'slug' => $page->slug,
                'title' => $page->title,
                'body_html' => $page->body_html ?? '',
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
        ]);
    }
}
