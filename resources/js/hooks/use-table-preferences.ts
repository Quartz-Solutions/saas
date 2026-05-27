import { useCallback, useEffect, useRef, useState } from 'react';

export interface TablePreferences {
    visibleColumns: string[];
    filters: Record<string, string>;
    search: string;
}

const COOKIE_MAX_AGE = 60 * 60 * 24 * 365; // 1 year
const DEBOUNCE_MS = 1000;

function getCookie(name: string): string | null {
    if (typeof document === 'undefined') {
return null;
}

    const cookies = document.cookie.split(';');

    for (const cookie of cookies) {
        const [cookieName, cookieValue] = cookie.trim().split('=');

        if (cookieName === name) {
            try {
                return decodeURIComponent(cookieValue);
            } catch {
                return cookieValue;
            }
        }
    }

    return null;
}

function setCookie(name: string, value: string): void {
    if (typeof document === 'undefined') {
return;
}

    document.cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax`;
}

function getStoredPreferences(tableId: string): Partial<TablePreferences> | null {
    const cookieName = `datatable_${tableId}`;
    const stored = getCookie(cookieName);

    if (!stored) {
return null;
}

    try {
        return JSON.parse(stored) as Partial<TablePreferences>;
    } catch {
        return null;
    }
}

function storePreferences(tableId: string, preferences: Partial<TablePreferences>): void {
    const cookieName = `datatable_${tableId}`;
    setCookie(cookieName, JSON.stringify(preferences));
}

async function saveToApi(tableId: string, preferences: Partial<TablePreferences>): Promise<void> {
    try {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        await fetch(`/settings/preferences/${tableId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ value: preferences }),
        });
    } catch {
        // Silently fail — cookie is the fallback
    }
}

async function loadFromApi(tableId: string): Promise<Partial<TablePreferences> | null> {
    try {
        const response = await fetch(`/settings/preferences/${tableId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
return null;
}

        const json = await response.json();

        return json.data ?? null;
    } catch {
        return null;
    }
}

type TablePreferenceField = 'visibleColumns' | 'filters' | 'search';

interface UseTablePreferencesOptions {
    tableId?: string;
    allColumnKeys: string[];
    defaultHiddenColumns?: string[];
    persistPreferences?: boolean;
    initialFilters?: Record<string, string>;
    initialSearch?: string;
    managedFields?: TablePreferenceField[];
}

interface UseTablePreferencesReturn {
    visibleColumns: Set<string>;
    toggleColumn: (columnKey: string) => void;
    showColumn: (columnKey: string) => void;
    hideColumn: (columnKey: string) => void;
    resetColumns: () => void;
    showAllColumns: () => void;
    isColumnVisible: (columnKey: string) => boolean;
    filters: Record<string, string>;
    setFilters: (filters: Record<string, string>) => void;
    search: string;
    setSearch: (search: string) => void;
}

export function useTablePreferences({
    tableId,
    allColumnKeys,
    defaultHiddenColumns = [],
    persistPreferences = true,
    initialFilters = {},
    initialSearch = '',
    managedFields,
}: UseTablePreferencesOptions): UseTablePreferencesReturn {
    const managesColumns = !managedFields || managedFields.includes('visibleColumns');
    const managesFilters = !managedFields || managedFields.includes('filters');
    const managesSearch = !managedFields || managedFields.includes('search');
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(null);
    const userHasChanged = useRef(false);
    const apiLoadAttempted = useRef(false);

    const getDefaultVisibleColumns = useCallback(() => {
        return new Set(allColumnKeys.filter((key) => !defaultHiddenColumns.includes(key)));
    }, [allColumnKeys, defaultHiddenColumns]);

    const resolveStoredColumns = useCallback(
        (stored: Partial<TablePreferences>): Set<string> | null => {
            if (!stored?.visibleColumns || !Array.isArray(stored.visibleColumns)) {
return null;
}

            const validColumns = stored.visibleColumns.filter((key) => allColumnKeys.includes(key));

            if (validColumns.length === 0) {
return null;
}

            return new Set(validColumns);
        },
        [allColumnKeys],
    );

    const [visibleColumns, setVisibleColumns] = useState<Set<string>>(() => {
        if (persistPreferences && tableId) {
            const stored = getStoredPreferences(tableId);

            if (stored) {
                const resolved = resolveStoredColumns(stored);

                if (resolved) {
return resolved;
}
            }
        }

        return getDefaultVisibleColumns();
    });

    const [filters, setFiltersState] = useState<Record<string, string>>(() => {
        if (persistPreferences && tableId) {
            const stored = getStoredPreferences(tableId);

            if (stored?.filters && typeof stored.filters === 'object') {
                return { ...initialFilters, ...stored.filters };
            }
        }

        return initialFilters;
    });

    const [search, setSearchState] = useState<string>(() => {
        if (persistPreferences && tableId) {
            const stored = getStoredPreferences(tableId);

            if (typeof stored?.search === 'string') {
                return stored.search;
            }
        }

        return initialSearch;
    });

    useEffect(() => {
        if (!persistPreferences || !tableId || apiLoadAttempted.current) {
return;
}

        apiLoadAttempted.current = true;

        loadFromApi(tableId).then((apiData) => {
            if (!apiData) {
return;
}

            const resolved = resolveStoredColumns(apiData);

            if (resolved) {
                setVisibleColumns(resolved);
            }

            if (apiData.filters && typeof apiData.filters === 'object') {
                setFiltersState((prev) => ({ ...prev, ...apiData.filters }));
            }

            if (typeof apiData.search === 'string') {
                setSearchState(apiData.search);
            }

            storePreferences(tableId, apiData);
        });
    }, [persistPreferences, tableId, resolveStoredColumns]);

    useEffect(() => {
        if (!persistPreferences || !tableId || !userHasChanged.current) {
return;
}

        const prefs: Partial<TablePreferences> = {};

        if (managesColumns) {
prefs.visibleColumns = Array.from(visibleColumns);
}

        if (managesFilters) {
prefs.filters = filters;
}

        if (managesSearch) {
prefs.search = search;
}

        const existing = getStoredPreferences(tableId) ?? {};
        storePreferences(tableId, { ...existing, ...prefs });

        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        debounceTimer.current = setTimeout(() => {
            saveToApi(tableId, prefs);
        }, DEBOUNCE_MS);

        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [
        visibleColumns,
        filters,
        search,
        persistPreferences,
        tableId,
        managesColumns,
        managesFilters,
        managesSearch,
    ]);

    const toggleColumn = useCallback((columnKey: string) => {
        userHasChanged.current = true;
        setVisibleColumns((prev) => {
            const next = new Set(prev);

            if (next.has(columnKey)) {
                if (next.size > 1) {
                    next.delete(columnKey);
                }
            } else {
                next.add(columnKey);
            }

            return next;
        });
    }, []);

    const showColumn = useCallback((columnKey: string) => {
        userHasChanged.current = true;
        setVisibleColumns((prev) => {
            const next = new Set(prev);
            next.add(columnKey);

            return next;
        });
    }, []);

    const hideColumn = useCallback((columnKey: string) => {
        userHasChanged.current = true;
        setVisibleColumns((prev) => {
            if (prev.size <= 1) {
return prev;
}

            const next = new Set(prev);
            next.delete(columnKey);

            return next;
        });
    }, []);

    const resetColumns = useCallback(() => {
        userHasChanged.current = true;
        setVisibleColumns(getDefaultVisibleColumns());
    }, [getDefaultVisibleColumns]);

    const showAllColumns = useCallback(() => {
        userHasChanged.current = true;
        setVisibleColumns(new Set(allColumnKeys));
    }, [allColumnKeys]);

    const isColumnVisible = useCallback(
        (columnKey: string) => visibleColumns.has(columnKey),
        [visibleColumns],
    );

    const setFilters = useCallback((newFilters: Record<string, string>) => {
        userHasChanged.current = true;
        setFiltersState(newFilters);
    }, []);

    const setSearch = useCallback((newSearch: string) => {
        userHasChanged.current = true;
        setSearchState(newSearch);
    }, []);

    return {
        visibleColumns,
        toggleColumn,
        showColumn,
        hideColumn,
        resetColumns,
        showAllColumns,
        isColumnVisible,
        filters,
        setFilters,
        search,
        setSearch,
    };
}
