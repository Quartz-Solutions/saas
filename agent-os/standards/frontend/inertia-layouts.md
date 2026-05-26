# Inertia Page Layouts

Layouts are assigned globally in `resources/js/app.tsx` based on the page name prefix.

## Page-name → layout map
| Page name pattern        | Layout                        | Use for                                  |
|--------------------------|-------------------------------|------------------------------------------|
| `welcome`                | _(no layout)_                 | landing / marketing entry                |
| `auth/*`                 | `AuthLayout`                  | login, register, password reset, 2FA     |
| `settings/*`             | `[AppLayout, SettingsLayout]` | profile, security, appearance            |
| anything else (default)  | `AppLayout`                   | authenticated app pages (dashboard, etc.)|

## URL vs page name
URL and page name are independent — the URL stays clean, the page name drives layout choice.
```php
Route::inertia('/',          'welcome');             // → no layout
Route::inertia('dashboard',  'dashboard');           // → AppLayout (default)
Route::inertia('settings/profile', 'settings/profile'); // → AppLayout + SettingsLayout
```

## app.tsx dispatch
```tsx
layout: (name) => {
    switch (true) {
        case name === 'welcome':            return null;
        case name.startsWith('auth/'):      return AuthLayout;
        case name.startsWith('settings/'):  return [AppLayout, SettingsLayout];
        default:                            return AppLayout;
    }
}
```
Layouts may stack: outer-first.

## Page.layout (data, not component)
Pages export a plain object passed to the layout as props:
```tsx
Login.layout = { title: 'Log in', description: 'Enter your email...' };          // AuthLayout reads these
UsersList.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };         // AppLayout reads breadcrumbs
```
- It's **data**, not a layout component (the component is chosen by `app.tsx`)
- `href` values come from Wayfinder helpers (see frontend/wayfinder-routes.md)

## Adding pages
- Authenticated app page → file at `resources/js/pages/<name>.tsx`, no prefix (defaults to `AppLayout`)
- Settings page → file at `resources/js/pages/settings/<name>.tsx`
- Auth flow page → file at `resources/js/pages/auth/<name>.tsx`
- New top-level section needing different chrome → add a `case name.startsWith('<prefix>/')` branch in `app.tsx`
