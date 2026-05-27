<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Public robots.txt.
 *
 * - In `production` env, allow indexing of the marketing surface
 *   and disallow private scopes (/admin, /t, /account, /checkout,
 *   /api, /webhooks, /preview, /onboarding).
 * - In any non-production env (local, staging) or when
 *   APP_ENV=staging, send a noindex/disallow-all to prevent
 *   staging deploys from being crawled.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemapUrl = url('/sitemap.xml');
        $isPublicEnv = app()->environment('production');

        if (! $isPublicEnv) {
            $body = "User-agent: *\nDisallow: /\nSitemap: {$sitemapUrl}\n";

            return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $disallow = [
            '/admin/',
            '/t/',
            '/account/',
            '/checkout/',
            '/api/',
            '/webhooks/',
            '/preview/',
            '/onboarding/',
            '/settings/',
        ];

        $lines = ['User-agent: *'];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }
        $lines[] = '';
        $lines[] = "Sitemap: {$sitemapUrl}";

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
