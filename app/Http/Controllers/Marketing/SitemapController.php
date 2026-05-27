<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Public XML sitemap — search engines hit GET /sitemap.xml.
     *
     * Includes the static marketing pages (home, pricing, legal) and every
     * published CmsPage that's not marked `no_index`. Private scopes
     * (/admin, /t/{slug}, /checkout, /account, /api) are intentionally
     * excluded via robots.txt rather than here — keeping them out of the
     * sitemap is the second line of defence.
     */
    public function __invoke(): Response
    {
        $urls = $this->collectUrls();

        $xml = $this->renderXml($urls);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string}>
     */
    protected function collectUrls(): array
    {
        $urls = [
            [
                'loc' => route('home'),
                'lastmod' => null,
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => route('marketing.pricing'),
                'lastmod' => null,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            [
                'loc' => route('marketing.docs.index'),
                'lastmod' => null,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ],
        ];

        foreach (['privacy', 'terms', 'cookies'] as $type) {
            $urls[] = [
                'loc' => route('marketing.legal.show', ['type' => $type]),
                'lastmod' => null,
                'changefreq' => 'yearly',
                'priority' => '0.3',
            ];
        }

        $pages = CmsPage::query()
            ->where('status', CmsPage::STATUS_PUBLISHED)
            ->where('no_index', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get(['slug', 'updated_at', 'template']);

        foreach ($pages as $page) {
            $urls[] = [
                'loc' => route('marketing.docs.show', ['slug' => $page->slug]),
                'lastmod' => $page->updated_at instanceof CarbonImmutable
                    ? $page->updated_at->toAtomString()
                    : optional($page->updated_at)->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => $page->template === CmsPage::TEMPLATE_DOCS ? '0.6' : '0.5',
            ];
        }

        return $urls;
    }

    /**
     * @param  array<int, array<string, ?string>>  $urls
     */
    protected function renderXml(array $urls): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
            if (! empty($url['lastmod'])) {
                $lines[] = '    <lastmod>'.htmlspecialchars($url['lastmod'], ENT_XML1).'</lastmod>';
            }
            if (! empty($url['changefreq'])) {
                $lines[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
            }
            if (! empty($url['priority'])) {
                $lines[] = '    <priority>'.$url['priority'].'</priority>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }
}
