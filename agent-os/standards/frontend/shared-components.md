# Shared Components (resources/js/components/)

Reusable widgets used across pages. Keep low-friction — promote any widget that looks self-contained, even if used once.

## Live catalog
`/shared-components` (auth-only) renders every primitive and widget with live preview + copy-able usage snippet. Treat it as the source of truth for what exists; this file only describes the conventions for adding or composing them.

## File & naming
- Filename: kebab-case (`password-input.tsx`, `delete-user.tsx`)
- Component: PascalCase, **default export**, name matches filename
- One component per file

## Two folders, two rules
- `components/ui/*` — shadcn/ui primitives (new-york style, Radix-backed). **Don't hand-edit** (lint-ignored). Add via `npx shadcn add <name>` inside the container, or scaffold manually by installing the matching `@radix-ui/react-*` and mirroring the existing wrapper style.
- `components/*` — your composed widgets. Free to edit.

### What's currently in `components/ui/`
accordion · alert · alert-dialog · aspect-ratio · avatar · badge · breadcrumb · button · calendar · card · checkbox · collapsible · context-menu · date-picker · date-range-picker · dialog · dropdown-menu · hover-card · icon · input · input-otp · label · menubar · navigation-menu · placeholder-pattern · popover · progress · radio-group · scroll-area · select · separator · sheet · sidebar · skeleton · slider · sonner · spinner · switch · table · tabs · textarea · toggle · toggle-group · tooltip

### What's currently in `components/` (composed)
alert-error · app-* (layout scaffold) · appearance-tabs · breadcrumbs · data-table/* · delete-user · heading · input-error · local-data-table · nav-* · password-input · text-link · two-factor-recovery-codes · two-factor-setup-modal · user-info · user-menu-content

Large components with their own conventions are documented separately:
- `frontend/data-tables.md` — `DataTable` (server-driven) and `LocalDataTable` (client-side)

## Props typing (inline, not interface)
```tsx
export default function Heading({ title, description, variant = 'default' }: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) { /* ... */ }
```
- Inline object type for small APIs
- Extend HTML/Inertia types when wrapping primitives:
  ```tsx
  HTMLAttributes<HTMLParagraphElement> & { message?: string }
  Omit<ComponentProps<'input'>, 'type'> & { ref?: Ref<HTMLInputElement> }
  ComponentProps<typeof Link>
  ```
- Promote a `type Props = ...` alias only when it's exported or > ~5 fields

## className handling
Always merge via `cn()` so callers can override:
```tsx
import { cn } from '@/lib/utils';

<Input className={cn('pr-10', className)} ref={ref} {...props} />
```

## Ref forwarding (React 19)
Accept `ref` as a regular prop — no `forwardRef`:
```tsx
function PasswordInput({ ref, ...props }: ComponentProps<'input'> & { ref?: Ref<HTMLInputElement> }) { /* ... */ }
```

## Spread the rest
Always `{...props}` to the underlying element so callers pass through `id`, `aria-*`, `name`, `autoComplete`, etc.

## Icons
Lucide React, sized `size-4` (16px) by default, `size-5` for emphasis. Imported as named: `import { Eye, EyeOff } from 'lucide-react'`

## Test selectors
Key interactive elements get `data-test="<purpose>-<element>"`:
```tsx
<Button data-test="login-button">Log in</Button>
<Button data-test="delete-user-button">Delete account</Button>
```

## When to extract from a page
Promote any self-contained widget into `components/` — even on first use. Example: `delete-user.tsx`, `two-factor-setup-modal.tsx`. Pages should read like outlines, not implementations.
