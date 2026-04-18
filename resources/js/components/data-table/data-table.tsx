import * as React from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';

import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTablePreferences } from '@/hooks/use-table-preferences';
import { formatDate as formatDateUtil } from '@/lib/utils';
import { cn } from '@/lib/utils';

import { DataTableFilters } from './data-table-filters';
import { DataTablePagination } from './data-table-pagination';
import { DataTableToolbar } from './data-table-toolbar';

export interface DataTableColumn<T> {
    key: string;
    header: string | (() => React.ReactNode);
    sortable?: boolean;
    render?: (row: T) => React.ReactNode;
    /** Applied to the data <td> cell. */
    className?: string;
    /** Applied to the inner flex wrapper in the header — use justify-end etc. to align header label. */
    headerClassName?: string;
}

export interface DataTableFilter {
    key: string;
    label: string;
    type: 'text' | 'select' | 'date' | 'daterange' | 'range' | 'async-select';
    options?: { label: string; value: string }[];
    placeholder?: string;
    // For range filter type (optional - used for display formatting)
    step?: number;
    formatValue?: (value: number) => string;
    // For async-select filter type
    searchUrl?: string;
}

export interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface DataTableProps<T> {
    data: T[];
    columns: DataTableColumn<T>[];
    filters?: DataTableFilter[];
    pagination?: PaginationData;
    onPageChange?: (page: number) => void;
    onSort?: (column: string, direction: 'asc' | 'desc') => void;
    onFilter?: (filters: Record<string, string>) => void;
    onSearch?: (query: string) => void;
    onClearAll?: () => void;
    onExport?: (filters: Record<string, string>, search: string) => void;
    exporting?: boolean;
    loading?: boolean;
    emptyMessage?: string;
    initialSearch?: string;
    initialFilters?: Record<string, string>;
    initialSort?: { column: string; direction: 'asc' | 'desc' };
    rowKey?: keyof T | ((row: T) => string | number);
    tableId?: string;
    defaultHiddenColumns?: string[];
    showColumnToggle?: boolean;
    persistPreferences?: boolean;
    // Optional externally-managed column visibility. When provided, the parent
    // component owns the single source of truth for column state and the
    // DataTable will not create its own persisted hook — avoiding two
    // independent hook instances racing on the same tableId.
    externalVisibleColumns?: Set<string>;
    externalOnToggleColumn?: (columnKey: string) => void;
    externalOnResetColumns?: () => void;
    externalOnShowAllColumns?: () => void;
}

export function DataTable<T extends object>({
    data,
    columns,
    filters = [],
    pagination,
    onPageChange,
    onSort,
    onFilter,
    onSearch,
    onClearAll,
    onExport,
    exporting = false,
    loading = false,
    emptyMessage = 'No results found.',
    initialSearch = '',
    initialFilters = {},
    initialSort,
    rowKey,
    tableId,
    defaultHiddenColumns = [],
    showColumnToggle = true,
    persistPreferences = true,
    externalVisibleColumns,
    externalOnToggleColumn,
    externalOnResetColumns,
    externalOnShowAllColumns,
}: DataTableProps<T>) {
    // When the parent provides external column state, it owns column
    // persistence. The internal hook then only manages filters/search so the
    // two instances don't race on the same tableId.
    const hasExternalColumnState = externalVisibleColumns !== undefined;
    const safeData = Array.isArray(data) ? data.filter(Boolean) : [];

    // Helper to get unique row key
    const getRowKey = (row: T, index: number): string | number => {
        if (rowKey) {
            if (typeof rowKey === 'function') {
                return rowKey(row);
            }
            return row[rowKey] as string | number;
        }
        // Fallback to 'id' if it exists on the row
        if ('id' in row && row.id !== undefined) {
            return row.id as string | number;
        }
        return index;
    };

    // Column keys for the preferences hook
    const allColumnKeys = useMemo(() => columns.map((col) => col.key), [columns]);

    // Use the table preferences hook for column visibility and filter persistence
    const {
        visibleColumns: internalVisibleColumns,
        toggleColumn: internalToggleColumn,
        resetColumns: internalResetColumns,
        showAllColumns: internalShowAllColumns,
        filters: persistedFilters,
        setFilters: setPersistedFilters,
        search: persistedSearch,
        setSearch: setPersistedSearch,
    } = useTablePreferences({
        tableId,
        allColumnKeys,
        defaultHiddenColumns,
        persistPreferences: persistPreferences && !!tableId,
        initialFilters,
        initialSearch,
        // When the parent owns columns, restrict this hook to filters/search
        // so it doesn't overwrite the externally-managed column state.
        managedFields: hasExternalColumnState ? ['filters', 'search'] : undefined,
    });

    // Prefer externally-managed column state when provided (parent owns persistence)
    const visibleColumns = externalVisibleColumns ?? internalVisibleColumns;
    const toggleColumn = externalOnToggleColumn ?? internalToggleColumn;
    const resetColumns = externalOnResetColumns ?? internalResetColumns;
    const showAllColumns = externalOnShowAllColumns ?? internalShowAllColumns;

    // A column is "non-toggleable" when the column-toggle UI refuses to show
    // it (function header, empty header, etc.) — those are structural columns
    // like row-select checkboxes and should always render regardless of what's
    // in the visibility set.
    const isNonToggleable = (col: DataTableColumn<T>): boolean => {
        if (!col.header) return true;
        if (typeof col.header === 'function') return true;
        return col.header.trim() === '';
    };

    // Filter columns based on visibility; non-toggleable columns stay visible.
    const visibleColumnsList = useMemo(
        () => columns.filter((col) => isNonToggleable(col) || visibleColumns.has(col.key)),
        [columns, visibleColumns]
    );

    const [sortColumn, setSortColumn] = useState<string | null>(
        initialSort?.column || null
    );
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>(
        initialSort?.direction || 'desc'
    );
    const [filtersOpen, setFiltersOpen] = useState(false);
    // Labels for async-select filters (key -> display label)
    const [asyncFilterLabels, setAsyncFilterLabels] = useState<Record<string, string>>({});

    // Track if we've done the initial sync
    const hasInitializedRef = useRef(false);

    // Use persisted values for display (with null safety)
    const activeFilters = (tableId && persistPreferences ? persistedFilters : initialFilters) || {};
    const searchQuery = (tableId && persistPreferences ? persistedSearch : initialSearch) || '';

    // On mount, sync persisted filters with server if they differ from URL params
    useEffect(() => {
        if (!tableId || !persistPreferences || hasInitializedRef.current) return;
        hasInitializedRef.current = true;

        const safePersistedFilters = persistedFilters || {};
        const safePersistedSearch = persistedSearch || '';
        const safeInitialFilters = initialFilters || {};
        const safeInitialSearch = initialSearch || '';

        const hasPersistedFilters = Object.keys(safePersistedFilters).length > 0;
        const hasPersistedSearch = safePersistedSearch.length > 0;
        const filtersMatch = JSON.stringify(safePersistedFilters) === JSON.stringify(safeInitialFilters);
        const searchMatch = safePersistedSearch === safeInitialSearch;

        // If persisted values differ from URL, trigger server-side filtering
        if ((hasPersistedFilters && !filtersMatch) || (hasPersistedSearch && !searchMatch)) {
            if (!filtersMatch && onFilter) {
                onFilter(safePersistedFilters);
            }
            if (!searchMatch && onSearch) {
                onSearch(safePersistedSearch);
            }
        }
    }, [tableId, persistPreferences, persistedFilters, persistedSearch, initialFilters, initialSearch, onFilter, onSearch]);

    const handleSort = (columnKey: string) => {
        const column = columns.find((col) => col.key === columnKey);
        if (!column?.sortable) return;

        const newDirection =
            sortColumn === columnKey && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortColumn(columnKey);
        setSortDirection(newDirection);
        onSort?.(columnKey, newDirection);
    };

    const handleFilterChange = (newFilters: Record<string, string>) => {
        if (tableId && persistPreferences) {
            setPersistedFilters(newFilters);
        }
        onFilter?.(newFilters);
    };

    const handleClearFilter = (filterKey: string) => {
        const newFilters = { ...activeFilters };
        delete newFilters[filterKey];
        if (tableId && persistPreferences) {
            setPersistedFilters(newFilters);
        }
        onFilter?.(newFilters);
    };

    const handleClearAllFilters = () => {
        if (tableId && persistPreferences) {
            setPersistedFilters({});
            setPersistedSearch('');
        }
        if (onClearAll) {
            onClearAll();
        } else {
            onFilter?.({});
            onSearch?.('');
        }
    };

    const handleSearch = (query: string) => {
        if (tableId && persistPreferences) {
            setPersistedSearch(query);
        }
        onSearch?.(query);
    };

    const hasActiveFilters =
        Object.keys(activeFilters).length > 0 || searchQuery.length > 0;

    return (
        <div className="space-y-4">
            <DataTableToolbar
                searchQuery={searchQuery}
                onSearch={handleSearch}
                onOpenFilters={() => setFiltersOpen(true)}
                hasFilters={filters.length > 0}
                columns={columns}
                visibleColumns={visibleColumns}
                onToggleColumn={toggleColumn}
                onResetColumns={resetColumns}
                onShowAllColumns={showAllColumns}
                showColumnToggle={showColumnToggle}
                onExport={onExport ? () => onExport(activeFilters, searchQuery) : undefined}
                exporting={exporting}
            />

            {/* Active Filters */}
            {hasActiveFilters && (
                <div className="flex flex-wrap items-center gap-2 rounded-lg border border-dashed p-4">
                    <span className="text-sm font-medium">Active filters:</span>
                    {searchQuery && (
                        <div className="inline-flex items-center gap-1 rounded-md border px-2.5 py-0.5 text-xs font-semibold">
                            Search: {searchQuery}
                            <button
                                onClick={() => handleSearch('')}
                                className="ml-1 hover:text-destructive"
                            >
                                ×
                            </button>
                        </div>
                    )}
                    {Object.entries(activeFilters).map(([key, value]) => {
                        const filter = filters.find((f) => f.key === key);
                        if (!filter || !value) return null;

                        let displayValue = value;
                        if (filter.type === 'async-select') {
                            displayValue = asyncFilterLabels[key] || value;
                        } else if (filter.type === 'select' && filter.options) {
                            const option = filter.options.find(
                                (opt) => opt.value === value
                            );
                            displayValue = option?.label || value;
                        } else if (filter.type === 'daterange' && value.includes('|')) {
                            const [from, to] = value.split('|');
                            const formatDate = (dateStr: string) => formatDateUtil(dateStr, dateStr);
                            displayValue = to
                                ? `${formatDate(from)} - ${formatDate(to)}`
                                : formatDate(from);
                        } else if (filter.type === 'range' && value.includes('|')) {
                            const [min, max] = value.split('|');
                            const formatVal = filter.formatValue || ((v: number) => v.toString());
                            if (min && max) {
                                displayValue = `${formatVal(Number(min))} - ${formatVal(Number(max))}`;
                            } else if (min) {
                                displayValue = `≥ ${formatVal(Number(min))}`;
                            } else if (max) {
                                displayValue = `≤ ${formatVal(Number(max))}`;
                            }
                        }

                        return (
                            <div
                                key={key}
                                className="inline-flex items-center gap-1 rounded-md border px-2.5 py-0.5 text-xs font-semibold"
                            >
                                {filter.label}: {displayValue}
                                <button
                                    onClick={() => handleClearFilter(key)}
                                    className="ml-1 hover:text-destructive"
                                >
                                    ×
                                </button>
                            </div>
                        );
                    })}
                    <button
                        onClick={handleClearAllFilters}
                        className="text-xs font-medium text-muted-foreground underline hover:text-foreground"
                    >
                        Clear all
                    </button>
                </div>
            )}

            {/* Table */}
            <div className="w-full overflow-x-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {visibleColumnsList.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={cn(
                                        column.sortable &&
                                            'cursor-pointer select-none',
                                        column.className
                                    )}
                                    onClick={() =>
                                        column.sortable && handleSort(column.key)
                                    }
                                >
                                    <div
                                        className={cn(
                                            'flex items-center gap-2 whitespace-nowrap',
                                            column.headerClassName
                                        )}
                                    >
                                        {typeof column.header === 'function' ? column.header() : column.header}
                                        {column.sortable && (
                                            <span className="text-xs">
                                                {sortColumn === column.key ? (
                                                    sortDirection === 'asc' ? (
                                                        '↑'
                                                    ) : (
                                                        '↓'
                                                    )
                                                ) : (
                                                    '↕'
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {loading ? (
                            <TableRow>
                                <TableCell
                                    colSpan={visibleColumnsList.length}
                                    className="h-24 text-center"
                                >
                                    Loading...
                                </TableCell>
                            </TableRow>
                        ) : safeData.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={visibleColumnsList.length}
                                    className="h-24 text-center"
                                >
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        ) : (
                            safeData.map((row, index) => (
                                <TableRow key={getRowKey(row, index)}>
                                    {visibleColumnsList.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn('whitespace-nowrap', column.className)}
                                        >
                                            {column.render
                                                ? column.render(row)
                                                : ((row as Record<string, unknown>)[column.key] as React.ReactNode)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            {pagination && (
                <DataTablePagination
                    pagination={pagination}
                    onPageChange={onPageChange}
                />
            )}

            {/* Filters Sidebar */}
            <Sheet open={filtersOpen} onOpenChange={setFiltersOpen}>
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>Filters</SheetTitle>
                        <SheetDescription>
                            Apply filters to narrow down the results.
                        </SheetDescription>
                    </SheetHeader>
                    <DataTableFilters
                        filters={filters}
                        activeFilters={activeFilters}
                        onFilterChange={handleFilterChange}
                        onClose={() => setFiltersOpen(false)}
                        onAsyncLabelChange={(key, label) => setAsyncFilterLabels((prev) => ({ ...prev, [key]: label }))}
                    />
                </SheetContent>
            </Sheet>
        </div>
    );
}
