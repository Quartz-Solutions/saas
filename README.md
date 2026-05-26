# Laravel + React Starter Kit

## Introduction

Our React starter kit provides a robust, modern starting point for building Laravel applications with a React frontend using [Inertia](https://inertiajs.com).

Inertia allows you to build modern, single-page React applications using classic server-side routing and controllers. This lets you enjoy the frontend power of React combined with the incredible backend productivity of Laravel and lightning-fast Vite compilation.

This React starter kit utilizes React 19, TypeScript, Tailwind, and the [shadcn/ui](https://ui.shadcn.com) and [radix-ui](https://www.radix-ui.com) component libraries.

## Official Documentation

Documentation for all Laravel starter kits can be found on the [Laravel website](https://laravel.com/docs/starter-kits).

## Contributing

Thank you for considering contributing to our starter kit! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

All contributions to the Starter Kits from now on should be made through [Maestro](https://github.com/laravel/maestro).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## License

The Laravel + React starter kit is open-sourced software licensed under the MIT license.

---

# Deployment

The boilerplate ships with first-party deploy paths for four targets. Pick the one that matches your operational comfort level — they are listed easiest to most flexible.

| Target                       | Best for                                                  | TLS         | Managed PG/Redis |
| ---------------------------- | --------------------------------------------------------- | ----------- | ---------------- |
| Fly.io                       | Solo founders, edge deploys, fastest path to TLS-on-Apex  | Auto        | Fly Postgres add-on |
| Railway                      | Teams that want a Heroku-style dashboard + git push       | Auto        | Railway plugins  |
| DigitalOcean App Platform    | Predictable monthly bill, managed everything              | Auto        | DO managed DB    |
| Self-hosted Docker           | Full control, on-prem, EU sovereignty, single fat box     | nginx-proxy + acme | Containers in same compose |

Every target needs the same Laravel env vars at minimum:

```env
APP_KEY=                  # php artisan key:generate --show
APP_URL=https://yourdomain.com
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
REDIS_HOST=...
REDIS_PORT=6379
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=hello@yourdomain.com
# Optional but recommended:
SENTRY_DSN=               # https://sentry.io/... — env-gated, off when blank
BACKUP_BUCKET=            # s3://<bucket> for the daily pg_dump (off when blank)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
```

---

## Fly.io

Fly.io is the fastest way from `git clone` to a live HTTPS URL with Postgres attached.

### Prereqs

- Install [`flyctl`](https://fly.io/docs/hands-on/install-flyctl/) and `fly auth login`.
- A payment method on file (Fly's free tier covers small apps, but PG add-on costs ~$1.94/mo for a 1 GB volume).

### First deploy

```bash
fly launch --no-deploy
# - Pick a unique app name (becomes <app>.fly.dev).
# - Pick a region close to your users (lhr, fra, sin, syd, etc.).
# - Decline the offer to create a Postgres app for now (we want it managed by fly).
# - Decline Redis (we'll attach Upstash separately).
# - Answer "yes" to copy your existing Dockerfile if prompted; otherwise Fly
#   generates a default one — replace it with our prod Dockerfile (see below).

# Replace the Fly-generated Dockerfile with our production image:
cp docker/prod/Dockerfile Dockerfile

# Provision Postgres and attach (creates DATABASE_URL secret automatically):
fly postgres create --name my-saas-db --region lhr --vm-size shared-cpu-1x --volume-size 10
fly postgres attach --app my-saas my-saas-db

# Provision Redis via Upstash (Fly has a native integration):
fly redis create --name my-saas-redis --region lhr
# Copy the connection URL it prints; set REDIS_HOST/PORT/PASSWORD via secrets below.

# Set secrets. APP_KEY is critical — generate locally first:
fly secrets set \
  APP_KEY="base64:$(openssl rand -base64 32)" \
  APP_URL="https://my-saas.fly.dev" \
  APP_ENV=production \
  APP_DEBUG=false \
  SESSION_DRIVER=redis \
  CACHE_STORE=redis \
  QUEUE_CONNECTION=redis \
  REDIS_HOST=fly-my-saas-redis.upstash.io \
  REDIS_PORT=6379 \
  REDIS_PASSWORD=...        \
  MAIL_MAILER=smtp          \
  MAIL_HOST=smtp.postmarkapp.com \
  MAIL_PORT=587             \
  MAIL_USERNAME=...         \
  MAIL_PASSWORD=...         \
  MAIL_FROM_ADDRESS="hello@yourdomain.com"

# Deploy:
fly deploy

# After first deploy, run migrations + (optional) seed:
fly ssh console -C "php artisan migrate --force"
fly ssh console -C "php artisan storage:link"
```

### Gotchas

- **Asset build runs inside the Dockerfile** (`docker/prod/Dockerfile` already does `pnpm install && pnpm build`). Don't try to bake assets at runtime — Fly's filesystem is ephemeral.
- **Queue + scheduler need separate processes.** Add this to `fly.toml`:
  ```toml
  [processes]
  app = "/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf"
  queue = "php artisan queue:work --sleep=3 --tries=3 --max-time=3600"
  scheduler = "sh -c 'while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done'"
  ```
- **`DATABASE_URL` is set by `fly postgres attach`** but Laravel reads `DB_HOST/DB_DATABASE/...`. Either parse `DATABASE_URL` in `bootstrap/app.php`, or unset it and set the individual `DB_*` secrets explicitly.
- **Health check** — the default `/up` endpoint is what `docker-compose.prod.yml` and the Fly proxy both probe. Don't remove it.

---

## Railway

Railway is `git push` deploys with a polished dashboard. Postgres + Redis are first-party plugins.

### Prereqs

- A Railway account + GitHub connection.
- The repo pushed to GitHub (Railway pulls from git).

### First deploy

1. **Create a new Railway project** → "Deploy from GitHub repo" → pick this repo.
2. **Set the builder to Dockerfile** in the service's *Settings → Build* tab, with `docker/prod/Dockerfile` as the path. (Railway's Nixpacks auto-detection will guess wrong for Laravel + Vite — force Dockerfile.)
3. **Add Postgres**: project → "+ New" → "Database" → "PostgreSQL". Railway exposes `DATABASE_URL`, `PGHOST`, `PGUSER`, `PGPASSWORD`, `PGDATABASE`, `PGPORT` to the app service automatically via reference variables.
4. **Add Redis**: same flow. Reference vars: `REDISHOST`, `REDISPORT`, `REDISPASSWORD`.
5. **Set variables on the app service** (dashboard → Variables):

   ```
   APP_KEY=base64:...                   # generate locally with: php artisan key:generate --show
   APP_URL=https://my-saas.up.railway.app
   APP_ENV=production
   APP_DEBUG=false
   DB_CONNECTION=pgsql
   DB_HOST=${{Postgres.PGHOST}}
   DB_PORT=${{Postgres.PGPORT}}
   DB_DATABASE=${{Postgres.PGDATABASE}}
   DB_USERNAME=${{Postgres.PGUSER}}
   DB_PASSWORD=${{Postgres.PGPASSWORD}}
   REDIS_HOST=${{Redis.REDISHOST}}
   REDIS_PORT=${{Redis.REDISPORT}}
   REDIS_PASSWORD=${{Redis.REDISPASSWORD}}
   SESSION_DRIVER=redis
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   MAIL_MAILER=smtp
   MAIL_HOST=...
   MAIL_PORT=587
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=hello@yourdomain.com
   ```
6. **Add the queue + scheduler as separate services** in the same Railway project (reuse the same repo, same Dockerfile, override the start command):
   - Queue service start command: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
   - Scheduler service start command: `sh -c 'while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done'`
7. **Push to main** → Railway auto-deploys. After the first deploy, open a Railway shell on the app service:
   ```bash
   railway run --service app php artisan migrate --force
   railway run --service app php artisan storage:link
   ```

### Gotchas

- **TCP proxy public URL** is on `*.up.railway.app` by default; add a custom domain in Settings → Domains → "+ Custom Domain" and point a CNAME at the provided target.
- **`APP_URL` must match the public URL** or Inertia/Wayfinder will generate broken absolute URLs.
- **Sleep-on-idle plans** will cold-start your container — fine for a side project, set the service to "Always On" for production.
- **Redis password** — Railway's Redis plugin enables AUTH by default; the `phpredis` driver in this stack reads `REDIS_PASSWORD` automatically.

---

## DigitalOcean App Platform

DO App Platform gives you predictable per-component pricing and managed Postgres + Redis under one dashboard.

### Prereqs

- A DigitalOcean account.
- The repo pushed to GitHub or GitLab.

### App spec

Drop the following at `.do/app.yaml` and run `doctl apps create --spec .do/app.yaml` (install [`doctl`](https://docs.digitalocean.com/reference/doctl/how-to/install/) first), or paste it into the App Platform UI under "Create from app spec".

```yaml
name: my-saas
region: nyc

services:
  - name: web
    dockerfile_path: docker/prod/Dockerfile
    github:
      repo: your-org/my-saas
      branch: main
      deploy_on_push: true
    http_port: 80
    instance_size_slug: basic-xs
    instance_count: 1
    routes:
      - path: /
    health_check:
      http_path: /up
    envs:
      - { key: APP_ENV,         value: production }
      - { key: APP_DEBUG,       value: "false" }
      - { key: APP_KEY,         value: ${APP_KEY},         type: SECRET }
      - { key: APP_URL,         value: ${APP_URL} }
      - { key: SESSION_DRIVER,  value: redis }
      - { key: CACHE_STORE,     value: redis }
      - { key: QUEUE_CONNECTION, value: redis }
      - { key: DB_CONNECTION,   value: pgsql }
      - { key: DB_HOST,         value: ${db.HOSTNAME} }
      - { key: DB_PORT,         value: ${db.PORT} }
      - { key: DB_DATABASE,     value: ${db.DATABASE} }
      - { key: DB_USERNAME,     value: ${db.USERNAME} }
      - { key: DB_PASSWORD,     value: ${db.PASSWORD},     type: SECRET }
      - { key: REDIS_HOST,      value: ${redis.HOSTNAME} }
      - { key: REDIS_PORT,      value: ${redis.PORT} }
      - { key: REDIS_PASSWORD,  value: ${redis.PASSWORD},  type: SECRET }
      - { key: MAIL_MAILER,     value: smtp }
      - { key: MAIL_HOST,       value: smtp.postmarkapp.com }
      - { key: MAIL_PORT,       value: "587" }
      - { key: MAIL_USERNAME,   value: ${MAIL_USERNAME},   type: SECRET }
      - { key: MAIL_PASSWORD,   value: ${MAIL_PASSWORD},   type: SECRET }
      - { key: MAIL_FROM_ADDRESS, value: hello@yourdomain.com }

  - name: queue
    dockerfile_path: docker/prod/Dockerfile
    github: { repo: your-org/my-saas, branch: main }
    run_command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    instance_size_slug: basic-xxs
    envs: *web_envs        # YAML anchor — copy the web envs block here in practice

  - name: scheduler
    dockerfile_path: docker/prod/Dockerfile
    github: { repo: your-org/my-saas, branch: main }
    run_command: sh -c 'while true; do php artisan schedule:run --verbose --no-interaction; sleep 60; done'
    instance_size_slug: basic-xxs
    envs: *web_envs

databases:
  - name: db
    engine: PG
    version: "16"
    production: true
    cluster_name: my-saas-pg

  - name: redis
    engine: REDIS
    version: "7"
    production: true
    cluster_name: my-saas-redis

jobs:
  - name: migrate
    kind: PRE_DEPLOY
    dockerfile_path: docker/prod/Dockerfile
    github: { repo: your-org/my-saas, branch: main }
    run_command: php artisan migrate --force
    envs: *web_envs
```

### Build/run commands

- **Build** — handled inside the Dockerfile (`composer install --no-dev`, `pnpm install --frozen-lockfile`, `pnpm build`).
- **Run** — the Dockerfile's `CMD` starts supervisord (php-fpm + nginx). The `queue` and `scheduler` components override `run_command` to start their own processes.

### Env vars to set

Mark these as `SECRET` in the dashboard (or `type: SECRET` in the spec):

- `APP_KEY` (generate with `php artisan key:generate --show` once locally)
- `DB_PASSWORD`, `REDIS_PASSWORD` (DO auto-injects via `${db.PASSWORD}` / `${redis.PASSWORD}` references)
- `MAIL_USERNAME`, `MAIL_PASSWORD`
- `SENTRY_DSN` (optional)
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `BACKUP_BUCKET` (if you want the daily pg_dump to go to Spaces)

### Gotchas

- **Pre-deploy migration job is mandatory.** App Platform spins up new containers in parallel with the old ones; if you run migrations from the web container's startup, you'll race two app instances against one DB. Use the `PRE_DEPLOY` job above.
- **`instance_size_slug` matters.** `basic-xxs` is fine for queue/scheduler, but the web service needs at least `basic-xs` (1 GB) to render Inertia pages comfortably.
- **DO Postgres uses port 25060** with a forced TLS connection. Add `?sslmode=require` to any DSN you build manually; the boilerplate's `config/database.php` doesn't need changes because we pass discrete `DB_*` vars.

---

## Self-hosted Docker

For a single VPS (Hetzner, OVH, Linode) or your own metal. Uses the existing `docker-compose.prod.yml` plus an nginx-proxy + Let's Encrypt sidecar for TLS.

### Prereqs

- A box with Docker Engine 24+ and the Docker Compose v2 plugin.
- A domain pointed to the box's IP (A record).
- Ports 80 + 443 open in the firewall.

### Walk-through

1. **Clone the repo and prepare env.**
   ```bash
   git clone https://github.com/your-org/my-saas /opt/my-saas
   cd /opt/my-saas
   cp .env.production.example .env.production
   # Edit .env.production:
   #   APP_KEY=base64:$(openssl rand -base64 32)
   #   APP_URL=https://yourdomain.com
   #   DOMAIN=yourdomain.com
   #   DB_PASSWORD=<strong>
   #   MAIL_*=...
   #   SENTRY_DSN=...           # optional, env-gated
   #   BACKUP_BUCKET=...        # optional; falls back to storage/backups/
   ```
2. **Bring up the app stack.**
   ```bash
   docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
   docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan migrate --force
   docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan storage:link
   ```
   The compose file ships five services: `app` (nginx + php-fpm), `queue` (worker), `scheduler` (loop), `db` (Postgres 16), `redis` (Redis 7).

3. **Front the stack with nginx-proxy + acme-companion for TLS.** Run these in a separate compose project so it can also serve other apps on the same box.
   ```bash
   mkdir -p /opt/proxy && cd /opt/proxy
   cat > docker-compose.yml <<'YAML'
   services:
     nginx-proxy:
       image: nginxproxy/nginx-proxy:latest
       restart: always
       ports: ["80:80", "443:443"]
       volumes:
         - certs:/etc/nginx/certs
         - vhost:/etc/nginx/vhost.d
         - html:/usr/share/nginx/html
         - /var/run/docker.sock:/tmp/docker.sock:ro
       networks: [proxy]

     acme:
       image: nginxproxy/acme-companion:latest
       restart: always
       environment:
         DEFAULT_EMAIL: ops@yourdomain.com
       volumes_from: [nginx-proxy]
       volumes:
         - acme:/etc/acme.sh
         - /var/run/docker.sock:/var/run/docker.sock:ro
       networks: [proxy]

   volumes: { certs: {}, vhost: {}, html: {}, acme: {} }
   networks:
     proxy:
       name: proxy
   YAML
   docker compose up -d
   ```
4. **Wire the app into the proxy network.** Edit `/opt/my-saas/docker-compose.prod.yml` and add to the `app` service:
   ```yaml
   environment:
     VIRTUAL_HOST: ${DOMAIN}
     VIRTUAL_PORT: "80"
     LETSENCRYPT_HOST: ${DOMAIN}
     LETSENCRYPT_EMAIL: ops@yourdomain.com
   networks: [default, proxy]

   networks:
     proxy:
       external: true
   ```
   Then recreate just the app container: `docker compose --env-file .env.production -f docker-compose.prod.yml up -d --force-recreate app`. acme-companion will issue the cert automatically on first request.

5. **Set up the daily backup cron** (the Laravel scheduler already schedules `docker/scripts/backup-db.sh` once a day, but the scheduler container has to be running for it to fire). Verify with:
   ```bash
   docker compose --env-file .env.production -f docker-compose.prod.yml exec app php artisan schedule:list
   # should show:  0 0 * * *  /var/www/html/docker/scripts/backup-db.sh
   ```
   If `BACKUP_BUCKET` is unset in `.env.production`, dumps land at `storage/backups/$(date +%F).sql.gz` inside the `storage_logs`/`storage_public` volumes — rotate them yourself, or set `BACKUP_BUCKET=my-saas-backups` + AWS creds for S3 push. If you'd rather drive backups from the host (e.g. backup-during-maintenance windows), add this to root's crontab:
   ```cron
   0 3 * * *  docker compose --env-file /opt/my-saas/.env.production -f /opt/my-saas/docker-compose.prod.yml exec -T app bash docker/scripts/backup-db.sh >> /var/log/my-saas-backup.log 2>&1
   ```

### Env vars to set

Everything in `.env.production.example` is required. Additionally for self-hosted:

- `DOMAIN` — the host nginx-proxy matches on (also referenced by `LETSENCRYPT_HOST`).
- `BACKUP_BUCKET` — optional; if set the daily backup uploads to `s3://<bucket>/db/`. Leave blank for local backups.
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` — required when `BACKUP_BUCKET` is set. Works with AWS S3, Cloudflare R2, DO Spaces, MinIO (set `AWS_ENDPOINT` too for non-AWS).
- `SENTRY_DSN` — optional; when set, exceptions auto-report (see `bootstrap/app.php`).

### Gotchas

- **`--env-file .env.production` is mandatory.** Without it, Compose substitutes `${DB_PASSWORD}` from `.env` (or empty), and Postgres comes up with the wrong creds. There is no hardcoded `environment:` block on `app` for a reason — `.env.production` is the single source of truth.
- **Vite assets are baked into the prod image** by `docker/prod/Dockerfile`'s `pnpm build` step. After changing frontend code: `docker compose --env-file .env.production -f docker-compose.prod.yml build --no-cache app` to rebake.
- **`storage_public` and `storage_logs` are named volumes.** They survive `docker compose down`, but if you `docker volume rm` them you lose uploaded media and logs — back them up alongside the DB.
- **TLS first request is slow** (~30s) the very first time acme-companion fetches a cert from Let's Encrypt. Subsequent requests are instant.
- **Health endpoint** — `/up` is what `docker-compose.prod.yml`'s healthcheck probes; if you wrap the app behind nginx-proxy, the healthcheck still talks to `http://127.0.0.1/up` inside the container, so it keeps working.
