# Controller Conventions

Reference: `app/Http/Controllers/Settings/ProfileController.php`, `SecurityController.php`.

## Thin controllers
Controllers handle transport only: validate (via FormRequest), call model/action, return response. Business logic lives on the model or in `app/Actions/<Domain>/<Action>.php` (Fortify-style).

## Folder layout
Group controllers by feature module. Match URL/page-name structure.
```
app/Http/Controllers/
  Settings/      → settings.* routes
  Users/         → users CRUD
  API/           → JSON endpoints consumed by the SPA (e.g. async-select search)
  {Module}/      → one folder per top-level feature you add
```
Matching FormRequests live at `app/Http/Requests/<Module>/<X>Request.php`.

## Method signatures
```php
public function edit(Request $request): Response                         // → Inertia::render(...)
public function update(ProfileUpdateRequest $request): RedirectResponse  // back() or to_route()
public function destroy(ProfileDeleteRequest $request): RedirectResponse // redirect() or to_route()
```
- GETs return `Inertia\Response` via `Inertia::render('module/page', $props)`
- Writes return `RedirectResponse` — never JSON for browser flows
- API endpoints (if any) return resources/JSON — keep separate from the Inertia controllers

## FormRequest — always for writes
- Every POST/PATCH/PUT/DELETE with input takes a typed FormRequest, even if rules are empty (forces an authorization seam)
- Never validate inline with `$request->validate(...)` in a controller
- Compose validation via traits when reused (see backend/validation-traits.md)

## Inertia render conventions
```php
return Inertia::render('settings/profile', [
    'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
    'status' => $request->session()->get('status'),
]);
```
- First arg matches the page name (drives layout dispatch — see frontend/inertia-layouts.md)
- Pass primitives / arrays only — not full Eloquent models with relations (use `$model->only([...])` or a Resource)

## Post-mutation
- Push a toast: `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])` (see global/toast-flash.md)
- Redirect with `to_route('name')` or `back()` — never `redirect('/literal-url')` (use route names)
- Wrap user-facing strings in `__()`

## Conditional middleware
Use `HasMiddleware` interface for per-method middleware (matches `SecurityController`):
```php
public static function middleware(): array {
    return [new Middleware('password.confirm', only: ['edit'])];
}
```
