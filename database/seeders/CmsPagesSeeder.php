<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * Seeds three placeholder docs pages so /docs has content out of the box.
 * Replace the body_markdown with real content per-project — these are
 * deliberately short so they don't bloat the boilerplate.
 */
class CmsPagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $attrs) {
            CmsPage::query()->updateOrCreate(
                ['slug' => $slug],
                $attrs + [
                    'locale' => 'en',
                    'status' => CmsPage::STATUS_PUBLISHED,
                    'template' => CmsPage::TEMPLATE_DOCS,
                    'published_at' => now()->subDay(),
                    'no_index' => false,
                ],
            );
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function pages(): array
    {
        return [
            'getting-started' => [
                'title' => 'Getting started',
                'meta_title' => 'Getting started — Documentation',
                'meta_description' => 'Spin up the boilerplate locally with Docker, run the dev server, and sign in.',
                'body_markdown' => $this->gettingStartedBody(),
                'body_html' => $this->gettingStartedHtml(),
            ],
            'deployment' => [
                'title' => 'Deployment',
                'meta_title' => 'Deployment — Documentation',
                'meta_description' => 'Ship the boilerplate to production with the included Docker stack.',
                'body_markdown' => $this->deploymentBody(),
                'body_html' => $this->deploymentHtml(),
            ],
            'api-reference' => [
                'title' => 'API reference',
                'meta_title' => 'API reference — Documentation',
                'meta_description' => 'Sanctum-authenticated REST API at /api/v1. Auto-generated reference at /api-docs.',
                'body_markdown' => $this->apiBody(),
                'body_html' => $this->apiHtml(),
            ],
        ];
    }

    protected function gettingStartedBody(): string
    {
        return <<<'MD'
# Getting started

This boilerplate runs entirely inside Docker. Host PHP/Node aren't required.

## Prerequisites
- Docker Desktop (or compatible)
- A modern browser

## Boot the stack
```bash
git clone <your-fork> my-saas
cd my-saas
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec -d app pnpm dev
```

The app is now at http://localhost:8080.

## Default test accounts
After `db:seed` runs the included `DemoSeeder`, you can sign in with:

- **owner@acme.test / password** — tenant owner
- **admin@acme.test / password** — tenant admin
- **superadmin@example.test / password** — global Super Admin (the `/admin` scope)

Replace this page's content with your project's real onboarding instructions.
MD;
    }

    protected function gettingStartedHtml(): string
    {
        return <<<'HTML'
<h1>Getting started</h1>
<p>This boilerplate runs entirely inside Docker. Host PHP/Node aren't required.</p>
<h2>Prerequisites</h2>
<ul>
  <li>Docker Desktop (or compatible)</li>
  <li>A modern browser</li>
</ul>
<h2>Boot the stack</h2>
<pre><code class="language-bash">git clone &lt;your-fork&gt; my-saas
cd my-saas
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec -d app pnpm dev
</code></pre>
<p>The app is now at <a href="http://localhost:8080">http://localhost:8080</a>.</p>
<h2>Default test accounts</h2>
<p>After <code>db:seed</code> runs the included <code>DemoSeeder</code>, you can sign in with:</p>
<ul>
  <li><strong>owner@acme.test / password</strong> — tenant owner</li>
  <li><strong>admin@acme.test / password</strong> — tenant admin</li>
  <li><strong>superadmin@example.test / password</strong> — global Super Admin (the <code>/admin</code> scope)</li>
</ul>
<p>Replace this page's content with your project's real onboarding instructions.</p>
HTML;
    }

    protected function deploymentBody(): string
    {
        return <<<'MD'
# Deployment

A production-shaped Docker Compose file ships with the boilerplate.

## Production stack
```bash
cp .env.production.example .env.production
# Edit APP_KEY, DB_PASSWORD, MAIL_*, DOMAIN
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
```

The prod stack runs PHP-FPM + nginx + Postgres + Redis under a single Docker network. Per-tenant cache and queue use Redis.

## After deploy
- Run migrations: `docker compose exec app php artisan migrate --force`
- Cache config: `php artisan config:cache && route:cache && view:cache`
- Configure webhooks at each gateway dashboard pointing at `https://{your-domain}/webhooks/{gateway}`

## Hosting notes
- Fly.io, Railway, DigitalOcean App Platform — all work with the provided Dockerfile
- TLS — terminate at your reverse proxy or use Let's Encrypt + nginx-proxy

Replace this with your project's real deploy runbook.
MD;
    }

    protected function deploymentHtml(): string
    {
        return <<<'HTML'
<h1>Deployment</h1>
<p>A production-shaped Docker Compose file ships with the boilerplate.</p>
<h2>Production stack</h2>
<pre><code class="language-bash">cp .env.production.example .env.production
# Edit APP_KEY, DB_PASSWORD, MAIL_*, DOMAIN
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
</code></pre>
<p>The prod stack runs PHP-FPM + nginx + Postgres + Redis under a single Docker network. Per-tenant cache and queue use Redis.</p>
<h2>After deploy</h2>
<ul>
  <li>Run migrations: <code>docker compose exec app php artisan migrate --force</code></li>
  <li>Cache config: <code>php artisan config:cache &amp;&amp; route:cache &amp;&amp; view:cache</code></li>
  <li>Configure webhooks at each gateway dashboard pointing at <code>https://{your-domain}/webhooks/{gateway}</code></li>
</ul>
<h2>Hosting notes</h2>
<ul>
  <li>Fly.io, Railway, DigitalOcean App Platform — all work with the provided Dockerfile</li>
  <li>TLS — terminate at your reverse proxy or use Let's Encrypt + nginx-proxy</li>
</ul>
<p>Replace this with your project's real deploy runbook.</p>
HTML;
    }

    protected function apiBody(): string
    {
        return <<<'MD'
# API reference

The boilerplate ships a Sanctum-authenticated REST API at `/api/v1`.

## Authentication
Each user creates personal access tokens at `/settings/api-tokens` and picks abilities per token (read-only vs read-write, per-resource scopes).

Use the token as a Bearer header:

```bash
curl -H "Authorization: Bearer $TOKEN" https://yoursaas.com/api/v1/me
```

## Auto-generated reference
The full OpenAPI-style reference lives at **/api-docs** (generated by Scribe from controller docblocks). It's the source of truth — this page is just an overview.

## Rate limiting
Per-token: 60 requests/minute by default. Configurable in `config/api-abilities.php`.

## Outbound webhooks
Tenants register their own webhook URLs at `/t/{slug}/settings/webhooks`. Events fired: `tenant.member.invited`, `subscription.updated`, `payment.succeeded`. Payloads are signed with `X-Webhook-Signature` (HMAC-SHA256 of the body using the endpoint's signing secret).
MD;
    }

    protected function apiHtml(): string
    {
        return <<<'HTML'
<h1>API reference</h1>
<p>The boilerplate ships a Sanctum-authenticated REST API at <code>/api/v1</code>.</p>
<h2>Authentication</h2>
<p>Each user creates personal access tokens at <code>/settings/api-tokens</code> and picks abilities per token (read-only vs read-write, per-resource scopes).</p>
<p>Use the token as a Bearer header:</p>
<pre><code class="language-bash">curl -H "Authorization: Bearer $TOKEN" https://yoursaas.com/api/v1/me
</code></pre>
<h2>Auto-generated reference</h2>
<p>The full OpenAPI-style reference lives at <strong>/api-docs</strong> (generated by Scribe from controller docblocks). It's the source of truth — this page is just an overview.</p>
<h2>Rate limiting</h2>
<p>Per-token: 60 requests/minute by default. Configurable in <code>config/api-abilities.php</code>.</p>
<h2>Outbound webhooks</h2>
<p>Tenants register their own webhook URLs at <code>/t/{slug}/settings/webhooks</code>. Events fired: <code>tenant.member.invited</code>, <code>subscription.updated</code>, <code>payment.succeeded</code>. Payloads are signed with <code>X-Webhook-Signature</code> (HMAC-SHA256 of the body using the endpoint's signing secret).</p>
HTML;
    }
}
