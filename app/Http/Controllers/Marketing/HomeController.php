<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsFeature;
use App\Models\CmsPage;
use App\Support\Cms\CmsRefsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the public landing page.
 *
 * Two paths:
 *   1. If a published CmsPage with `route_name=home` exists, render its
 *      block tree. The admin can replace the entire homepage from
 *      /admin/cms/pages without touching code.
 *   2. Otherwise fall back to the seeded feature list in `cms_features`
 *      so a fresh install still has a usable home page.
 */
class HomeController extends Controller
{
    public function __construct(private readonly CmsRefsService $refs) {}

    public function __invoke(): Response
    {
        $page = CmsPage::query()
            ->where('route_name', 'home')
            ->where('status', CmsPage::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if ($page !== null && $page->hasBlocks()) {
            return Inertia::render('marketing/home', [
                'page' => [
                    'title' => $page->title,
                    'meta_title' => $page->meta_title,
                    'meta_description' => $page->meta_description,
                    'no_index' => (bool) $page->no_index,
                    'body_blocks' => $page->body_blocks,
                ],
                'cmsRefs' => $this->refs->forBlocks((array) $page->body_blocks),
                // Legacy prop, unused when blocks are present, but kept so
                // <marketing/home> always receives a `features` array.
                'features' => [],
            ]);
        }

        $features = CmsFeature::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['title', 'description', 'icon'])
            ->map(fn (CmsFeature $f) => [
                'title' => $f->title,
                'description' => $f->description,
                'icon' => $f->icon ?? 'shield',
            ])
            ->all();

        return Inertia::render('marketing/home', [
            'page' => null,
            'cmsRefs' => null,
            'features' => $features,
        ]);
    }
}
