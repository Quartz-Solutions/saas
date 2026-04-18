# Data Tables

Two table components cover every list view. Live reference + playground at `/shared-components` (auth-only).

## Pick one

| Use | Component | Data source |
|---|---|---|
| Large/unknown dataset, filter/search/sort on the server | `DataTable<T>` | `resources/js/components/data-table/data-table.tsx` |
| Small, already-fetched dataset, everything client-side | `LocalDataTable<T>` | `resources/js/components/local-data-table.tsx` |

Rule of thumb: if the list could exceed ~a few hundred rows, use `DataTable`. Otherwise `LocalDataTable` is one prop away from "done".

## DataTable — server-driven

Parent owns the data fetch. The component emits callbacks; you respond with an Inertia partial reload.

```tsx
import { DataTable, type DataTableColumn, type DataTableFilter, type PaginationData } from '@/components/data-table/data-table';
import { router } from '@inertiajs/react';
import { index as usersIndex } from '@/routes/users';

<DataTable<UserRow>
    tableId="users-index"
    data={users.data}
    pagination={users.meta}
    columns={columns}
    filters={filters}
    initialSearch={tableState.search}
    initialFilters={tableState.filters}
    initialSort={tableState.sort}
    onSearch={(search) => reload({ search })}
    onFilter={(filters) => reload({ filters })}
    onSort={(column, direction) => reload({ sort: { column, direction } })}
    onPageChange={(page) => reload({ page })}
    onClearAll={() => reload({})}
    onExport={(filters, q) => router.post('/users/export', { filters, q })}
/>
```

The partial-reload helper:

```tsx
function reload(params) {
    router.get(usersIndex().url, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'tableState'],
    });
}
```

### Controller shape
Return the paginator meta + a `tableState` object so the page can round-trip filters:

```php
return Inertia::render('users/index', [
    'users' => [
        'data' => $paginator->items(),
        'meta' => [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ],
    ],
    'tableState' => [
        'search' => $search,
        'filters' => (object) $filters,
        'sort' => ['column' => $sort, 'direction' => $direction],
    ],
]);
```

- Always allow-list sort columns (never trust the client): `const ALLOWED_SORT = ['id', 'name', …]`
- `ilike` for search on Postgres; `like` on MySQL
- For `daterange` filters the wire format is `"YYYY-MM-DD|YYYY-MM-DD"` — parse with `explode('|', $value)`
- For `range` filters the format is `"min|max"` (either side may be empty)

### Filter types supported
`text` · `select` · `date` · `daterange` · `range` (numeric min/max) · `async-select`

`async-select` hits `searchUrl` with `?search=` and expects `{ data: [{ label, value }, …] }`. Example endpoint: `app/Http/Controllers/API/UserSearchController.php`.

## LocalDataTable — client-side

Hands you a table over a plain array. No callbacks needed.

```tsx
import { LocalDataTable, type LocalTableColumn, type LocalTableFilter } from '@/components/local-data-table';

<LocalDataTable<Product>
    tableId="products-local"
    data={products}
    searchKeys={['name', 'sku']}
    pageSize={15}
    exportable
    exportFilename="products"
    columns={[
        { key: 'price', header: 'Price', sortable: true,
          render: (r) => `$${r.price.toFixed(2)}`,
          exportValue: (r) => r.price },
    ]}
    filters={[
        { key: 'category', label: 'Category', type: 'select', options: [...] },
        { key: 'created_at', label: 'Added between', type: 'daterange' },
    ]}
/>
```

- Built-in CSV export via `exportable` — pass `exportValue(row)` per column for values that differ from the rendered cell
- `onPageDataChange(pageData, allFilteredData)` callback lets you build row-selection UIs on top

## Shared conventions (both tables)

- **Generic over `<T>`** — always pass the row type for typed `render`, `sortKey`, etc.
- **`key`** must be unique per column; `render?: (row) => ReactNode` for custom cells
- **Alignment**: use `headerClassName="justify-end"` + `className="text-right tabular-nums"` for numeric columns
- **Action columns** (buttons, checkboxes) get an empty or function `header` so the column-toggle UI skips them and they always render
- **`tableId`** enables cookie + API preference persistence (see `use-table-preferences`). Omit for disposable/demo tables.
- **Non-toggleable columns** (headers that are empty or functions) are auto-kept visible regardless of the preference set

## Preferences persistence

`resources/js/hooks/use-table-preferences.ts` handles column visibility, filters, and search — cookie-first (sync read) plus a 1-second-debounced API roundtrip.

Backend: `GET/PUT settings/preferences/{page}` → `UserPreferenceController`, stored in the `user_preferences` table (`user_id`, `page`, `name` [default: `Default`], `value` jsonb, `is_active`). The update action merges the incoming subset with existing fields so multiple hook instances sharing a `tableId` don't clobber each other.

If a page never needs persistence, either omit `tableId` or pass `persistPreferences={false}`.

## What not to do

- Don't roll a fourth table component — extend the existing ones or add a column render fn
- Don't pass Eloquent models with eager-loaded relations into `data` — `$model->only([...])` or a Resource
- Don't hand-edit `resources/js/routes/**` or `resources/js/actions/**` helpers to add a `.form()` — run `php artisan wayfinder:generate --with-form` inside the container instead (Vite's wayfinder plugin does this automatically during `pnpm dev`)
