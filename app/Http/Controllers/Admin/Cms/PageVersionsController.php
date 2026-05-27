<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use App\Support\Cms\PageService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageVersionsController extends Controller
{
    public function __construct(private readonly PageService $pages) {}

    public function index(CmsPage $cmsPage): Response
    {
        $versions = CmsPageVersion::query()
            ->where('cms_page_id', $cmsPage->id)
            ->with('author:id,name,email')
            ->orderByDesc('version_no')
            ->get()
            ->map(fn (CmsPageVersion $v) => [
                'id' => $v->id,
                'version_no' => $v->version_no,
                'note' => $v->note,
                'author' => $v->author ? ['name' => $v->author->name, 'email' => $v->author->email] : null,
                'created_at' => optional($v->created_at)->toIso8601String(),
                'snapshot' => $v->snapshot,
            ])
            ->all();

        return Inertia::render('admin/cms/pages/versions', [
            'page' => ['id' => $cmsPage->id, 'title' => $cmsPage->title, 'slug' => $cmsPage->slug],
            'versions' => $versions,
        ]);
    }

    public function restore(CmsPage $cmsPage, int $version): RedirectResponse
    {
        $row = CmsPageVersion::query()
            ->where('cms_page_id', $cmsPage->id)
            ->where('id', $version)
            ->firstOrFail();

        $this->pages->restoreVersion($cmsPage, $row, optional(request()->user())->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page restored.')]);

        return to_route('admin.cms.pages.edit', ['cms_page' => $cmsPage->id]);
    }
}
