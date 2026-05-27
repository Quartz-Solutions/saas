<?php

namespace App\Console\Commands;

use App\Models\CmsPage;
use App\Support\Cms\PageService;
use Illuminate\Console\Command;

/**
 * Flips draft → published when `publish_at` rolls over, and published →
 * archived when `unpublish_at` rolls over. Run by the scheduler every
 * minute (registered in routes/console.php).
 */
class PublishScheduledCmsPages extends Command
{
    protected $signature = 'cms:publish-scheduled';

    protected $description = 'Promote scheduled CMS pages to published / unpublish expired ones.';

    public function handle(PageService $pages): int
    {
        $now = now();
        $published = 0;
        $expired = 0;

        CmsPage::query()
            ->where('status', CmsPage::STATUS_DRAFT)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->get()
            ->each(function (CmsPage $page) use ($pages, &$published) {
                $page->status = CmsPage::STATUS_PUBLISHED;
                $page->published_at = $page->published_at ?? $page->publish_at ?? now();
                $page->save();
                $pages->bustCache($page);
                $published++;
            });

        CmsPage::query()
            ->where('status', CmsPage::STATUS_PUBLISHED)
            ->whereNotNull('unpublish_at')
            ->where('unpublish_at', '<=', $now)
            ->get()
            ->each(function (CmsPage $page) use ($pages, &$expired) {
                $page->status = CmsPage::STATUS_ARCHIVED;
                $page->save();
                $pages->bustCache($page);
                $expired++;
            });

        $this->info("Published: {$published}, archived: {$expired}.");

        return self::SUCCESS;
    }
}
