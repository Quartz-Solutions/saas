# Toast Flash Messages

Server-side mutations communicate user feedback via Inertia flash; the client renders it through `useFlashToast` + sonner.

## Server (controller)
```php
Inertia::flash('toast', [
    'type' => 'success',         // success | info | warning | error
    'message' => __('Profile updated.'),
]);

return to_route('profile.edit');
```

## Client (auto-wired in app.tsx via Toaster + useFlashToast)
No per-page setup needed — the hook listens to Inertia router `flash` events globally.

## When to use
- **Every** successful write action (POST/PATCH/PUT/DELETE) pushes a toast
- Reads never push toasts
- Field validation errors use `<InputError message={errors.x} />` — not toast
- Non-field errors (e.g. 'Could not delete account') *may* use `type: 'error'` toast
- Error/warning toasts triggered by client-only failures (clipboard, network) call `toast.error(...)` directly

## Shape
`FlashToast` type lives in `resources/js/types/ui.ts` — keep server payload aligned:
```ts
type FlashToast = { type: 'success' | 'info' | 'warning' | 'error'; message: string };
```
