# Page Components (resources/js/pages/)

Files in `pages/` are Inertia entry points — one file per route. Pages are composition layers, not implementations.

## File & naming
- Filename: kebab-case, matches the page name passed to `Inertia::render('module/page')`
  - Controller `Inertia::render('settings/profile')` → `pages/settings/profile.tsx`
  - Controller `Inertia::render('home')` → `pages/home.tsx`
- Component: PascalCase, **default export**
- One page per file

## Page props (typed per page, matches controller payload)
```tsx
type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
};

export default function Login({ status, canResetPassword, canRegister }: Props) { /* ... */ }
```
- Type mirrors the controller's `Inertia::render` array — keep them in sync manually
- Inline object type is fine for 1–3 props; promote to `type Props` at 4+

## <Head>
Every page sets its title:
```tsx
import { Head } from '@inertiajs/react';

<Head title="Profile settings" />
```
(Suffix `- {appName}` is added globally by `app.tsx`.)

## Layout assignment via Page.layout
Pages opt into layout-specific data with a static property:
```tsx
Login.layout = { title: 'Log in', description: 'Enter your email...' };
Profile.layout = { breadcrumbs: [{ title: 'Profile settings', href: edit() }] };
```
- Data only — the layout component is chosen by `app.tsx` (see frontend/inertia-layouts.md)
- `href` values come from Wayfinder helpers (see frontend/wayfinder-routes.md)

## Forms — <Form> + Wayfinder + uncontrolled inputs
```tsx
import { Form } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

<Form
    {...ProfileController.update.form()}
    options={{ preserveScroll: true }}
    resetOnSuccess={['password']}
>
    {({ processing, errors }) => (
        <>
            <Input name="name" defaultValue={auth.user.name} required />
            <InputError message={errors.name} />
            <Button disabled={processing}>Save</Button>
        </>
    )}
</Form>
```
- Use `<Form>` + `Controller.action.form()` spread — not `useForm` (unless the page needs imperative control)
- Inputs are **uncontrolled** — use `defaultValue`, not `value`
- Read `errors.<field>` from the render prop; render via `<InputError message={errors.x} />`
- `processing` disables the submit button (add `<Spinner />` for async-feeling flows)
- `resetOnSuccess`, `options.preserveScroll`, `onError` are the common Form props

## Server data via usePage
```tsx
const { auth } = usePage().props;
```
For shared data set in `HandleInertiaRequests::share()` (auth user, sidebar state, app name).

## Pages stay thin
- Composition only — imports widgets from `@/components/*`, layouts from `@/layouts/*`
- Promote any reusable JSX to `components/` (see frontend/shared-components.md)
- No business logic, no data transformation in render — do it server-side or in a hook
