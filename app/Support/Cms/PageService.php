<?php

namespace App\Support\Cms;

use App\Events\CmsContentPublished;
use App\Models\CmsPage;
use App\Models\CmsPageVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Canonical service for CmsPage create/update. Centralises:
 *   - block-tree validation through the BlockTypeRegistry
 *   - slug + nested-path materialisation
 *   - cache-bust on save
 *   - (in M11) version snapshots
 *
 * Controllers always go through here; no direct CmsPage::create() in the
 * admin scope.
 */
class PageService
{
    public function __construct(private readonly BlockTypeRegistry $blocks) {}

    /**
     * Persist a new page or update an existing one. Validates blocks
     * against the registry. Returns the saved model.
     *
     * @param  array<string, mixed>  $attrs
     */
    public function save(?CmsPage $page, array $attrs): CmsPage
    {
        return DB::transaction(function () use ($page, $attrs) {
            $blocks = $attrs['body_blocks'] ?? null;
            if (is_array($blocks)) {
                $this->blocks->validateTree($blocks);
            }

            $page ??= new CmsPage;

            $payload = [
                'slug' => $this->normaliseSlug($attrs['slug'] ?? ($attrs['title'] ?? '')),
                'title' => $attrs['title'] ?? $page->title ?? 'Untitled',
                'locale' => $attrs['locale'] ?? $page->locale ?? 'en',
                'parent_id' => $attrs['parent_id'] ?? $page->parent_id,
                'route_name' => $attrs['route_name'] ?? $page->route_name,
                'status' => $attrs['status'] ?? $page->status ?? CmsPage::STATUS_DRAFT,
                'template' => $attrs['template'] ?? $page->template ?? CmsPage::TEMPLATE_DEFAULT,
                'meta_title' => $attrs['meta_title'] ?? $page->meta_title,
                'meta_description' => $attrs['meta_description'] ?? $page->meta_description,
                'og_image_path' => $attrs['og_image_path'] ?? $page->og_image_path,
                'no_index' => array_key_exists('no_index', $attrs) ? (bool) $attrs['no_index'] : (bool) $page->no_index,
                'body_blocks' => $blocks,
                'body_markdown' => $attrs['body_markdown'] ?? $page->body_markdown,
                'body_html' => $attrs['body_html'] ?? $page->body_html,
                'publish_at' => $attrs['publish_at'] ?? $page->publish_at,
                'unpublish_at' => $attrs['unpublish_at'] ?? $page->unpublish_at,
            ];

            // Promote draft → published the first time published_at is set.
            $publishedAt = $attrs['published_at'] ?? $page->published_at;
            if ($payload['status'] === CmsPage::STATUS_PUBLISHED && $publishedAt === null) {
                $publishedAt = now();
            }
            $payload['published_at'] = $publishedAt;

            if ($payload['status'] !== CmsPage::STATUS_PUBLISHED) {
                // Keep timestamp around (drafts can have a published_at that was scheduled).
                $payload['published_at'] = $publishedAt;
            }

            $payload['author_id'] = $attrs['author_id']
                ?? $page->author_id
                ?? optional(request()->user())->id;

            $page->fill($payload);
            $page->path = $this->materialisePath($page, $payload['slug']);
            $page->save();

            $this->snapshot($page, optional(request()->user())->id);
            $this->bustCache($page);

            return $page->fresh();
        });
    }

    /**
     * Persist a versioned snapshot of the page's current state. Called
     * after every save in `save()`. Lets admins compare and restore
     * earlier revisions.
     */
    public function snapshot(CmsPage $page, ?int $userId = null, ?string $note = null): CmsPageVersion
    {
        $next = (int) (CmsPageVersion::query()
            ->where('cms_page_id', $page->id)
            ->max('version_no') ?? 0) + 1;

        return CmsPageVersion::query()->create([
            'cms_page_id' => $page->id,
            'version_no' => $next,
            'snapshot' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'locale' => $page->locale,
                'template' => $page->template,
                'status' => $page->status,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'no_index' => (bool) $page->no_index,
                'body_blocks' => $page->body_blocks,
                'body_html' => $page->body_html,
            ],
            'author_id' => $userId,
            'note' => $note,
        ]);
    }

    /**
     * Roll the page back to an earlier version. The current state is
     * snapshotted as a new version first so the rollback itself is
     * reversible.
     */
    public function restoreVersion(CmsPage $page, CmsPageVersion $version, ?int $userId = null): CmsPage
    {
        return DB::transaction(function () use ($page, $version, $userId) {
            // Snapshot current first so the restore is reversible.
            $this->snapshot($page, $userId, 'auto: pre-restore from v'.$version->version_no);

            $s = (array) $version->snapshot;
            $page->fill([
                'title' => $s['title'] ?? $page->title,
                'slug' => $s['slug'] ?? $page->slug,
                'locale' => $s['locale'] ?? $page->locale,
                'template' => $s['template'] ?? $page->template,
                'status' => $s['status'] ?? $page->status,
                'meta_title' => $s['meta_title'] ?? null,
                'meta_description' => $s['meta_description'] ?? null,
                'no_index' => (bool) ($s['no_index'] ?? false),
                'body_blocks' => $s['body_blocks'] ?? null,
                'body_html' => $s['body_html'] ?? null,
            ]);
            $page->path = $this->materialisePath($page, (string) $page->slug);
            $page->save();

            $this->snapshot($page, $userId, 'restored from v'.$version->version_no);
            $this->bustCache($page);

            return $page->fresh();
        });
    }

    public function publish(CmsPage $page): CmsPage
    {
        $page->status = CmsPage::STATUS_PUBLISHED;
        if ($page->published_at === null) {
            $page->published_at = now();
        }
        $page->save();
        $this->bustCache($page);

        return $page->fresh();
    }

    public function unpublish(CmsPage $page): CmsPage
    {
        $page->status = CmsPage::STATUS_DRAFT;
        $page->save();
        $this->bustCache($page);

        return $page->fresh();
    }

    public function archive(CmsPage $page): CmsPage
    {
        $page->status = CmsPage::STATUS_ARCHIVED;
        $page->save();
        $this->bustCache($page);

        return $page->fresh();
    }

    public function bustCache(Model $page): void
    {
        if (! $page instanceof CmsPage) {
            return;
        }

        // Forget per-locale cache keys (M13 introduced locale-suffixed keys).
        foreach ((array) config('cms.locales', ['en']) as $locale) {
            cache()->forget('cms.page:'.$page->slug.':'.$locale);
        }
        // Legacy key for any non-locale-aware caller.
        cache()->forget('cms.page:'.$page->slug);
        cache()->forget('marketing.docs.index');

        // Notify listeners (CDN purger, sitemap warmer, etc.).
        CmsContentPublished::dispatch($page);
    }

    /**
     * Build the materialised URL path from the parent chain + slug.
     * Top-level pages have path = slug; nested pages get parent.path/slug.
     */
    protected function materialisePath(CmsPage $page, string $slug): string
    {
        if ($page->parent_id !== null) {
            $parent = CmsPage::query()->find($page->parent_id);
            if ($parent && $parent->path) {
                return trim($parent->path, '/').'/'.$slug;
            }
        }

        return $slug;
    }

    protected function normaliseSlug(string $candidate): string
    {
        $slug = Str::slug($candidate);

        return $slug === '' ? 'page-'.Str::lower(Str::random(6)) : $slug;
    }
}
