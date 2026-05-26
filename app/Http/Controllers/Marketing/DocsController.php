<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Inertia\Inertia;
use Inertia\Response;

class DocsController extends Controller
{
    public function index(): Response
    {
        $pages = cache()->remember('marketing.docs.index', 3600, function () {
            return CmsPage::query()
                ->where('template', CmsPage::TEMPLATE_DOCS)
                ->where('status', CmsPage::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderBy('title')
                ->get(['slug', 'title', 'meta_description'])
                ->toArray();
        });

        return Inertia::render('marketing/docs/index', [
            'pages' => $pages,
        ]);
    }
}
