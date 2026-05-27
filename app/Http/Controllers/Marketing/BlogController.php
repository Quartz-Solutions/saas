<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsBlogCategory;
use App\Models\CmsBlogPost;
use App\Models\CmsBlogTag;
use App\Support\Cms\CmsRefsService;
use Illuminate\Http\Response;
use Inertia\Inertia;

class BlogController extends Controller
{
    public function __construct(private readonly CmsRefsService $refs) {}

    public function index(): \Inertia\Response
    {
        $posts = CmsBlogPost::query()
            ->where('status', CmsBlogPost::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->with(['author:id,name', 'categories:id,slug,name', 'tags:id,slug,name'])
            ->paginate(12);

        return Inertia::render('marketing/blog/index', [
            'posts' => [
                'data' => $posts->getCollection()->map(fn ($p) => $this->serialize($p, withBlocks: false))->all(),
                'meta' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ],
            ],
            'categories' => CmsBlogCategory::query()->orderBy('name')->get(['id', 'slug', 'name'])->all(),
            'tags' => CmsBlogTag::query()->orderBy('name')->get(['id', 'slug', 'name'])->all(),
        ]);
    }

    public function show(string $slug): \Inertia\Response
    {
        $post = CmsBlogPost::query()
            ->with(['author:id,name', 'categories:id,slug,name', 'tags:id,slug,name'])
            ->where('slug', $slug)
            ->where('status', CmsBlogPost::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return Inertia::render('marketing/blog/show', [
            'post' => $this->serialize($post, withBlocks: true),
            'cmsRefs' => $this->refs->forBlocks((array) ($post->body_blocks ?? [])),
        ]);
    }

    public function byCategory(string $slug): \Inertia\Response
    {
        $category = CmsBlogCategory::query()->where('slug', $slug)->firstOrFail();

        $posts = $category->load(['posts' => fn ($q) => $q
            ->where('status', CmsBlogPost::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at'),
        ])->posts ?? collect();

        return Inertia::render('marketing/blog/archive', [
            'archive' => ['kind' => 'category', 'slug' => $slug, 'name' => $category->name],
            'posts' => $posts->map(fn ($p) => $this->serialize($p, withBlocks: false))->all(),
        ]);
    }

    public function byTag(string $slug): \Inertia\Response
    {
        $tag = CmsBlogTag::query()->where('slug', $slug)->firstOrFail();

        $posts = CmsBlogPost::query()
            ->whereHas('tags', fn ($q) => $q->where('cms_blog_tags.slug', $slug))
            ->where('status', CmsBlogPost::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->get();

        return Inertia::render('marketing/blog/archive', [
            'archive' => ['kind' => 'tag', 'slug' => $slug, 'name' => $tag->name],
            'posts' => $posts->map(fn ($p) => $this->serialize($p, withBlocks: false))->all(),
        ]);
    }

    public function feed(): Response
    {
        $posts = CmsBlogPost::query()
            ->where('status', CmsBlogPost::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->limit(50)
            ->get(['id', 'slug', 'title', 'excerpt', 'published_at']);

        $items = '';
        foreach ($posts as $post) {
            $url = url('/blog/'.$post->slug);
            $items .= "  <item>\n";
            $items .= '    <title>'.htmlspecialchars($post->title, ENT_XML1).'</title>'."\n";
            $items .= "    <link>{$url}</link>\n";
            $items .= "    <guid isPermaLink=\"true\">{$url}</guid>\n";
            $items .= '    <pubDate>'.optional($post->published_at)->toRfc2822String().'</pubDate>'."\n";
            if ($post->excerpt) {
                $items .= '    <description>'.htmlspecialchars($post->excerpt, ENT_XML1).'</description>'."\n";
            }
            $items .= "  </item>\n";
        }

        $siteName = (string) config('app.name');
        $homeUrl = url('/');
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
  <title>{$siteName} Blog</title>
  <link>{$homeUrl}</link>
  <description>Latest posts from {$siteName}.</description>
{$items}</channel>
</rss>

XML;

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CmsBlogPost $post, bool $withBlocks): array
    {
        $payload = [
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'cover_image_url' => $post->cover_image_url,
            'published_at' => optional($post->published_at)->toIso8601String(),
            'reading_minutes' => $post->reading_minutes,
            'no_index' => (bool) $post->no_index,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'author' => $post->author ? ['id' => $post->author->id, 'name' => $post->author->name] : null,
            'categories' => $post->relationLoaded('categories')
                ? $post->categories->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->all()
                : [],
            'tags' => $post->relationLoaded('tags')
                ? $post->tags->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name])->all()
                : [],
        ];

        if ($withBlocks) {
            $payload['body_blocks'] = $post->body_blocks ?? [];
            $payload['body_html'] = $post->body_html ?? '';
        }

        return $payload;
    }
}
