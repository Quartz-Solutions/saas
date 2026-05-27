<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\CmsBlogPostRequest;
use App\Models\CmsBlogCategory;
use App\Models\CmsBlogPost;
use App\Models\CmsBlogTag;
use App\Support\Cms\BlockTypeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogPostsController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly BlockTypeRegistry $blocks) {}

    public function index(Request $request): Response
    {
        $posts = CmsBlogPost::query()
            ->withTrashed()
            ->with(['author:id,name', 'categories:id,name', 'tags:id,name'])
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/cms/blog/posts/index', [
            'posts' => [
                'data' => $posts->getCollection()->map(fn (CmsBlogPost $p) => $this->serialize($p))->all(),
                'meta' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cms/blog/posts/edit', [
            'post' => null,
            'blockCatalog' => $this->blockCatalog(),
            'categories' => CmsBlogCategory::query()->orderBy('name')->get(['id', 'name', 'slug'])->all(),
            'tags' => CmsBlogTag::query()->orderBy('name')->get(['id', 'name', 'slug'])->all(),
        ]);
    }

    public function store(CmsBlogPostRequest $request): RedirectResponse
    {
        $post = $this->save(null, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post created.')]);

        return to_route('admin.cms.blog.posts.edit', ['post' => $post->id]);
    }

    public function edit(int $post): Response
    {
        $row = CmsBlogPost::withTrashed()->with(['categories', 'tags', 'author:id,name'])->findOrFail($post);

        return Inertia::render('admin/cms/blog/posts/edit', [
            'post' => array_merge($this->serialize($row), [
                'body_blocks' => $row->body_blocks ?? [],
                'category_ids' => $row->categories->pluck('id')->all(),
                'tag_ids' => $row->tags->pluck('id')->all(),
            ]),
            'blockCatalog' => $this->blockCatalog(),
            'categories' => CmsBlogCategory::query()->orderBy('name')->get(['id', 'name', 'slug'])->all(),
            'tags' => CmsBlogTag::query()->orderBy('name')->get(['id', 'name', 'slug'])->all(),
        ]);
    }

    public function update(CmsBlogPostRequest $request, int $post): RedirectResponse
    {
        $row = CmsBlogPost::query()->findOrFail($post);
        $this->save($row, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post saved.')]);

        return to_route('admin.cms.blog.posts.edit', ['post' => $row->id]);
    }

    public function destroy(int $post): RedirectResponse
    {
        $row = CmsBlogPost::query()->findOrFail($post);
        $row->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post archived.')]);

        return to_route('admin.cms.blog.posts.index');
    }

    private function save(?CmsBlogPost $post, CmsBlogPostRequest $request): CmsBlogPost
    {
        $data = $request->validated();
        $blocks = $data['body_blocks'] ?? null;

        if (is_array($blocks)) {
            $this->blocks->validateTree($blocks);
        }

        $slug = $data['slug'] ?? Str::slug($data['title']);

        $post ??= new CmsBlogPost;
        $post->fill([
            'slug' => $slug,
            'title' => $data['title'],
            'locale' => $data['locale'] ?? 'en',
            'excerpt' => $data['excerpt'] ?? null,
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'body_blocks' => $blocks,
            'status' => $data['status'] ?? CmsBlogPost::STATUS_DRAFT,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'no_index' => (bool) ($data['no_index'] ?? false),
            'author_id' => $post->author_id ?? optional($request->user())->id,
        ]);

        // First-time publish stamps published_at.
        if ($post->status === CmsBlogPost::STATUS_PUBLISHED && $post->published_at === null) {
            $post->published_at = now();
        }

        // Reading minutes — rough word-count estimate from text blocks.
        $post->reading_minutes = $this->estimateReadingMinutes($blocks ?? []);
        $post->save();

        $post->categories()->sync($data['category_ids'] ?? []);
        $post->tags()->sync($data['tag_ids'] ?? []);

        return $post->fresh();
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
     * @param  array<int, mixed>  $blocks
     */
    private function estimateReadingMinutes(array $blocks): int
    {
        $words = 0;
        foreach ($blocks as $block) {
            $attrs = (array) ($block['attrs'] ?? []);
            foreach (['html', 'body', 'subtitle', 'title', 'message', 'code', 'caption'] as $field) {
                if (! empty($attrs[$field]) && is_string($attrs[$field])) {
                    $words += str_word_count(strip_tags($attrs[$field]));
                }
            }
        }

        return (int) max(1, ceil($words / 200));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CmsBlogPost $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'locale' => $post->locale,
            'excerpt' => $post->excerpt,
            'cover_image_url' => $post->cover_image_url,
            'status' => $post->status,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'no_index' => (bool) $post->no_index,
            'reading_minutes' => $post->reading_minutes,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'updated_at' => optional($post->updated_at)->toIso8601String(),
            'deleted_at' => optional($post->deleted_at)->toIso8601String(),
            'author' => $post->author ? ['id' => $post->author->id, 'name' => $post->author->name] : null,
            'categories' => $post->relationLoaded('categories')
                ? $post->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all()
                : [],
            'tags' => $post->relationLoaded('tags')
                ? $post->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all()
                : [],
        ];
    }
}
