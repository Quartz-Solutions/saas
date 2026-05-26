# RTL (Right-to-left) support

The boilerplate ships with `dir="rtl"` flipped automatically when the active
locale starts with `ar` (Arabic). Tailwind 4 handles most of the heavy lifting
— flexbox direction inherits from `dir`, and logical properties (`me-*`,
`ms-*`, `ps-*`, `pe-*`, `start-*`, `end-*`) work out of the box. This doc
captures the edge cases worth knowing about.

## How it's wired

- `App\Http\Middleware\HandleAppearance` resolves the request locale from
  `?locale=...` query, then a `locale` cookie, then `config('app.locale')`,
  and calls `app()->setLocale()`. This means `?locale=ar` is a cheap way to
  verify any page renders RTL during development.
- `resources/views/app.blade.php` sets
  `dir="{{ Str::startsWith(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}"`
  on the root `<html>`. The whole tree inherits.

## What works automatically

- shadcn primitives (`Sidebar`, `Dialog`, `DropdownMenu`, `Select`, ...) all
  use logical properties via Tailwind 4 / Radix, so they flip correctly.
- `flex` layouts flip naturally.
- Lucide icons that have a directional meaning (`ChevronRight`, `ArrowLeft`)
  are rendered as-is — if you find one that reads wrong in RTL, swap it for
  the mirrored variant inside the consuming component, or add a
  `rtl:rotate-180` Tailwind modifier.

## When you need `rtl:` modifiers

- **Asymmetric margins / padding** — prefer logical Tailwind utilities:
  - `me-*` / `ms-*` instead of `mr-*` / `ml-*`
  - `pe-*` / `ps-*` instead of `pr-*` / `pl-*`
- **Absolute positioning** — use `start-*` / `end-*` instead of `left-*` /
  `right-*`.
- **Directional icons inside text** (chevrons, arrows) — `rtl:rotate-180`
  on the icon flips them.
- **Tables** — first/last column alignment uses logical properties already.

## Spot-checking RTL

```text
http://localhost:8080/dashboard?locale=ar
```

This sets the locale for the current request only. `tests/Feature/RtlTest.php`
exercises the same path: it sets the locale to `ar` and asserts the response
HTML contains `dir="rtl"`.

## What's NOT covered yet

- Inline content negotiation per-user (would require a `locale` column +
  preference page). The `locale` cookie path is the bridge for now.
- Localized number/date formatting beyond Laravel's defaults. When the
  i18n phase lands, pair `Carbon::setLocale()` with the new locale on each
  request.
