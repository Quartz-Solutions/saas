# Inertia Page Layouts

This app has two faces: a public storefront (e-commerce) and an authenticated POS/admin dashboard. Layouts are assigned globally in `resources/js/app.tsx` based on the page name prefix.

## Page-name → layout map
| Page name pattern        | Layout                        | Use for                                  |
|--------------------------|-------------------------------|------------------------------------------|
| `auth/*`                 | `AuthLayout`                  | login, register, password reset, 2FA     |
| `admin/settings/*`       | `[AppLayout, SettingsLayout]` | profile, security, appearance            |
| `admin/*`                | `AppLayout`                   | dashboard, orders, products, POS screens |
| anything else (default)  | `PublicLayout`                | storefront: `home`, `categories`, `categories/show`, `search`, `cart`, `checkout`, `product` |

## URL vs page name
URL and page name are independent — the URL stays clean, the page name drives layout choice.
```php
Route::get('/',           fn () => Inertia::render('home'));            // → PublicLayout
Route::get('/categories', fn () => Inertia::render('categories'));      // → PublicLayout
Route::get('/admin',      fn () => Inertia::render('admin/dashboard')); // → AppLayout
```

## app.tsx dispatch
```tsx
layout: (name) => {
    switch (true) {
        case name.startsWith('auth/'):            return AuthLayout;
        case name.startsWith('admin/settings/'):  return [AppLayout, SettingsLayout];
        case name.startsWith('admin/'):           return AppLayout;
        default:                                  return PublicLayout;
    }
}
```
Layouts may stack: outer-first.

## Page.layout (data, not component)
Pages export a plain object passed to the layout as props:
```tsx
Login.layout = { title: 'Log in', description: 'Enter your email...' };          // AuthLayout reads these
ProductList.layout = { breadcrumbs: [{ title: 'Products', href: index() }] };    // AppLayout reads breadcrumbs
```
- It's **data**, not a layout component (the component is chosen by `app.tsx`)
- `href` values come from Wayfinder helpers (see frontend/wayfinder-routes.md)

## Adding pages
- Public storefront page → file at `resources/js/pages/<name>.tsx`, no prefix
- Admin dashboard page → file at `resources/js/pages/admin/<name>.tsx`
- New top-level section needing different chrome → add a `case name.startsWith('<prefix>/')` branch in `app.tsx`
