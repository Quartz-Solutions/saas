<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\CmsPageDestroyRequest;
use App\Http\Requests\Admin\Cms\CmsPageStoreRequest;
use App\Http\Requests\Admin\Cms\CmsPageUpdateRequest;
use App\Models\CmsPage;
use App\Support\Cms\BlockTypeRegistry;
use App\Support\Cms\PageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PagesController extends Controller
{
    private const ALLOWED_SORT = ['id', 'title', 'slug', 'status', 'template', 'updated_at'];

    private const PER_PAGE = 25;

    public function __construct(
        private readonly PageService $pages,
        private readonly BlockTypeRegistry $blocks,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'updated_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $query = CmsPage::query()->withTrashed();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['template']) && $filters['template'] !== '') {
            $query->where('template', $filters['template']);
        }
        if (isset($filters['locale']) && $filters['locale'] !== '') {
            $query->where('locale', $filters['locale']);
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('admin/cms/pages/index', [
            'pages' => [
                'data' => $paginator->getCollection()->map(fn (CmsPage $p) => $this->serialize($p))->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ],
            'tableState' => [
                'search' => $search,
                'filters' => (object) $filters,
                'sort' => ['column' => $sort, 'direction' => $direction],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cms/pages/edit', [
            'page' => null,
            'blockCatalog' => $this->blockCatalog(),
            'reservedRoutes' => (array) config('cms.reserved_routes', []),
            'parentOptions' => $this->parentOptions(null),
            'locales' => $this->locales(),
        ]);
    }

    public function store(CmsPageStoreRequest $request): RedirectResponse
    {
        $page = $this->pages->save(null, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page created.')]);

        return to_route('admin.cms.pages.edit', ['cms_page' => $page->id]);
    }

    public function edit(CmsPage $cmsPage): Response
    {
        return Inertia::render('admin/cms/pages/edit', [
            'page' => $this->serialize($cmsPage, withBlocks: true),
            'blockCatalog' => $this->blockCatalog(),
            'reservedRoutes' => (array) config('cms.reserved_routes', []),
            'parentOptions' => $this->parentOptions($cmsPage->id),
            'locales' => $this->locales(),
        ]);
    }

    public function update(CmsPageUpdateRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $this->pages->save($cmsPage, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page saved.')]);

        return to_route('admin.cms.pages.edit', ['cms_page' => $cmsPage->id]);
    }

    public function destroy(CmsPageDestroyRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $cmsPage->delete();
        $this->pages->bustCache($cmsPage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page archived.')]);

        return to_route('admin.cms.pages.index');
    }

    public function restore(int $id): RedirectResponse
    {
        $page = CmsPage::withTrashed()->findOrFail($id);
        $page->restore();
        $this->pages->bustCache($page);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page restored.')]);

        return to_route('admin.cms.pages.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CmsPage $page, bool $withBlocks = false): array
    {
        $payload = [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'locale' => $page->locale,
            'parent_id' => $page->parent_id,
            'path' => $page->path,
            'route_name' => $page->route_name,
            'template' => $page->template,
            'status' => $page->status,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'no_index' => (bool) $page->no_index,
            'published_at' => optional($page->published_at)->toIso8601String(),
            'publish_at' => optional($page->publish_at)->toIso8601String(),
            'unpublish_at' => optional($page->unpublish_at)->toIso8601String(),
            'updated_at' => optional($page->updated_at)->toIso8601String(),
            'created_at' => optional($page->created_at)->toIso8601String(),
            'deleted_at' => optional($page->deleted_at)->toIso8601String(),
        ];

        if ($withBlocks) {
            $payload['body_blocks'] = $page->body_blocks ?? [];
            $payload['body_html'] = $page->body_html ?? '';
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blockCatalog(): array
    {
        return array_map(
            fn ($type) => array_merge($type->toArray(), ['defaultAttrs' => $type->defaultAttrs]),
            $this->blocks->all(),
        );
    }

    /**
     * @return array<int, array{id: int, title: string, path: ?string}>
     */
    private function parentOptions(?int $excludeId): array
    {
        return CmsPage::query()
            ->when($excludeId !== null, fn ($q) => $q->whereKeyNot($excludeId))
            ->orderBy('title')
            ->get(['id', 'title', 'path'])
            ->map(fn (CmsPage $p) => ['id' => $p->id, 'title' => $p->title, 'path' => $p->path])
            ->all();
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    private function locales(): array
    {
        return [
            ['code' => 'en', 'label' => 'English'],
            ['code' => 'ar', 'label' => 'Arabic'],
            ['code' => 'fr', 'label' => 'French'],
            ['code' => 'es', 'label' => 'Spanish'],
            ['code' => 'de', 'label' => 'German'],
        ];
    }
}
