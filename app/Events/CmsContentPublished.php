<?php

namespace App\Events;

use App\Models\CmsPage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a CMS page is saved, restored, published, unpublished
 * or archived. Listeners use this to invalidate caches, warm sitemaps,
 * or push a CDN purge.
 *
 * Reuses the page slug + locale as a cache surrogate-key so a
 * Cloudflare integration (post-v1) can purge by-tag without rebuilding
 * everything.
 */
class CmsContentPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CmsPage $page,
        public readonly string $reason = 'saved',
    ) {}

    /**
     * The surrogate-key tag for CDN purging.
     */
    public function tag(): string
    {
        return "cms-page:{$this->page->slug}:{$this->page->locale}";
    }
}
