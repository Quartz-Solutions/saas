# Custom Themes — Build Plan

> **Status:** Plan only. Today the app has a single, build-time theme baked
> into `resources/css/app.css` (`:root` + `.dark` oklch variables) plus a
> light/dark toggle. This document specifies a `themes` system: a DB-owned
> catalog of themes (colors, radius, fonts, custom CSS), a super-admin CRUD
> to create/edit/activate them, dynamic upload of CSS and Google-Fonts ZIP
> archives to storage, and runtime injection of the active theme into the
> DOM **without a front-end rebuild**.

---

## 1. What exists today

- **Tokens** live in `resources/css/app.css`:
  - `@theme { --font-sans: 'Instrument Sans', … }` (Tailwind 4 theme tokens).
  - `:root { --background … --primary … --chart-1..5 … --radius … --sidebar-* }`
    (light) and `.dark { … }` (dark). ~30 oklch variables × 2 modes.
  - shadcn/ui components consume `var(--primary)` etc. Recharts uses
    `var(--chart-1..5)` (see the new admin dashboard).
- **Light/dark** is a cookie (`appearance`) shared by `HandleAppearance`
  middleware (`View::share('appearance', …)`), an inline FOUC script in
  `resources/views/app.blade.php`, and the `use-appearance` hook that
  toggles the `.dark` class on `<html>`. **This stays unchanged** — a theme
  defines *both* a light and dark token map; the toggle still flips between
  them.
- **Fonts** are loaded from bunny.net via a `<link>` in `app.blade.php`
  (`Instrument Sans`). `--font-sans` (a Tailwind `@theme` token) is the
  family used by `body`/`font-sans`.
- **CMS brand globals** already store `brand_color`, `accent_color`,
  `font_heading`, `font_body` (`config/cms.php` → `brand` group) but those
  are marketing-site strings, not wired into the app shell tokens.
- **Uploads**: `ImageProcessor` + `Cms\MediaService` store to the `public`
  disk (`storage/app/public/…`, served at `/storage/…` via `storage:link`),
  random-hash filenames, URL via `Storage::disk('public')->url($path)`.
- **Settings → runtime config**: `AppSettingsService` caches overrides
  (`app_settings:overrides`, 24h) and `applyOverrides()` writes
  `Config::set(...)` on each request. Same caching shape will be mirrored
  for the active theme.
- **ZIP**: native `ZipArchive` is available (used by
  `app/Jobs/GenerateDataExport.php`). No third-party archive lib.
- **Admin CRUD pattern**: `routes/admin.php` (`['auth','verified',
  'admin.scope','role:Super Admin']`), `Admin/*Controller` +
  `Admin/*Request` FormRequests + Inertia pages under
  `resources/js/pages/admin/*` (DataTable + dialogs + Wayfinder
  `Controller.store.form()`), nav added in `app-sidebar.tsx`
  (`isSuperAdmin` → `Admin.children[]`).

---

## 2. Goals + non-goals

### Goals
- A `themes` table seeded with the built-in **Default** theme (mirrors the
  current `app.css` tokens, marked active) plus **3 presets** derived from
  the supplied screenshots (Emerald / Indigo / Midnight).
- Super-admin can **create / edit / clone / activate / delete** themes.
- A theme owns: light + dark color tokens, radius, a chosen font family,
  uploaded font files, and an optional custom CSS file.
- Activating a theme **swaps the live look with no rebuild** — tokens are
  compiled to a CSS file in storage and linked in `<head>`, overriding the
  build-time defaults via the cascade.
- **Upload + edit a CSS file** from the admin UI (file upload or inline
  editor); it is appended to the compiled output for advanced overrides.
- **Upload a Google-Fonts ZIP**, extract the font files safely, list the
  discovered families, and pick the theme's default family → emits
  `@font-face` + overrides `--font-sans`.

### Non-goals (deferred)
- **Per-tenant / white-label themes.** v1 ships one platform-global active
  theme. The resolution seam (`ThemeService::active()`) is built so a
  per-tenant override can be layered later without touching the injection
  path. (§13.1)
- **Live theme marketplace / sharing between installs.** Import/export a
  theme as JSON is a Phase-5 nice-to-have, not a marketplace.
- **Arbitrary per-component CSS editor / visual page builder.** Custom CSS
  is a single escape-hatch file, not a component-level editor.
- **Replacing Tailwind tokens with a different design system.** Themes
  re-value the *existing* token set; they don't add new tokens.

---

## 3. Data model

Two new tables (new timestamped migrations; never edit existing ones).

### 3.1 `themes`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(120) | "Emerald", "Midnight", … |
| `slug` | string(140) unique | url-safe |
| `description` | text nullable | |
| `is_active` | boolean default false | exactly one true — enforced in `ThemeService` |
| `is_preset` | boolean default false | seeded presets: clone-only, not deletable |
| `mode_hint` | string(10) default 'both' | `light` / `dark` / `both` — UI hint for which map a preset is designed around |
| `tokens` | jsonb | `{ "light": {"--primary":"oklch(…)", …}, "dark": {…} }` |
| `radius` | string(16) default '0.625rem' | mirrors `--radius` |
| `font_family` | string(120) nullable | chosen default family name (overrides `--font-sans`) |
| `custom_css_path` | string nullable | storage path of the uploaded/edited CSS |
| `compiled_css_path` | string nullable | cached compiled artifact (tokens+fonts+custom) |
| `compiled_at` | timestamp nullable | |
| `preview_image_path` | string nullable | optional thumbnail for the gallery |
| `created_by_id` | FK users nullable (nullOnDelete) | |
| `timestamps` | | |
| `softDeletes` | | user themes recoverable; presets never deleted |

Model `App\Models\Theme` — `#[Fillable([...])]`, casts `tokens => array`,
`is_active/is_preset => boolean`, `compiled_at => datetime`. Observed by
`AuditObserver` (mirror `AppSettingsService`/Plan auditing) with a small
`$auditableFields` list (`name`, `is_active`, `font_family`).

### 3.2 `theme_fonts`

One row per uploaded font face (a Google-Fonts ZIP yields many).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `theme_id` | FK themes cascadeOnDelete | |
| `family` | string(120) | parsed from filename, e.g. "Roboto" |
| `weight` | string(16) default '400' | `100..900` / `normal`/`bold` |
| `style` | string(16) default 'normal' | `normal` / `italic` |
| `format` | string(16) | `woff2` / `woff` / `ttf` / `otf` |
| `path` | string | storage path under `themes/{id}/fonts/…` |
| `original_filename` | string | |
| `size_bytes` | unsignedBigInteger | |
| `timestamps` | | |

`theme.font_family` references one of the `family` values (or a built-in
system stack). Storing per-face lets the editor list + delete individual
faces and build a complete `@font-face` block.

---

## 4. How a theme reaches the browser (the core)

**Approach: compile-to-file + `<link>`** (recommended over inline `<style>`).

On create/edit/activate, `ThemeService::compile($theme)` writes a single
stylesheet to the **public** disk and stores its path on
`themes.compiled_css_path`:

```
storage/app/public/themes/{id}/compiled-{sha8}.css
→ served at /storage/themes/{id}/compiled-{sha8}.css
```

The compiled file contains, in order:

```css
/* 1. @font-face for every theme_fonts row (self-hosted, /storage URLs) */
@font-face { font-family:'Roboto'; font-weight:400; font-style:normal;
  src:url('/storage/themes/12/fonts/roboto-400.woff2') format('woff2'); font-display:swap; }
/* … */

/* 2. token overrides — same selectors as app.css so the cascade wins */
:root { --background: …; --primary: …; --radius: …; --sidebar: …;
        --chart-1: …; /* …all tokens from tokens.light… */
        --font-sans: 'Roboto', ui-sans-serif, system-ui, sans-serif; }
.dark { /* …all tokens from tokens.dark… */ }

/* 3. the theme's custom CSS file, verbatim, last (highest priority) */
```

`resources/views/app.blade.php` links it **after** the Vite-built
`app.css` so its `:root`/`.dark` rules override the build-time defaults
(equal specificity → later wins). No rebuild, no inline style:

```blade
@vite([... 'resources/css/app.css', ...])
@if ($activeThemeCss)
    <link rel="stylesheet" href="{{ $activeThemeCss }}">
@endif
```

`$activeThemeCss` (the compiled URL, hash-busted) is shared to the blade by
a tiny middleware `InjectActiveTheme` (sibling to `HandleAppearance`) or by
extending `HandleAppearance` itself: `View::share('activeThemeCss',
app(ThemeService::class)->activeCssUrl())`. Resolution is cached
(`theme.active` key) and invalidated on any theme mutation — identical to
`AppSettingsService`'s cache discipline.

Why this beats alternatives:
- **No rebuild** — colors/fonts change instantly on activate.
- **CSP-friendly** — a same-origin `<link>`, not an inline `<style>` (no
  `unsafe-inline` / nonce needed). The existing inline FOUC script that
  sets `.dark` early is untouched and still applies before paint.
- **Cacheable** — hashed filename, long cache headers; busts on recompile.
- **Light/dark intact** — one file carries both maps; the `.dark` toggle
  keeps working.

> Inline-`<style>` injection is the fallback if storage isn't writable
> (e.g. read-only FS); documented but not the default.

---

## 5. `ThemeService` — the single seam

`App\Support\Theme\ThemeService` (per CLAUDE.md service-layer rule; all
theme writes route through it — no direct `Theme::create` in controllers):

- `active(): Theme` — the one `is_active` theme, cached (`theme.active`).
- `activeCssUrl(): ?string` — compiled URL for the blade.
- `create(array $attrs, ?User $by): Theme`
- `update(Theme $theme, array $attrs): Theme`
- `activate(Theme $theme): Theme` — flips `is_active` (one-active invariant
  in a transaction), recompiles, invalidates cache.
- `clone(Theme $theme): Theme` — duplicates tokens/fonts/custom CSS into a
  new editable (non-preset) theme.
- `delete(Theme $theme): void` — refuses if `is_preset` or `is_active`.
- `compile(Theme $theme): string` — writes the compiled CSS artifact
  (§4), returns path; called on every mutation.
- `storeCustomCss(Theme $theme, string|UploadedFile $css): void`
- `importFontZip(Theme $theme, UploadedFile $zip): Collection` — §8.
- `tokenSchema(): array` — the canonical list of editable tokens + groups
  (drives the editor UI; see §6).
- `invalidate(): void` — `Cache::forget('theme.active')`.

Caching mirrors `AppSettingsService` (`Cache::remember('theme.active', …)`,
forget on write).

---

## 6. Token model — what's editable

The token set = the variables already in `app.css`. The editor groups them
so the UI isn't 60 raw fields:

| Group | Tokens |
|---|---|
| **Brand** | `--primary`, `--primary-foreground`, `--accent`, `--accent-foreground`, `--ring` |
| **Surfaces** | `--background`, `--foreground`, `--card`, `--card-foreground`, `--popover*`, `--muted*`, `--secondary*`, `--border`, `--input` |
| **Sidebar** | `--sidebar`, `--sidebar-foreground`, `--sidebar-primary*`, `--sidebar-accent*`, `--sidebar-border`, `--sidebar-ring` |
| **Charts** | `--chart-1` … `--chart-5` |
| **Status** | `--destructive`, `--destructive-foreground` |
| **Shape** | `--radius` (range slider 0–1rem) |
| **Type** | `font_family` (picker, §8) |

Each color token is edited per **light** and **dark** map. The UI offers a
color picker that writes **oklch** (the project's format) — accept hex input
and convert, since designers think in hex (the screenshots' greens/purples).
`tokenSchema()` defines key, label, group, and whether it's a color vs
length so the form renders generically. Tokens absent from a theme fall
back to the build-time `app.css` value (the compiled file only emits what's
set), so a theme can override just the brand and inherit the rest.

---

## 7. Custom CSS upload / edit

- **Upload**: a `.css` file → stored at
  `storage/app/public/themes/{id}/custom-{sha8}.css`; path on
  `themes.custom_css_path`.
- **Edit inline**: a code textarea (lightweight; Monaco optional later) that
  POSTs raw CSS; `ThemeService::storeCustomCss` writes the same file.
- **Validation**: extension/MIME `.css` (`text/css`), size cap (e.g. 256 KB),
  UTF-8. Because only **Super Admins** (trusted) can reach this, deep CSS
  sanitization is out of scope, but the plan notes the powerful surface and
  recommends: strip remote `@import url(http…)` (keep first-party), and that
  the compiled file is served same-origin so it can't smuggle cross-origin
  styles past CSP. Appended **last** in the compiled output (§4) so it wins.

---

## 8. Google-Fonts ZIP upload pipeline

User downloads a family from fonts.google.com (a ZIP: `static/*.ttf`,
variable `*.ttf`, `OFL.txt`, `README`). Flow:

1. **Upload** the ZIP via the theme editor (`POST
   /admin/themes/{theme}/fonts`, `multipart/form-data`, field `archive`).
   Validate: mime `application/zip`, size cap (e.g. 20 MB).
2. **Extract safely** with native `ZipArchive` into a temp dir:
   - **Zip-slip guard**: reject any entry whose normalized path escapes the
     target (contains `..`, leading `/`, or resolves outside temp).
   - **Extension whitelist**: only `.woff2`, `.woff`, `.ttf`, `.otf` are
     extracted; everything else (license, readme, images) is ignored.
   - **Caps**: max entries (e.g. 200), max per-file size, max total
     uncompressed size (zip-bomb guard).
3. **Parse** each font filename for `family`, `weight`, `style` using the
   Google-Fonts convention (`Roboto-BoldItalic.ttf` → Roboto / 700 /
   italic; variable `Roboto[wght].ttf` → Roboto / `100 900` / normal).
   Where ambiguous, default 400/normal and let the admin correct it.
4. **Store** each font on the public disk under
   `themes/{id}/fonts/{hash}.{ext}` and insert a `theme_fonts` row.
5. The editor lists discovered families; the admin **picks the default
   family** → `themes.font_family`.
6. `compile()` emits one `@font-face` per `theme_fonts` row and sets
   `--font-sans: '{font_family}', <system fallback stack>` in `:root`
   (overriding the `@theme` default at runtime).

`woff2` is preferred at serve time; if a family only ships `.ttf`, that's
used as-is (acceptable; note a future optimization to transcode to woff2).

---

## 9. Admin UI

New admin scope `/admin/themes` (Super-Admin gated), added to
`app-sidebar.tsx` under `Admin.children` with a `Palette` lucide icon.

- **`GET /admin/themes`** — gallery grid of theme cards: name, a swatch
  strip (primary/accent/bg/sidebar previews from tokens), `Active` badge,
  preset tag, and a dropdown (Activate / Edit / Clone / Delete). Reuses the
  card-grid look from the new dashboard rather than a dense DataTable.
- **`GET /admin/themes/{theme}/edit`** (and `create`) — tabbed editor:
  - **Colors** tab — per-group token pickers (§6) with a Light/Dark
    sub-toggle; a live preview pane (a miniature dashboard/sidebar mock)
    that applies the edited tokens via a scoped inline `style` so the admin
    sees changes before saving.
  - **Typography** tab — upload Google-Fonts ZIP, list `theme_fonts`,
    delete individual faces, pick default family.
  - **Custom CSS** tab — file upload + inline editor.
  - **Shape** — radius slider.
- **Mutations** via Wayfinder Form actions
  (`ThemesController.store.form()`, `.update.form()`), `FormRequest`
  validation, sonner toast on success (existing patterns).
- **Activate** = `POST /admin/themes/{theme}/activate`. **Clone** = `POST
  …/clone`. Presets render Activate + Clone but disable Delete/destructive
  edits (clone-to-edit).

Routes (REST + custom POSTs), mirroring `routes/admin.php` plans:

```
Route::resource('themes', ThemesController::class)->except('show');
Route::post('themes/{theme}/activate', [ThemesController::class,'activate']);
Route::post('themes/{theme}/clone',    [ThemesController::class,'clone']);
Route::post('themes/{theme}/fonts',    [ThemeFontsController::class,'store']);
Route::delete('themes/{theme}/fonts/{font}', [ThemeFontsController::class,'destroy']);
Route::put('themes/{theme}/custom-css',[ThemesController::class,'updateCss']);
```

FormRequests under `app/Http/Requests/Admin/Themes/` (Store/Update/
FontUpload/CssUpdate), all extending the existing `AdminFormRequest`.

---

## 10. Seeded themes (`ThemesSeeder`)

Idempotent (`updateOrCreate` by slug). Seeds **4** themes; **Default** is
`is_active = true`. All four are `is_preset = true` (clone-to-edit).

> Assumption: "default = current theme" + "3 from the screenshots" → 4
> seeded. If you want only 3 total, drop one preset (see §14.5).

1. **Default (Quartz)** — `tokens` copied verbatim from the current
   `app.css` `:root`/`.dark` (neutral grayscale, `--primary` near-black).
   `font_family` = Instrument Sans. Active.
2. **Emerald** (screenshot 1 — "Donezo"): light, green brand.
   - light `--primary` ≈ `oklch(0.52 0.13 152)` (emerald-700), white
     surfaces, light sidebar, `--accent` soft green, charts green ramp.
   - dark map = a dark-green-accented variant.
3. **Indigo** (screenshot 2 — "Nexus"): light, indigo/violet brand.
   - `--primary` ≈ `oklch(0.55 0.20 280)` (indigo-600), white surfaces,
     charts blue→violet→teal ramp (matches the Sankey/bars).
4. **Midnight** (screenshot 3 — "Maillix"): dark-first, violet accent.
   - dark map: near-black navy `--background`/`--sidebar`
     (`oklch(0.18 0.02 280)`), `--primary` violet `oklch(0.62 0.22 300)`,
     light foreground. `mode_hint = 'dark'`.
   - light map: a clean light variant with the same violet brand.

Exact oklch values are tuned during implementation against the screenshots;
the seeder is the source of truth. Seeder compiles each theme so
`compiled_css_path` is populated immediately; the active one is linkable on
first request.

Wire-up: `ThemesSeeder` runs on every install so the app always has an
active theme — add it to `DatabaseSeeder::run()` (lean, deterministic, 4
rows). The `Default` theme guarantees the app renders identically to today
out of the box. (`storage:link` is already part of setup.)

---

## 11. Phasing

| Phase | Scope | Done when |
|---|---|---|
| **A — Foundations** | migrations, `Theme`/`ThemeFont` models + factories, `ThemeService` (resolve/compile/cache), `InjectActiveTheme` share + `app.blade.php` `<link>`, `ThemesSeeder` (Default + 3 presets). | Activating a theme via tinker swaps the live look with no rebuild; light/dark both correct; Default renders identically to today. |
| **B — Admin CRUD** | `ThemesController` + FormRequests + routes + sidebar entry; gallery + create/edit (Colors + Shape tabs) with color pickers and live preview; activate/clone/delete with preset guards. | Super-admin creates, edits brand colors (light+dark), activates, clones a preset, deletes a user theme — all from `/admin/themes`. |
| **C — Custom CSS** | upload + inline editor + validation; appended to compiled output. | Admin uploads/edits a CSS file; it shows in the compiled artifact and overrides tokens. |
| **D — Fonts** | ZIP upload endpoint, safe extraction, `theme_fonts`, family parsing, family picker, `@font-face` + `--font-sans` emit. | Admin uploads a Google-Fonts ZIP, picks a family, and the app renders in that font. Traversal/non-font entries rejected. |
| **E — Polish** | preview thumbnails, JSON import/export, oklch⇄hex helpers, contrast warnings; per-tenant override hook (deferred). | Optional. |

---

## 12. Acceptance criteria

- [ ] `themes` + `theme_fonts` migrations, `Theme`/`ThemeFont` models +
      factories.
- [ ] `ThemesSeeder` creates Default (active) + Emerald + Indigo + Midnight;
      re-runnable without dupes; wired into `DatabaseSeeder`.
- [ ] Exactly one `is_active` theme at all times (transactional invariant).
- [ ] Active theme compiled to `storage/app/public/themes/...` and linked in
      `<head>` after `app.css`; **no front-end rebuild** needed to switch;
      light/dark both correct; Default ≡ current look.
- [ ] Super-admin can create / edit / clone / activate / delete at
      `/admin/themes`; presets are clone-only and never deletable; the
      active theme can't be deleted.
- [ ] Color tokens editable per light + dark; radius editable; live preview.
- [ ] Custom CSS uploadable + editable; appears in compiled output, wins
      over tokens.
- [ ] Google-Fonts ZIP uploadable; extraction is zip-slip-safe + font-
      extension-whitelisted + size-capped; families listed; default family
      selectable → `@font-face` + `--font-sans` applied.
- [ ] All mutations go through `ThemeService`; Theme changes audited; cache
      invalidated + CSS recompiled on every mutation.
- [ ] Tests (§ below) pass; `pint` + `pnpm types:check` clean.

### Test bar
- ThemeService: one-active invariant; `compile()` emits `:root`/`.dark` +
  `@font-face` + appended custom CSS; cache invalidation on mutate.
- ZIP import: rejects `../` traversal and absolute paths; ignores non-font
  entries; caps total size; parses weight/style from representative
  Google-Fonts filenames.
- Controller: 403 for non-super-admin on every theme route; activate swaps
  `is_active`; preset delete → 422/forbidden.
- Render: rendered `<head>` contains the active theme `<link>`; switching
  active theme changes the linked URL.
- Custom CSS: `.css`-only validation; size cap enforced.

---

## 13. Cross-cutting decisions

### 13.1 Global now, per-tenant later
v1 active theme is platform-global. The injection reads
`ThemeService::active()`; a later per-tenant phase changes only that
resolver (e.g. `currentTenant->theme_id ?? globalActive`) — the
`app.blade.php` `<link>`, compile pipeline, and editor are unchanged. A
nullable `tenants.theme_id` FK is the future hook (not added in v1).

### 13.2 Compile-to-file, not inline
See §4. Keeps CSP clean, cacheable, rebuild-free. Inline `<style>` is the
documented fallback for read-only filesystems.

### 13.3 oklch is the storage format
Tokens are stored and compiled as oklch (matches `app.css`). The editor
accepts hex and converts, but persists oklch so the cascade is consistent
and dark-mode lightness math stays predictable.

### 13.4 Self-hosted fonts only (v1)
ZIP upload → `/storage` self-host (matches the request, GDPR-friendly, no
third-party CDN). A "link a Google Fonts CDN URL instead" option is a §14.4
open decision, not v1.

### 13.5 Storage + cache invalidation
Public disk, `themes/{id}/…`. `theme.active` cache forgotten on every
mutation (mirror `AppSettingsService::invalidate`). Compiled artifacts are
hash-named; stale artifacts cleaned on recompile.

---

## 14. Open decisions

1. **Inline vs compiled** — recommend compiled `<link>` (§4/§13.2). Confirm
   the app has no strict CSP that would also block same-origin `<link>`
   (it won't; this is the CSP-safe choice).
2. **Token granularity** — curated groups (§6) vs exposing all ~30 tokens
   raw. Recommend grouped, with an "Advanced (all tokens)" disclosure.
3. **oklch vs hex storage** — recommend oklch (§13.3).
4. **Font source** — self-host ZIP only vs also allow a Google Fonts CDN
   `<link>`. Recommend ZIP-only for v1.
5. **Seeded count** — 4 (Default + 3 presets) vs 3. Plan assumes 4; confirm.
6. **Custom CSS trust level** — light validation (Super-Admin trusted) vs
   strict sanitization. Recommend light validation + remote-`@import` strip.
7. **Where the active link renders** — `HandleAppearance` (extend) vs a new
   `InjectActiveTheme` middleware. Recommend a dedicated middleware for
   clarity; both share `activeThemeCss` to the blade.

---

## 15. Security considerations

- **Zip-slip / zip-bomb** on font upload — normalized-path guard, extension
  whitelist (font types only), entry-count + per-file + total-uncompressed
  caps (§8). This is the highest-risk surface.
- **Custom CSS** — Super-Admin-only; same-origin served; strip remote
  `@import`. CSS can't execute JS, but `url()` exfiltration via background
  images is theoretically possible → acceptable for trusted admins, noted.
- **Path safety** — all stored files use hashed names under
  `themes/{id}/…`; never trust the uploaded filename for the storage path.
- **Compiled artifact** — written by the app to the public disk; filename is
  app-generated (hash), content is app-generated from validated tokens +
  the (validated) custom CSS file.
- **Authorization** — every theme route behind `role:Super Admin`
  (`routes/admin.php` group); FormRequests `authorize()` re-check.

---

## 16. File-by-file change list

**New**
- `database/migrations/*_create_themes_table.php`
- `database/migrations/*_create_theme_fonts_table.php`
- `app/Models/Theme.php`, `app/Models/ThemeFont.php`
- `database/factories/ThemeFactory.php`, `ThemeFontFactory.php`
- `database/seeders/ThemesSeeder.php`
- `app/Support/Theme/ThemeService.php`
- `app/Support/Theme/FontArchiveImporter.php` (ZIP extraction + parsing)
- `app/Support/Theme/ThemeCompiler.php` (token map → CSS string)
- `app/Http/Middleware/InjectActiveTheme.php`
- `app/Http/Controllers/Admin/ThemesController.php`
- `app/Http/Controllers/Admin/ThemeFontsController.php`
- `app/Http/Requests/Admin/Themes/{ThemeStoreRequest,ThemeUpdateRequest,FontUploadRequest,CssUpdateRequest}.php`
- `resources/js/pages/admin/themes/{index,edit,create}.tsx` + editor
  components (color-token grid, font uploader, css editor, live preview)
- `config/themes.php` (token schema + groups + defaults + caps)
- Tests: `tests/Feature/Admin/ThemesControllerTest.php`,
  `tests/Unit/ThemeServiceTest.php`,
  `tests/Feature/Admin/ThemeFontImportTest.php`

**Edited**
- `resources/views/app.blade.php` — `<link>` the active compiled theme CSS
  after `@vite(... app.css ...)`.
- `bootstrap/app.php` — register/alias `InjectActiveTheme` in the web group.
- `database/seeders/DatabaseSeeder.php` — call `ThemesSeeder`.
- `resources/js/components/app-sidebar.tsx` — add the `Themes` admin nav
  entry (`Palette` icon).
- `composer.json` — add `ext-zip` to `require` (document the dependency;
  it's already present at runtime).

---

## 17. Estimated effort

| Phase | What | Effort |
|---|---|---|
| A | Schema + service + compile + inject + seeder | 5–7 h |
| B | Admin CRUD + color editor + live preview | 6–9 h |
| C | Custom CSS upload/edit | 2–3 h |
| D | Google-Fonts ZIP import + font picker | 4–6 h |
| E | Polish (thumbnails, import/export, per-tenant hook) | 3–5 h |

**Total:** ~20–30 h. **A + B** (~12–16 h) is the shippable core: a working
theme switcher with 4 seeded themes editable from the admin. C/D/E layer on
the upload features and can ship incrementally.
