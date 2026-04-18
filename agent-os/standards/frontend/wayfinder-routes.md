# Wayfinder Routes & Actions

All in-app URLs and form actions come from Wayfinder-generated helpers. Never hand-write href strings or edit generated files.

## Use
```tsx
import { edit } from '@/routes/profile';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

<Link href={edit()}>Profile</Link>
<Form {...ProfileController.update.form()}>...</Form>
```

## Rules
- `@/routes/*` — URL helpers (use with `<Link href>`, `router.visit`)
- `@/actions/*` — controller-method helpers (use with `<Form {...x.form()}>`)
- Even external links go through a route helper or constants — no inline `href="/..."` strings in JSX
- **Never edit** files under `resources/js/routes/**`, `resources/js/actions/**`, `resources/js/wayfinder/**` — the Wayfinder Vite plugin regenerates them and silently overwrites edits
- If a helper is missing, add the Laravel route (or controller method) and rerun `vite` — do not stub the helper by hand
