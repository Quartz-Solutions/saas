<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the public /docs section with first-class superadmin documentation
 * for every capability of the CMS module.
 *
 * Each page is `template=docs`, `status=published`, and uses block-based
 * `body_blocks` so the renderer dogfoods its own block library. The pages
 * also serve as a quick "show me how every block looks" gallery for the
 * superadmin trying out the system on a fresh install.
 *
 * Update or delete these pages once a project has authored its own docs.
 */
class CmsDocsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            CmsPage::query()->updateOrCreate(
                ['slug' => $slug, 'locale' => 'en'],
                [
                    'title' => $page['title'],
                    'meta_title' => $page['title'].' — CMS docs',
                    'meta_description' => $page['summary'],
                    'template' => CmsPage::TEMPLATE_DOCS,
                    'status' => CmsPage::STATUS_PUBLISHED,
                    'published_at' => now()->subDay(),
                    'no_index' => false,
                    'body_blocks' => $page['blocks'],
                    'body_html' => null,
                    'body_markdown' => null,
                ],
            );
        }
    }

    /**
     * @return array<string, array{title: string, summary: string, blocks: array<int, array<string, mixed>>}>
     */
    protected function pages(): array
    {
        return [
            'cms-overview' => $this->overview(),
            'cms-pages' => $this->pagesGuide(),
            'cms-blocks' => $this->blocksGuide(),
            'cms-globals' => $this->globalsGuide(),
            'cms-media' => $this->mediaGuide(),
            'cms-collections' => $this->collectionsGuide(),
            'cms-blog' => $this->blogGuide(),
            'cms-forms' => $this->formsGuide(),
            'cms-newsletter' => $this->newsletterGuide(),
            'cms-redirects' => $this->redirectsGuide(),
            'cms-seo' => $this->seoGuide(),
            'cms-i18n' => $this->i18nGuide(),
            'cms-versions-preview' => $this->versionsPreviewGuide(),
            'cms-cache' => $this->cacheGuide(),
        ];
    }

    /* -----------------------------------------------------------------
     * Page builders — each returns an array with title, summary, blocks.
     * -----------------------------------------------------------------*/

    protected function overview(): array
    {
        return [
            'title' => 'CMS overview',
            'summary' => 'What the CMS module gives you out of the box — pages, blog, globals, media, forms, newsletter, redirects, SEO, i18n, versions and caching.',
            'blocks' => [
                $this->richText('<p>The CMS module ships with this boilerplate and handles every public-facing surface of your SaaS — landing page, pricing copy, docs, blog, legal pages, contact form, and site-wide brand. It is <strong>global to the install</strong>, not per-tenant: everything you edit here is what the world sees before login.</p>'),
                $this->richText('<h2>Where to start</h2><p>Sign in as Super Admin and open <a href="/admin/cms"><code>/admin/cms</code></a>. The left sidebar lists every section described below.</p>'),
                $this->divider(),
                $this->richText('<h2>What you can manage</h2><ul><li><strong>Pages</strong> — any URL on the public site, composed from typed blocks.</li><li><strong>Blog</strong> — posts, categories, tags, RSS.</li><li><strong>Globals</strong> — brand, navigation, footer, announcement, analytics, cookie banner, contact, social, SEO defaults.</li><li><strong>Media library</strong> — uploads, alt text, focal point.</li><li><strong>Reusable content</strong> — features, testimonials, FAQs, logos.</li><li><strong>Forms</strong> — contact / lead-capture builders + submissions inbox.</li><li><strong>Newsletter</strong> — driver-pluggable subscribers (Database, Mailchimp, Resend, ConvertKit).</li><li><strong>Redirects</strong> — 301/302 manager + 404 log.</li><li><strong>SEO</strong> — per-page meta + site-wide defaults + sitemap + robots.txt.</li><li><strong>i18n</strong> — multi-locale authoring with visitor-locale resolution.</li><li><strong>Versions &amp; preview</strong> — automatic snapshots, restore, signed preview URLs.</li><li><strong>Cache</strong> — stale-while-revalidate plus event-based invalidation.</li></ul>'),
                $this->divider(),
                $this->richText('<h2>Mental model</h2><p>Everything you author lands in the <code>cms_*</code> tables. The Inertia + React front-end reads them through the marketing controllers and renders blocks via <code>&lt;BlockRenderer&gt;</code>. The same block library is used for landing pages, docs pages, legal pages, and blog posts — author once, render anywhere.</p><p>For the wire-level conventions, see <a href="/docs/cms-blocks">Block library</a>.</p>'),
                $this->ctaBanner(
                    title: 'Open the CMS admin',
                    body: 'Every section in this guide maps to a sidebar entry under Admin → CMS.',
                    primary: ['Go to admin', '/admin/cms/pages'],
                    secondary: ['Read the pages guide', '/docs/cms-pages'],
                ),
            ],
        ];
    }

    protected function pagesGuide(): array
    {
        return [
            'title' => 'Pages',
            'summary' => 'Author landing, docs, and legal pages from typed blocks. Drag-to-reorder, drafts, scheduled publishing, versioned snapshots.',
            'blocks' => [
                $this->richText('<p>Pages live in <code>cms_pages</code>. Each page is a tree of typed <strong>blocks</strong> rendered by <code>&lt;BlockRenderer&gt;</code>. Manage them at <a href="/admin/cms/pages"><code>/admin/cms/pages</code></a>.</p>'),

                $this->richText('<h2>Quick start</h2><ol><li>Open <strong>Admin → CMS → Pages</strong>.</li><li>Click <strong>New page</strong>.</li><li>Pick a template (Default / Landing / Docs / Legal).</li><li>Set the title — the slug is auto-derived but editable.</li><li>Drop blocks in via <strong>Add block</strong>. Drag the grip handle to reorder. Click a block header to edit its attributes.</li><li>Switch <strong>Status</strong> from Draft to Published.</li><li>Save.</li></ol>'),

                $this->divider(),

                $this->richText('<h2>Templates</h2><ul><li><strong>Default</strong> — generic public page, no special chrome.</li><li><strong>Landing</strong> — marketing pages (homepage replacement, feature deep-dives).</li><li><strong>Docs</strong> — appears in the <a href="/docs">/docs index</a>, has a "back to docs" header.</li><li><strong>Legal</strong> — Privacy / Terms / Cookies / Refund (this is what you want for compliance copy).</li></ul>'),

                $this->richText('<h2>Slug, path, and URL</h2><p>Each page has a <code>slug</code> (e.g. <code>pricing</code>). When a parent page is set, the URL becomes <code>{parent.path}/{slug}</code> — e.g. <code>/docs/getting-started/install</code> for a slug <code>install</code> nested under <code>getting-started</code>. The materialised <code>path</code> column is computed on save and indexed for fast lookup.</p>'),

                $this->richText('<h2>Reserved route slots</h2><p>A page can claim a reserved URL slot via the <strong>Route slot</strong> setting:</p><ul><li><code>home</code> — replaces the hardcoded landing page at <code>/</code>.</li><li><code>pricing</code> — augments <code>/pricing</code> (the plan grid is still rendered from <code>plans</code>).</li><li><code>about</code>, <code>features</code>, <code>contact</code> — claim those vanity URLs without writing routes.</li></ul><p>When a published page has <code>route_name=home</code>, <code>HomeController</code> renders the block tree instead of the hardcoded feature list.</p>'),

                $this->divider(),

                $this->richText('<h2>Status &amp; scheduling</h2><p>A page has one of three statuses: <strong>Draft</strong>, <strong>Published</strong>, or <strong>Archived</strong>. Set <code>publish_at</code> to a future date+time and the <code>cms:publish-scheduled</code> scheduler tick (runs every minute) will flip Draft → Published when the time rolls over. <code>unpublish_at</code> works the same way in reverse — Published → Archived.</p>'),

                $this->codeBlock('php artisan cms:publish-scheduled', 'bash', 'Manual run'),

                $this->richText('<h2>Versions &amp; restore</h2><p>Every save creates a row in <code>cms_page_versions</code>. Open <strong>Versions</strong> from the page edit screen to see the timeline and roll back to any earlier snapshot. A restore creates its own version first, so the rollback is itself reversible.</p>'),

                $this->divider(),

                $this->richText('<h2>Preview unpublished work</h2><p>The <strong>Preview</strong> tab on the page editor mints a signed URL of the form <code>/preview/page/{id}?signature=…&amp;expires=…</code> and embeds it in an iframe (mobile / tablet / desktop viewports). Hit <strong>Refresh</strong> after saving to see your latest changes. The signed URL is valid for 30 minutes.</p>'),

                $this->ctaBanner(
                    title: 'Try the block editor',
                    body: 'Open the editor on any existing page to see the block library in action.',
                    primary: ['Go to pages', '/admin/cms/pages'],
                    secondary: ['Read about blocks', '/docs/cms-blocks'],
                ),
            ],
        ];
    }

    protected function blocksGuide(): array
    {
        return [
            'title' => 'Block library',
            'summary' => 'The 18 block types you can compose pages from — content blocks (rich text, image, video, code, divider, raw HTML) and marketing blocks (hero, features, pricing, testimonials, logos, stats, FAQ, CTA, newsletter, contact, announcement).',
            'blocks' => [
                $this->richText('<p>Every CMS page and blog post is composed from <strong>typed blocks</strong>. The catalog is declared in <code>config/cms.php</code> and bound to the <code>BlockTypeRegistry</code> singleton. Each block has a kebab-case <code>type</code>, a default attribute set, and validation rules enforced server-side.</p><p>Open the <strong>Add block</strong> dialog in the page editor to see them grouped — Content vs. Marketing.</p>'),

                $this->divider(),

                $this->richText('<h2>Content blocks</h2><ul><li><strong>Rich text</strong> — headings, paragraphs, lists, links. The <code>html</code> attribute accepts inline formatting. Best for prose between marketing sections.</li><li><strong>Image</strong> — single image with caption, layout (<code>contained</code> / <code>full</code> / <code>narrow</code>) and alignment.</li><li><strong>Video embed</strong> — YouTube, Vimeo, Mux, or direct URL. Aspect ratio configurable.</li><li><strong>Code block</strong> — syntax-highlighted snippet with optional filename header.</li><li><strong>Divider</strong> — visual separator (<code>line</code> / <code>dotted</code> / <code>space</code>).</li><li><strong>Raw HTML</strong> — admin-only escape hatch when you need something the other blocks can\'t express.</li></ul>'),

                $this->richText('<h2>Marketing blocks</h2><ul><li><strong>Hero</strong> — top-of-page headline + subtitle + two CTAs. Three layouts: <code>centered</code>, <code>split-left</code>, <code>split-right</code>.</li><li><strong>Feature grid</strong> — pulls items from the Features collection by ID. Set columns (1-4).</li><li><strong>Feature split</strong> — alternating image + text section.</li><li><strong>Pricing</strong> — plan grid sourced from your <code>plans</code> table. Use <code>plan_slugs</code> to filter, <code>highlight_slug</code> to feature one.</li><li><strong>Testimonials</strong> — quote(s) referenced by ID, rendered as single / carousel / grid.</li><li><strong>Logo cloud</strong> — customer / partner logos by <code>group_slug</code>.</li><li><strong>Stats</strong> — headline numbers (e.g. "10k users / 99.9% uptime").</li><li><strong>FAQ</strong> — accordion sourced from FAQs collection by group.</li><li><strong>CTA banner</strong> — mid-page conversion strip with primary + secondary CTAs.</li><li><strong>Newsletter signup</strong> — wires to the active newsletter provider.</li><li><strong>Contact form</strong> — embeds a form definition by <code>form_slug</code>.</li><li><strong>Announcement strip</strong> — page-local notice. (Site-wide one lives in Globals.)</li></ul>'),

                $this->divider(),

                $this->richText('<h2>How attributes are validated</h2><p>Each block type declares Laravel validation rules in <code>config/cms.php</code>. When you save a page, the <code>ValidBlockTree</code> rule walks every block, checks its type is registered, and validates its attributes. Unknown block types are rejected at write time so the renderer never has to guard against them.</p>'),

                $this->codeBlock(<<<'PHP'
'rules' => [
    'title' => ['required', 'string', 'max:240'],
    'subtitle' => ['nullable', 'string', 'max:1000'],
    'primary_cta_label' => ['nullable', 'string', 'max:64'],
    'primary_cta_url' => ['nullable', 'string', 'max:2048'],
    'layout' => ['nullable', 'in:centered,split-left,split-right'],
],
PHP, 'php', 'config/cms.php (hero rules)'),

                $this->richText('<h2>Adding a new block type</h2><ol><li>Add an entry to <code>config/cms.php</code> under <code>blocks</code> with <code>id</code>, <code>label</code>, <code>group</code>, <code>icon</code>, <code>defaultAttrs</code>, and <code>rules</code>.</li><li>Create a matching React component at <code>resources/js/components/cms/blocks/your-block.tsx</code>.</li><li>Register it in the <code>REGISTRY</code> map in <code>block-renderer.tsx</code>.</li></ol><p>The admin block picker will pick up the new entry automatically.</p>'),
            ],
        ];
    }

    protected function globalsGuide(): array
    {
        return [
            'title' => 'Globals',
            'summary' => 'Site-wide singletons — brand, navigation, footer, announcement, analytics, cookie banner, contact, social handles, SEO defaults.',
            'blocks' => [
                $this->richText('<p><strong>Globals</strong> are the bits of content that appear on every public page — your logo, header nav, footer, analytics IDs, cookie copy, contact info. They live in <code>cms_globals</code> as singletons (one row per key) and are shared with the public layout on every request via Inertia.</p><p>Manage them at <a href="/admin/cms/globals"><code>/admin/cms/globals</code></a>.</p>'),

                $this->divider(),

                $this->richText('<h2>The 9 globals</h2><ul><li><strong>Brand &amp; theme</strong> — logo (light + dark), favicon, default OG image, brand color, accent color, fonts.</li><li><strong>Header navigation</strong> — top nav with drag-to-reorder items.</li><li><strong>Footer navigation</strong> — multi-column links + copyright line + tagline.</li><li><strong>Announcement bar</strong> — optional strip at the top of every page (enable, message, link, variant, dismissible).</li><li><strong>Analytics</strong> — GA4, Plausible, PostHog, GTM, Meta Pixel, Hotjar — leave blank to skip.</li><li><strong>Cookie banner</strong> — copy, accept/reject labels, link to your cookie policy.</li><li><strong>Contact info</strong> — used in footer + <code>/contact</code> + LocalBusiness JSON-LD.</li><li><strong>Social handles</strong> — Twitter / LinkedIn / GitHub / YouTube / Facebook / Instagram + Twitter handle for cards.</li><li><strong>SEO defaults</strong> — site name, title template (<code>{page} | {site}</code>), default description, default OG image, robots directive.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>How values resolve</h2><p>Each global has a <strong>defaults</strong> array declared in <code>config/cms.php</code> and an overrides row in <code>cms_globals</code>. When you read a global, defaults are merged with the override — only keys you explicitly set go to the DB. Unknown keys are silently dropped to prevent schema drift.</p>'),

                $this->codeBlock(<<<'PHP'
// Read a global from PHP (controllers, listeners):
$brand = app(\App\Support\Cms\GlobalsService::class)->get('brand');
// Read in React (any public page):
const { cmsGlobals } = usePage<SharedProps>().props;
PHP, 'php', 'Reading globals'),

                $this->richText('<h2>The header &amp; footer menus</h2><p>The header menu is a flat list of <code>{ label, url, target }</code> items. Drag the grip handle in the admin editor to reorder. The footer is a list of <strong>columns</strong>, each with a title and its own item list. Add as many columns as you like.</p><p>If you leave both globals empty, the public layout falls back to a sensible default (Pricing, Docs, Features in the header; Product + Legal in the footer).</p>'),

                $this->richText('<h2>Tip: keep brand &amp; OG image in sync</h2><p>The default OG image you set under <strong>Brand</strong> is also used as the SEO fallback when a page leaves its <code>og_image</code> blank. Upload a 1200×630 to <a href="/admin/cms/media">Media</a>, paste the URL into <strong>Brand → Default OG image</strong> and you\'re covered for every page that doesn\'t set its own.</p>'),

                $this->ctaBanner(
                    title: 'Configure your brand',
                    body: 'Set the logo, colors, and OG image once — every page picks them up.',
                    primary: ['Edit Brand', '/admin/cms/globals/brand'],
                    secondary: ['Open Globals', '/admin/cms/globals'],
                ),
            ],
        ];
    }

    protected function mediaGuide(): array
    {
        return [
            'title' => 'Media library',
            'summary' => 'Upload images once, reference them by URL anywhere. Alt text + focal point + hash-based deduplication.',
            'blocks' => [
                $this->richText('<p>The media library is your single home for image uploads used across CMS pages, blog covers, brand assets, and Globals. Manage at <a href="/admin/cms/media"><code>/admin/cms/media</code></a>.</p>'),

                $this->richText('<h2>Uploading</h2><ol><li>Click <strong>Upload</strong> in the toolbar (or drag files into the grid).</li><li>Multiple files at once is fine — they upload in parallel.</li><li>Accepted formats: JPG, PNG, GIF, WEBP, SVG, AVIF, ICO.</li><li>Max file size: 10 MB per file (configurable in <code>MediaUploadRequest</code>).</li></ol>'),

                $this->richText('<h2>Editing</h2><p>Click any thumbnail to open the edit dialog. You can update:</p><ul><li><strong>Alt text</strong> — used by screen readers and as the image fallback. Required for accessibility on every public image.</li><li><strong>Focal point</strong> — (focal_x, focal_y) between 0 and 1. Tells responsive crops which point should stay visible (M14-era resize is out of scope for v1; the value is stored ready for future Glide variants).</li><li><strong>Copy URL</strong> — paste the URL into block fields (e.g. <code>cover_image_url</code> on a blog post or <code>image_url</code> in a block).</li></ul>'),

                $this->divider(),

                $this->richText('<h2>Deduplication</h2><p>Every upload is hashed with SHA-256. If you upload the same file twice (by content), the second upload returns the existing row instead of creating a duplicate — saves disk and keeps references consistent.</p>'),

                $this->richText('<h2>Deleting</h2><p>Deleting from the media library is <strong>permanent</strong> — the row is soft-deleted but the underlying file is removed from storage immediately. Pages and blocks that referenced the URL will 404 for that image. Use with care.</p>'),

                $this->codeBlock(<<<'PHP'
// Programmatic upload (e.g. importer):
$asset = app(\App\Support\Cms\MediaService::class)
    ->upload($uploadedFile, $userId);

// URL for use in blocks / globals:
$url = app(\App\Support\Cms\MediaService::class)->urlFor($asset);
PHP, 'php', 'Using MediaService'),
            ],
        ];
    }

    protected function collectionsGuide(): array
    {
        return [
            'title' => 'Reusable collections',
            'summary' => 'Features, testimonials, FAQs, and logos — author once, reference by ID or group from any block.',
            'blocks' => [
                $this->richText('<p>Four collections back the marketing blocks that need repeating data. Manage them under <strong>Admin → CMS</strong>:</p><ul><li><a href="/admin/cms/collections/features">Features</a> — feeds the <code>feature_grid</code> block.</li><li><a href="/admin/cms/collections/testimonials">Testimonials</a> — feeds the <code>testimonials</code> block.</li><li><a href="/admin/cms/collections/faqs">FAQs</a> — feeds the <code>faq</code> block, grouped by slug.</li><li><a href="/admin/cms/collections/logos">Logos</a> — feeds the <code>logo_cloud</code> block, grouped by slug.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>Features</h2><p>Each feature has a <code>title</code>, <code>description</code>, <code>icon</code> (Lucide icon name like <code>shield</code>, <code>code</code>, <code>credit-card</code>), and a <code>sort_order</code>. Drop a <strong>Feature grid</strong> block on a page, then enter the IDs you want shown (comma-separated for now; a visual picker is a follow-up).</p>'),

                $this->richText('<h2>Testimonials</h2><p>Fields: quote, author name, author role, company, avatar URL, logo URL, rating (1–5). Reference by ID in the <strong>Testimonials</strong> block. Pick a layout: <code>single</code>, <code>carousel</code>, or <code>grid</code>.</p>'),

                $this->richText('<h2>FAQs</h2><p>Every FAQ row belongs to a <code>group_slug</code> (default: <code>default</code>). Use distinct groups to power different FAQ blocks on different pages — e.g. <code>pricing-faq</code> for the pricing page and <code>billing-faq</code> for the billing FAQ section. The block renders an accordion with HTML answers.</p>'),

                $this->richText('<h2>Logos</h2><p>Same grouping pattern as FAQs. Use it for "Trusted by" customer logos vs. "Integrates with" partner logos. The block fetches all active logos in the group, ordered by <code>sort_order</code>.</p>'),

                $this->divider(),

                $this->richText('<h2>How references resolve</h2><p>When a public page is rendered, the marketing controller scans the page\'s blocks, collects every <code>feature_ids</code>, <code>testimonial_ids</code>, <code>group_slug</code>, and <code>plan_slugs</code> reference, and pre-loads exactly those rows in a single round-trip. The bundle is shared as <code>cmsRefs</code> in Inertia props — no N+1, no extra queries per block.</p>'),
            ],
        ];
    }

    protected function blogGuide(): array
    {
        return [
            'title' => 'Blog',
            'summary' => 'Posts authored with the same block editor as pages. Categories, tags, RSS, auto reading-minutes.',
            'blocks' => [
                $this->richText('<p>The blog lives at <a href="/blog">/blog</a> publicly and at <a href="/admin/cms/blog/posts">Admin → CMS → Blog</a> for editing. Posts use the <strong>same block editor</strong> as CMS pages, so the same shortcuts and block library apply.</p>'),

                $this->richText('<h2>Authoring a post</h2><ol><li>Open <strong>Admin → CMS → Blog → New post</strong>.</li><li>Set title, slug, excerpt, and (optional) cover image URL.</li><li>Compose the body in the <strong>Content</strong> tab using blocks.</li><li>Pick categories and tags in the <strong>Categories &amp; Tags</strong> tab.</li><li>Switch <strong>Status</strong> from Draft to Published. <code>published_at</code> stamps automatically on first publish.</li><li>Save.</li></ol>'),

                $this->divider(),

                $this->richText('<h2>Reading time</h2><p>Reading minutes are computed automatically on save from the word count of every text-bearing block attribute (title, subtitle, body, html, code, message, caption). The estimate uses 200 words/minute — adjust in <code>BlogPostsController::estimateReadingMinutes</code> if your audience reads slower.</p>'),

                $this->richText('<h2>Public URLs</h2><ul><li><code>/blog</code> — index, 12 posts per page, newest first.</li><li><code>/blog/{slug}</code> — single post.</li><li><code>/blog/category/{slug}</code> — category archive.</li><li><code>/blog/tag/{slug}</code> — tag archive.</li><li><code>/blog/feed.xml</code> — RSS feed, last 50 published posts.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>Categories &amp; tags</h2><p>For v1 these are managed through the admin post editor — selecting "all categories I\'ve ever used" creates the rows on demand. (A dedicated taxonomy admin is a clean follow-up.)</p>'),

                $this->richText('<h2>SEO &amp; JSON-LD</h2><p>Each post has its own meta title, meta description, and <code>no_index</code> flag in the <strong>SEO</strong> tab. The public renderer also injects an <a href="https://schema.org/Article">Article</a> JSON-LD snippet automatically using the post\'s headline, publish date, author, and cover image.</p>'),

                $this->ctaBanner(
                    title: 'Publish your first post',
                    body: 'Drop a few blocks, pick a category, hit publish — it shows on /blog instantly.',
                    primary: ['Open blog admin', '/admin/cms/blog/posts'],
                ),
            ],
        ];
    }

    protected function formsGuide(): array
    {
        return [
            'title' => 'Forms',
            'summary' => 'Build contact / lead-capture forms in admin. Submissions land in a built-in inbox plus optional email + webhook fan-out.',
            'blocks' => [
                $this->richText('<p>The form builder lives at <a href="/admin/cms/forms"><code>/admin/cms/forms</code></a>. Two forms are seeded out of the box: <strong>contact</strong> (name + email + message) and <strong>newsletter</strong> (email only). The <code>contact_form</code> block on any CMS page references a form by its <code>form_slug</code>.</p>'),

                $this->richText('<h2>Building a form</h2><ol><li>Click <strong>New form</strong>.</li><li>Set the name and slug. The slug becomes part of the submit URL (<code>/marketing/forms/{slug}</code>).</li><li>Add fields one at a time. Each field has a <code>key</code> (becomes the JSON payload key), a <code>label</code>, a <code>type</code> (<code>text</code> / <code>email</code> / <code>tel</code> / <code>textarea</code> / <code>select</code> / <code>checkbox</code> / <code>number</code> / <code>url</code>), and a required flag.</li><li>(Optional) set <strong>Notify email</strong> to fan-out new submissions to your team.</li><li>(Optional) set <strong>Webhook URL</strong> to fire a JSON POST to your CRM / Slack / Zapier.</li></ol>'),

                $this->divider(),

                $this->richText('<h2>Submissions inbox</h2><p>From the form list, click <strong>N submissions</strong> on any form card to see every submission, sorted newest-first. Hit <strong>Export CSV</strong> for a flat dump with one row per submission.</p>'),

                $this->richText('<h2>Anti-spam</h2><p>Two defenses are wired by default:</p><ul><li><strong>Honeypot</strong>: a hidden <code>_honey</code> field on the public form. Any non-empty value silently drops the submission (200 OK, no row stored).</li><li><strong>Rate-limit</strong>: 20 requests/minute per IP on <code>/marketing/forms/{slug}</code>.</li></ul>'),

                $this->codeBlock(<<<'JS'
// Public form submit (the contact_form block does this for you):
await fetch('/marketing/forms/contact', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({
        name: 'Eagle',
        email: 'eagle@example.com',
        message: 'Hi there',
        _honey: '', // must stay empty
    }),
});
JS, 'javascript', 'POST /marketing/forms/{slug}'),

                $this->richText('<h2>Unknown form?</h2><p>If a block references a <code>form_slug</code> that doesn\'t exist or is inactive, the submit endpoint returns a clean JSON 404 ("Form [{slug}] does not exist or is inactive."). Create the form in admin to fix it.</p>'),
            ],
        ];
    }

    protected function newsletterGuide(): array
    {
        return [
            'title' => 'Newsletter',
            'summary' => 'Driver-pluggable subscribers — Database (default), Mailchimp, Resend Audiences, ConvertKit. Switch with one env var.',
            'blocks' => [
                $this->richText('<p>The newsletter system uses a <strong>driver registry</strong>: the active provider is configured via <code>CMS_NEWSLETTER_PROVIDER</code> in <code>.env</code>. Every driver also persists a local row in <code>cms_newsletter_subscribers</code>, so the admin inbox stays in sync with whatever ESP you forward to.</p>'),

                $this->richText('<h2>Providers</h2><ul><li><strong>Database</strong> (default) — stores locally only. Use this until you wire a real ESP, or for self-hosted newsletter workflows.</li><li><strong>Mailchimp</strong> — Marketing API v3, subscribes to a configured audience. Supports double-opt-in.</li><li><strong>Resend Audiences</strong> — Resend\'s contact API.</li><li><strong>ConvertKit</strong> — v3 API, subscribes via a configured form.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>Switching provider</h2><p>Set the env var and the matching credentials. The newsletter block and any contact-form newsletter signup automatically use the active driver.</p>'),

                $this->codeBlock(<<<'ENV'
# Database (default — no extra config)
CMS_NEWSLETTER_PROVIDER=database

# Mailchimp
CMS_NEWSLETTER_PROVIDER=mailchimp
MAILCHIMP_API_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us1
MAILCHIMP_AUDIENCE_ID=xxxxxxxxxx
MAILCHIMP_DOUBLE_OPT_IN=true

# Resend Audiences
CMS_NEWSLETTER_PROVIDER=resend
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxx
RESEND_AUDIENCE_ID=audience-uuid

# ConvertKit
CMS_NEWSLETTER_PROVIDER=convertkit
CONVERTKIT_API_KEY=xxxxxxxxxxxxxxxxxxxxxx
CONVERTKIT_FORM_ID=1234567
ENV, 'env', '.env'),

                $this->richText('<h2>Failure handling</h2><p>If you switch to (say) Mailchimp but forget the credentials, the driver silently falls back to the local Database provider — the lead is never lost. You\'ll see <code>provider=mailchimp</code> on the subscriber row with a "stored locally" message in the response.</p>'),

                $this->richText('<h2>Idempotency</h2><p>Re-subscribing the same email is always safe — it returns the existing row, restores it from "unsubscribed" if needed, and keeps the original confirmation timestamp.</p>'),
            ],
        ];
    }

    protected function redirectsGuide(): array
    {
        return [
            'title' => 'Redirects',
            'summary' => 'Map old paths to new ones with 301/302/307/308. Built-in 404 log lets you convert popular misses into redirects with one click.',
            'blocks' => [
                $this->richText('<p>The redirect manager lives at <a href="/admin/cms/redirects"><code>/admin/cms/redirects</code></a>. The <code>HandleRedirects</code> middleware runs <em>before route matching</em>, so unmatched paths (like <code>/old-marketing-url</code>) still issue a 30x rather than hitting a 404.</p>'),

                $this->richText('<h2>Adding a redirect</h2><ol><li>Click <strong>New redirect</strong>.</li><li>Enter the <strong>From path</strong> (must start with <code>/</code>).</li><li>Enter the <strong>To path</strong> — either a local path (<code>/new-url</code>) or an absolute URL (<code>https://example.com/somewhere</code>).</li><li>Pick a status code: <strong>301</strong> (permanent, default), <strong>302</strong>, <strong>307</strong>, or <strong>308</strong>.</li></ol><p>Each redirect tracks <code>hits</code> and <code>last_hit_at</code> so you can see which old URLs still get traffic.</p>'),

                $this->divider(),

                $this->richText('<h2>404 log</h2><p>Every GET request that doesn\'t match any route is logged to <code>cms_not_found_log</code>, with <code>path</code>, <code>hits</code>, <code>referer</code>, and <code>last_hit_at</code>. The 50 most recent entries appear at the bottom of the redirects page.</p>'),

                $this->richText('<h2>Convert 404 → redirect</h2><p>Click any entry in the 404 log to be prompted for a destination. The system creates the redirect, and future visitors get a clean 301 instead of a 404. The log row stays in place so you can see what got rescued.</p>'),

                $this->richText('<h2>Tip: bulk migration</h2><p>When you migrate from another stack, paste your old URL → new URL mapping CSV into the admin (one row per redirect). A CSV importer is a small follow-up; for now the rows are added one at a time via the dialog.</p>'),

                $this->ctaBanner(
                    title: 'Audit recent 404s',
                    body: 'Convert popular misses into redirects to recover lost inbound traffic.',
                    primary: ['Open redirects', '/admin/cms/redirects'],
                ),
            ],
        ];
    }

    protected function seoGuide(): array
    {
        return [
            'title' => 'SEO',
            'summary' => 'Per-page meta + site-wide defaults + auto sitemap + env-aware robots.txt. JSON-LD support out of the box.',
            'blocks' => [
                $this->richText('<p>Every public page goes through the <code>&lt;SeoMeta /&gt;</code> component, which composes <code>&lt;title&gt;</code>, meta description, canonical URL, Open Graph tags, Twitter card tags, robots directive, and (optionally) a JSON-LD blob. Per-page values override the global defaults.</p>'),

                $this->richText('<h2>Per-page settings</h2><p>On any page or blog post, open the <strong>SEO</strong> tab to set:</p><ul><li><strong>Meta title</strong> — defaults to the page title.</li><li><strong>Meta description</strong> — defaults to <code>seo_defaults.description</code>.</li><li><strong>noindex</strong> — hides this page from search engines (still publicly accessible).</li></ul>'),

                $this->divider(),

                $this->richText('<h2>Site-wide defaults</h2><p>Open <strong>Admin → CMS → Globals → SEO defaults</strong>. Set:</p><ul><li><strong>Site name</strong> — used in the title template + OG site_name.</li><li><strong>Title template</strong> — e.g. <code>{page} | {site}</code>. Tokens: <code>{page}</code>, <code>{site}</code>.</li><li><strong>Default meta description</strong> — used when a page leaves description blank.</li><li><strong>Default OG image</strong> — 1200×630 image URL used for social previews when a page doesn\'t set its own.</li><li><strong>Robots directive</strong> — default <code>index,follow</code> for unauthenticated pages.</li></ul>'),

                $this->richText('<h2>Sitemap</h2><p>Auto-generated at <a href="/sitemap.xml"><code>/sitemap.xml</code></a>. Includes every published CMS page (except <code>no_index</code>) plus static marketing routes. Search engines fetch this directly.</p>'),

                $this->richText('<h2>robots.txt</h2><p>Served at <a href="/robots.txt"><code>/robots.txt</code></a>. The body is environment-aware:</p><ul><li><strong>Production</strong>: allows marketing routes, blocks private scopes (<code>/admin</code>, <code>/t</code>, <code>/account</code>, <code>/checkout</code>, <code>/api</code>, <code>/webhooks</code>, <code>/preview</code>, <code>/settings</code>).</li><li><strong>Any non-production env</strong> (local, staging): <code>Disallow: /</code> — keeps staging copies out of the index.</li></ul>'),

                $this->divider(),

                $this->richText('<h2>JSON-LD</h2><p>Blog posts ship Article schema automatically. For other pages, pass a <code>schemaOrg</code> prop to <code>&lt;SeoMeta&gt;</code> with whatever shape you need (FAQPage, Product, LocalBusiness, BreadcrumbList, etc.). The component serializes it into a <code>&lt;script type="application/ld+json"&gt;</code> in the document head.</p>'),
            ],
        ];
    }

    protected function i18nGuide(): array
    {
        return [
            'title' => 'i18n & localization',
            'summary' => 'Author the same page in multiple locales. Visitor locale resolves from ?lang=, then cookie, then Accept-Language, with safe fallback.',
            'blocks' => [
                $this->richText('<p>Multi-locale content is built into <code>cms_pages</code>, <code>cms_blog_posts</code>, and the cookie / middleware layer. Configure supported locales in <code>config/cms.php</code> under <code>locales</code>.</p>'),

                $this->codeBlock(<<<'PHP'
'locales' => ['en', 'ar', 'fr', 'es', 'de'],
'default_locale' => 'en',
PHP, 'php', 'config/cms.php'),

                $this->divider(),

                $this->richText('<h2>Authoring a translation</h2><ol><li>Create the original page in English. Save.</li><li>Create a new page with the <strong>same slug</strong>. Set the locale dropdown to <code>ar</code> (Arabic) or whatever target. Author the translated content.</li></ol><p>The <code>(slug, locale)</code> pair is unique, so the same slug can live in every locale.</p>'),

                $this->richText('<h2>Visitor locale resolution</h2><p>The <code>SetCmsLocale</code> middleware (applied to public marketing routes only via the <code>cms.locale</code> alias) resolves the locale in this priority order:</p><ol><li><code>?lang=xx</code> query string. Persists to the <code>cms_locale</code> cookie for a year.</li><li>The <code>cms_locale</code> cookie.</li><li>The <code>Accept-Language</code> header (first match against supported list).</li><li><code>config(\'app.locale\')</code> fallback.</li></ol>'),

                $this->richText('<h2>Fallback when content is missing</h2><p>If a visitor requests an Arabic version of <code>/docs/cms-pages</code> but you haven\'t authored one yet, the controller falls back to the <code>default_locale</code> page so the visitor gets <em>some</em> content rather than a 404. You can override by checking <code>cmsGlobals.i18n.locale</code> in your React templates and showing an "untranslated" notice if you want.</p>'),

                $this->divider(),

                $this->richText('<h2>Private scopes keep their own locale</h2><p>The middleware is applied only to public marketing routes. Authenticated scopes (<code>/admin</code>, <code>/t/*</code>, <code>/settings</code>) use Laravel\'s app locale however you set it (e.g. per-user preference). This keeps the public-facing locale picker decoupled from in-app i18n.</p>'),
            ],
        ];
    }

    protected function versionsPreviewGuide(): array
    {
        return [
            'title' => 'Versions & preview',
            'summary' => 'Every page save snapshots to cms_page_versions. Restore any earlier version. Signed preview URLs let you share unpublished drafts safely.',
            'blocks' => [
                $this->richText('<p>Two safety nets ship with the editor: automatic version snapshots on every save, and time-boxed signed preview URLs for unpublished drafts.</p>'),

                $this->divider(),

                $this->richText('<h2>Versions</h2><p>Every successful save of a CMS page creates a row in <code>cms_page_versions</code> with a full snapshot of title, slug, locale, template, status, meta fields, and the entire block tree. Open the <strong>Versions</strong> link on the page edit screen to see the timeline.</p>'),

                $this->richText('<h2>Restoring</h2><p>Click <strong>Restore</strong> on any version. The current state is automatically snapshotted <em>first</em> (with note <code>auto: pre-restore from v{n}</code>), so restoring is reversible. A second version is then written representing the restored state.</p>'),

                $this->codeBlock(<<<'PHP'
// Programmatic snapshot (e.g. from a custom importer):
app(\App\Support\Cms\PageService::class)
    ->snapshot($page, $userId, 'imported from old CMS');
PHP, 'php', 'PageService::snapshot()'),

                $this->divider(),

                $this->richText('<h2>Signed preview URLs</h2><p>Use the <strong>Preview</strong> tab on the page editor to get a signed URL for the current page state — even if it\'s a Draft. The URL is valid for 30 minutes and includes a <code>signature</code> + <code>expires</code> query string. The middleware verifies the signature; tampered or expired links return 403.</p>'),

                $this->richText('<h2>Sharing previews safely</h2><p>The preview page is marked <code>no_index</code> and is excluded from <code>/sitemap.xml</code>. Share the signed URL with stakeholders, they can review without seeing the editor. When the link expires, they need a fresh one.</p>'),

                $this->codeBlock(<<<'PHP'
// Mint your own (e.g. send by email):
$url = \App\Http\Controllers\Marketing\PreviewController::signedUrlFor($page->id, 30);
PHP, 'php', 'Signed URL helper'),

                $this->richText('<h2>Scheduled publishing</h2><p>Set <strong>Publish at</strong> on a draft page to a future date+time. The <code>cms:publish-scheduled</code> artisan command (registered on the scheduler every minute) flips it to Published when the time rolls over. Set <strong>Unpublish at</strong> for the inverse — Published → Archived.</p>'),
            ],
        ];
    }

    protected function cacheGuide(): array
    {
        return [
            'title' => 'Cache & invalidation',
            'summary' => 'Stale-while-revalidate caching on every public read. Events fire on publish so downstream systems (CDN, sitemaps) can react.',
            'blocks' => [
                $this->richText('<p>Public CMS reads are cached via Laravel\'s <code>Cache::flexible([60, ttl])</code> — fresh for 60 seconds, then served <em>stale</em> while the background refresh recomputes. This keeps p99 latency low under traffic spikes and makes the marketing site survive sudden bursts cleanly.</p>'),

                $this->divider(),

                $this->richText('<h2>What\'s cached</h2><ul><li>Per-page bodies (<code>cms.page:{slug}:{locale}</code>) — 1 hour TTL.</li><li>Globals (<code>cms.global:{key}</code>) — 1 hour TTL.</li><li>Docs index list — 1 hour TTL.</li></ul><p>Configure TTLs in <code>config/cms.php</code> under <code>cache</code>.</p>'),

                $this->codeBlock(<<<'PHP'
'cache' => [
    'page_ttl' => 3600,
    'docs_index_ttl' => 3600,
    'globals_ttl' => 3600,
],
PHP, 'php', 'config/cms.php'),

                $this->richText('<h2>Invalidation</h2><p>Every save / publish / unpublish / restore on a page calls <code>PageService::bustCache</code>, which:</p><ol><li>Forgets every locale-suffixed key for the page slug.</li><li>Forgets the docs index list.</li><li>Dispatches the <code>App\Events\CmsContentPublished</code> event with the page model and a <strong>surrogate key</strong> (<code>cms-page:{slug}:{locale}</code>).</li></ol>'),

                $this->divider(),

                $this->richText('<h2>Hooking into the event</h2><p>Subscribe to <code>CmsContentPublished</code> from any listener — purge a Cloudflare zone by tag, ping a search index, warm the sitemap, post to Slack — whatever you need.</p>'),

                $this->codeBlock(<<<'PHP'
// In app/Providers/EventServiceProvider.php (or via a listener class):
Event::listen(CmsContentPublished::class, function ($event) {
    // $event->page  — the saved CmsPage
    // $event->tag() — surrogate key for CDN purges
    Cloudflare::purgeByTag($event->tag());
});
PHP, 'php', 'Custom listener'),

                $this->richText('<h2>Globals are busted on save too</h2><p><code>GlobalsService::save</code> forgets <code>cms.global:{key}</code> on every write, so brand / nav / footer changes take effect on the very next request — no manual cache flushing.</p>'),
            ],
        ];
    }

    /* -----------------------------------------------------------------
     * Block builder helpers — keep page authoring terse.
     * -----------------------------------------------------------------*/

    /**
     * @return array<string, mixed>
     */
    protected function richText(string $html): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'rich_text',
            'attrs' => ['html' => $html],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function codeBlock(string $code, string $language = 'bash', string $filename = ''): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'code',
            'attrs' => [
                'language' => $language,
                'code' => $code,
                'filename' => $filename,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function divider(): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'divider',
            'attrs' => ['style' => 'line'],
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $primary  [label, url]
     * @param  array{0: string, 1: string}|null  $secondary  [label, url]
     * @return array<string, mixed>
     */
    protected function ctaBanner(string $title, string $body, array $primary, ?array $secondary = null): array
    {
        return [
            'id' => $this->ulid(),
            'type' => 'cta_banner',
            'attrs' => [
                'title' => $title,
                'body' => $body,
                'primary_cta_label' => $primary[0],
                'primary_cta_url' => $primary[1],
                'secondary_cta_label' => $secondary[0] ?? '',
                'secondary_cta_url' => $secondary[1] ?? '',
                'background_media_id' => null,
            ],
        ];
    }

    /**
     * Lightweight Crockford-base32 ULID for block ids. Doesn't need to
     * be cryptographically strong — uniqueness within a page is enough.
     */
    protected function ulid(): string
    {
        return strtoupper(Str::ulid()->toBase32());
    }
}
