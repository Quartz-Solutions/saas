import { format, parseISO } from 'date-fns';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight, Download, Filter, Loader2, Search, Settings2 } from 'lucide-react';
import { useState, useMemo, useEffect, useRef } from 'react';
import type {DateRange} from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

export interface LocalTableColumn<T> {
    key: string;
    header: string | React.ReactNode;
    sortable?: boolean;
    sortKey?: keyof T;
    render: (row: T) => React.ReactNode;
    className?: string;
    /** Optional function to get export value for this column. If not provided, uses row[key] */
    exportValue?: (row: T) => string | number | null | undefined;
    /** Set to false to exclude this column from export (default: true if header exists) */
    exportable?: boolean;
}

export interface LocalTableFilter<T> {
    key: keyof T;
    label: string;
    type?: 'select' | 'text' | 'daterange';
    options?: { label: string; value: string }[];
    placeholder?: string;
}

export interface LocalDataTableProps<T> {
    data: T[];
    columns: LocalTableColumn<T>[];
    searchKeys?: (keyof T)[];
    filters?: LocalTableFilter<T>[];
    pageSize?: number;
    emptyMessage?: string;
    searchPlaceholder?: string;
    tableId?: string;
    defaultHiddenColumns?: string[];
    showColumnToggle?: boolean;
    persistPreferences?: boolean;
    /** Enable export button (default: false) */
    exportable?: boolean;
    /** Filename for export (without extension, default: 'export') */
    exportFilename?: string;
    /** Callback when visible page data changes (for selection support) */
    onPageDataChange?: (pageData: T[], allFilteredData: T[]) => void;
}

export function LocalDataTable<T extends object>({
    data,
    columns,
    searchKeys = [],
    filters = [],
    pageSize = 15,
    emptyMessage = 'No data available',
    searchPlaceholder = 'Search...',
    tableId,
    defaultHiddenColumns = [],
    showColumnToggle = true,
    persistPreferences = true,
    exportable = false,
    exportFilename = 'export',
    onPageDataChange,
}: LocalDataTableProps<T>) {
    // Column keys for the preferences hook
    const allColumnKeys = useMemo(() => columns.map((col) => col.key), [columns]);

    // Use the table preferences hook for column visibility and filter persistence
    const {
        visibleColumns,
        toggleColumn,
        resetColumns,
        showAllColumns,
        filters: persistedFilters,
        setFilters: setPersistedFilters,
        search: persistedSearch,
        setSearch: setPersistedSearch,
    } = useTablePreferences({
        tableId,
        allColumnKeys,
        defaultHiddenColumns,
        persistPreferences: persistPreferences && !!tableId,
    });

    // Filter columns based on visibility
    const visibleColumnsList = useMemo(
        () => columns.filter((col) => visibleColumns.has(col.key)),
        [columns, visibleColumns]
    );

    // Use persisted values if tableId is set, otherwise use local state
    const [localSearch, setLocalSearch] = useState('');
    const [sortKey, setSortKey] = useState<string | null>(null);
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
    const [page, setPage] = useState(1);
    const [localActiveFilters, setLocalActiveFilters] = useState<Record<string, string>>({});
    const [localFilters, setLocalFilters] = useState<Record<string, string>>({});
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [exporting, setExporting] = useState(false);

    // Determine which state to use based on persistence (with null safety)
    const activeFilters = (tableId && persistPreferences ? persistedFilters : localActiveFilters) || {};
    const search = (tableId && persistPreferences ? persistedSearch : localSearch) || '';

    const setActiveFilters = (newFilters: Record<string, string>) => {
        if (tableId && persistPreferences) {
            setPersistedFilters(newFilters);
        } else {
            setLocalActiveFilters(newFilters);
        }
    };

    const setSearch = (newSearch: string) => {
        if (tableId && persistPreferences) {
            setPersistedSearch(newSearch);
        } else {
            setLocalSearch(newSearch);
        }
    };

    // Filter out columns without headers (like action columns) for the toggle dropdown
    const toggleableColumns = columns.filter((col) => col.header && (typeof col.header !== 'string' || col.header.trim() !== ''));
    const hiddenColumnsCount = toggleableColumns.filter((col) => !visibleColumns.has(col.key)).length;

    // Open filters sheet and sync local filters
    const openFilters = () => {
        setLocalFilters({ ...activeFilters });
        setFiltersOpen(true);
    };

    // Count active filters
    const activeFilterCount = Object.values(activeFilters).filter(v => v && v !== '').length;
    const hasActiveFilters = activeFilterCount > 0 || search.length > 0;

    // Filter data by dropdown filters
    const dropdownFilteredData = useMemo(() => {
        let result = data;

        for (const [key, value] of Object.entries(activeFilters)) {
            if (value && value !== '') {
                const filter = filters.find(f => String(f.key) === key);

                if (filter?.type === 'daterange' && value.includes('|')) {
                    const [fromStr, toStr] = value.split('|');
                    result = result.filter((row) => {
                        const rowValue = row[key as keyof T];

                        if (!rowValue) {
return false;
}

                        const rowDate = new Date(String(rowValue));
                        const fromDate = fromStr ? new Date(fromStr) : null;
                        const toDate = toStr ? new Date(toStr) : null;

                        if (fromDate && toDate) {
                            return rowDate >= fromDate && rowDate <= toDate;
                        } else if (fromDate) {
                            return rowDate >= fromDate;
                        }

                        return true;
                    });
                } else {
                    result = result.filter((row) => {
                        const rowValue = row[key as keyof T];

                        return String(rowValue) === value;
                    });
                }
            }
        }

        return result;
    }, [data, activeFilters, filters]);

    // Filter data by search
    const filteredData = useMemo(() => {
        if (!search.trim() || searchKeys.length === 0) {
return dropdownFilteredData;
}

        const searchLower = search.toLowerCase();

        return dropdownFilteredData.filter((row) =>
            searchKeys.some((key) => {
                const value = row[key];

                if (value === null || value === undefined) {
return false;
}

                return String(value).toLowerCase().includes(searchLower);
            })
        );
    }, [dropdownFilteredData, search, searchKeys]);

    // Create a stable mapping of column keys to their sortKeys
    // Use a ref to track changes and only update when sort config actually changes
    const columnSortKeysRef = useRef<Record<string, keyof T | undefined>>({});
    const columnSortKeys = useMemo(() => {
        const map: Record<string, keyof T | undefined> = {};
        columns.forEach((col) => {
            map[col.key] = col.sortKey;
        });
        // Check if the sort config actually changed
        const prevKeys = Object.keys(columnSortKeysRef.current).sort().join(',');
        const newKeys = Object.keys(map).sort().join(',');
        const prevVals = Object.values(columnSortKeysRef.current).map(String).join(',');
        const newVals = Object.values(map).map(String).join(',');

        if (prevKeys === newKeys && prevVals === newVals) {
            return columnSortKeysRef.current;
        }

        columnSortKeysRef.current = map;

        return map;
    }, [columns]);

    // Sort data
    const sortedData = useMemo(() => {
        if (!sortKey) {
return filteredData;
}

        const actualSortKey = columnSortKeys[sortKey] || sortKey;

        return [...filteredData].sort((a, b) => {
            const aVal = a[actualSortKey as keyof T];
            const bVal = b[actualSortKey as keyof T];

            if (aVal === null || aVal === undefined) {
return 1;
}

            if (bVal === null || bVal === undefined) {
return -1;
}

            let comparison = 0;

            if (typeof aVal === 'number' && typeof bVal === 'number') {
                comparison = aVal - bVal;
            } else {
                comparison = String(aVal).localeCompare(String(bVal));
            }

            return sortDir === 'asc' ? comparison : -comparison;
        });
    }, [filteredData, sortKey, sortDir, columnSortKeys]);

    // Paginate data
    const totalPages = Math.ceil(sortedData.length / pageSize);
    const paginatedData = useMemo(() => {
        const start = (page - 1) * pageSize;

        return sortedData.slice(start, start + pageSize);
    }, [sortedData, page, pageSize]);

    // Track previous data to avoid unnecessary callbacks
    const prevPageDataRef = useRef<T[]>([]);
    const prevFilteredDataRef = useRef<T[]>([]);

    // Notify parent of page data changes (for selection support)
    useEffect(() => {
        if (onPageDataChange) {
            // Only call if data actually changed (by reference or length/content)
            const pageDataChanged = paginatedData !== prevPageDataRef.current;
            const filteredDataChanged = sortedData !== prevFilteredDataRef.current;

            if (pageDataChanged || filteredDataChanged) {
                prevPageDataRef.current = paginatedData;
                prevFilteredDataRef.current = sortedData;
                onPageDataChange(paginatedData, sortedData);
            }
        }
    }, [paginatedData, sortedData, onPageDataChange]);

    const handleSort = (key: string) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortDir('desc');
        }
    };

    const getSortIcon = (key: string) => {
        if (sortKey !== key) {
return <span className="text-xs">↕</span>;
}

        return sortDir === 'asc'
            ? <span className="text-xs">↑</span>
            : <span className="text-xs">↓</span>;
    };

    const handleSearch = (query: string) => {
        setSearch(query);
        setPage(1);
    };

    const handleClearFilter = (filterKey: string) => {
        const newFilters = { ...activeFilters };
        delete newFilters[filterKey];
        setActiveFilters(newFilters);
        setPage(1);
    };

    const handleClearAllFilters = () => {
        setActiveFilters({});
        setSearch('');
        setPage(1);
    };

    const handleLocalFilterChange = (key: string, value: string) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value,
        }));
    };

    const handleApplyFilters = () => {
        // Remove empty filters
        const cleanedFilters = Object.entries(localFilters).reduce(
            (acc, [key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    acc[key] = value;
                }

                return acc;
            },
            {} as Record<string, string>
        );

        setActiveFilters(cleanedFilters);
        setPage(1);
        setFiltersOpen(false);
    };

    const handleResetFilters = () => {
        setLocalFilters({});
        setActiveFilters({});
        setPage(1);
    };

    // Convert string to DateRange for daterange filters
    const parseDateRangeValue = (value: string | undefined): DateRange | undefined => {
        if (!value) {
return undefined;
}

        const [fromStr, toStr] = value.split('|');

        return {
            from: fromStr ? parseISO(fromStr) : undefined,
            to: toStr ? parseISO(toStr) : undefined,
        };
    };

    // Convert DateRange to string for storage
    const formatDateRangeValue = (range: DateRange | undefined): string => {
        if (!range?.from) {
return '';
}

        const from = format(range.from, 'yyyy-MM-dd');
        const to = range.to ? format(range.to, 'yyyy-MM-dd') : '';

        return to ? `${from}|${to}` : from;
    };

    const handleDateRangeChange = (key: string, range: DateRange | undefined) => {
        const value = formatDateRangeValue(range);
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value,
        }));
    };

    // Get filter display value
    const getFilterDisplayValue = (filter: LocalTableFilter<T>, value: string): string => {
        if (filter.type === 'select' && filter.options) {
            const option = filter.options.find((opt) => opt.value === value);

            return option?.label || value;
        } else if (filter.type === 'daterange' && value.includes('|')) {
            const [from, to] = value.split('|');
            const formatDate = (dateStr: string) => formatDateUtil(dateStr, dateStr);

            return to ? `${formatDate(from)} - ${formatDate(to)}` : formatDate(from);
        }

        return value;
    };

    // Export to CSV
    const handleExport = () => {
        setExporting(true);

        try {
            // Get exportable columns (columns with string headers that are not explicitly set to exportable: false)
            const exportColumns = columns.filter(
                (col) => typeof col.header === 'string' && col.header.trim() !== '' && col.exportable !== false
            );

            // Build CSV header
            const headers = exportColumns.map((col) => `"${(col.header as string).replace(/"/g, '""')}"`);
            const csvRows = [headers.join(',')];

            // Build CSV rows from sorted (filtered) data
            for (const row of sortedData) {
                const values = exportColumns.map((col) => {
                    let value: unknown;

                    // Use custom exportValue function if provided
                    if (col.exportValue) {
                        value = col.exportValue(row);
                    } else {
                        // Default: get value from row using column key
                        value = (row as Record<string, unknown>)[col.key];
                    }

                    // Handle null/undefined
                    if (value === null || value === undefined) {
                        return '';
                    }

                    // Convert to string and escape quotes
                    const strValue = String(value).replace(/"/g, '""');

                    return `"${strValue}"`;
                });
                csvRows.push(values.join(','));
            }

            // Create and download CSV
            const csvContent = csvRows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `${exportFilename}_${format(new Date(), 'yyyyMMdd_HHmmss')}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } finally {
            setExporting(false);
        }
    };

    return (
        <div className="space-y-4">
            {/* Toolbar */}
            <div className="flex items-center justify-between gap-4">
                <div className="flex flex-1 items-center gap-2">
                    {searchKeys.length > 0 && (
                        <div className="relative w-full max-w-sm">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder={searchPlaceholder}
                                value={search}
                                onChange={(e) => handleSearch(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                    )}
                    {filters.length > 0 && (
                        <Button variant="outline" size="sm" onClick={openFilters}>
                            <Filter className="mr-2 h-4 w-4" />
                            Filters
                            {activeFilterCount > 0 && (
                                <span className="ml-1 rounded-full bg-primary px-1.5 py-0.5 text-[10px] text-primary-foreground">
                                    {activeFilterCount}
                                </span>
                            )}
                        </Button>
                    )}
                </div>
                {showColumnToggle && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm" className="ml-auto">
                                <Settings2 className="mr-2 h-4 w-4" />
                                Columns
                                {hiddenColumnsCount > 0 && (
                                    <span className="ml-1 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium">
                                        {hiddenColumnsCount} hidden
                                    </span>
                                )}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <div className="max-h-64 overflow-y-auto">
                                {toggleableColumns.map((column) => (
                                    <DropdownMenuCheckboxItem
                                        key={column.key}
                                        checked={visibleColumns.has(column.key)}
                                        onCheckedChange={() => toggleColumn(column.key)}
                                        onSelect={(e) => e.preventDefault()}
                                        disabled={visibleColumns.size === 1 && visibleColumns.has(column.key)}
                                    >
                                        {column.header}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </div>
                            <DropdownMenuSeparator />
                            <div className="flex gap-1 p-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="flex-1 text-xs"
                                    onClick={resetColumns}
                                >
                                    Reset
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="flex-1 text-xs"
                                    onClick={showAllColumns}
                                >
                                    Show all
                                </Button>
                            </div>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                {exportable && (
                    <Button variant="outline" size="sm" onClick={handleExport} disabled={exporting}>
                        {exporting ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Download className="mr-2 h-4 w-4" />
                        )}
                        Export
                    </Button>
                )}
            </div>

            {/* Active Filters */}
            {hasActiveFilters && (
                <div className="flex flex-wrap items-center gap-2 rounded-lg border border-dashed p-4">
                    <span className="text-sm font-medium">Active filters:</span>
                    {search && (
                        <div className="inline-flex items-center gap-1 rounded-md border px-2.5 py-0.5 text-xs font-semibold">
                            Search: {search}
                            <button
                                onClick={() => handleSearch('')}
                                className="ml-1 hover:text-destructive"
                            >
                                ×
                            </button>
                        </div>
                    )}
                    {Object.entries(activeFilters).map(([key, value]) => {
                        const filter = filters.find((f) => String(f.key) === key);

                        if (!filter || !value) {
return null;
}

                        const displayValue = getFilterDisplayValue(filter, value);

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
            <div className="rounded-md border overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {visibleColumnsList.map((col) => (
                                <TableHead
                                    key={col.key}
                                    className={cn(
                                        col.sortable && 'cursor-pointer select-none hover:bg-muted/50',
                                        col.className
                                    )}
                                    onClick={() => col.sortable && handleSort(col.key)}
                                >
                                    <div className="flex items-center gap-2">
                                        {col.header}
                                        {col.sortable && getSortIcon(col.key)}
                                    </div>
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {paginatedData.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={visibleColumnsList.length} className="h-24 text-center text-muted-foreground">
                                    {emptyMessage}
                                </TableCell>
                            </TableRow>
                        ) : (
                            paginatedData.map((row, idx) => (
                                <TableRow key={idx}>
                                    {visibleColumnsList.map((col) => (
                                        <TableCell key={col.key} className={col.className}>
                                            {col.render(row)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
                <div className="flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        Showing {((page - 1) * pageSize) + 1} - {Math.min(page * pageSize, sortedData.length)} of {sortedData.length}
                        {filteredData.length !== data.length && ` (filtered from ${data.length})`}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPage(1)}
                            disabled={page === 1}
                        >
                            <ChevronsLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPage(page - 1)}
                            disabled={page === 1}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <span className="text-sm">
                            Page {page} of {totalPages}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPage(page + 1)}
                            disabled={page >= totalPages}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPage(totalPages)}
                            disabled={page >= totalPages}
                        >
                            <ChevronsRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
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
                    <div className="mb-4 flex h-full flex-col overflow-y-auto">
                        <div className="flex-1 space-y-4 px-2 py-6">
                            {filters.map((filter) => (
                                <div key={String(filter.key)} className="space-y-2">
                                    <Label htmlFor={String(filter.key)} className="font-medium">
                                        {filter.label}
                                    </Label>
                                    {filter.type === 'text' && (
                                        <Input
                                            id={String(filter.key)}
                                            placeholder={filter.placeholder}
                                            value={localFilters[String(filter.key)] || ''}
                                            onChange={(e) =>
                                                handleLocalFilterChange(String(filter.key), e.target.value)
                                            }
                                        />
                                    )}
                                    {(filter.type === 'select' || !filter.type) && filter.options && (
                                        <Select
                                            value={localFilters[String(filter.key)] || ''}
                                            onValueChange={(value) =>
                                                handleLocalFilterChange(String(filter.key), value === '__clear__' ? '' : value)
                                            }
                                        >
                                            <SelectTrigger id={String(filter.key)} className="w-full">
                                                <SelectValue placeholder={filter.placeholder || 'Select...'} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__clear__">
                                                    {filter.placeholder || 'All'}
                                                </SelectItem>
                                                {filter.options.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                    {filter.type === 'daterange' && (
                                        <DateRangePicker
                                            value={parseDateRangeValue(localFilters[String(filter.key)])}
                                            onChange={(range) => handleDateRangeChange(String(filter.key), range)}
                                            placeholder={filter.placeholder || 'Select date range'}
                                        />
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="flex gap-2 border-t px-1 pt-4">
                            <Button
                                variant="outline"
                                className="flex-1"
                                onClick={handleResetFilters}
                            >
                                Reset
                            </Button>
                            <Button className="flex-1" onClick={handleApplyFilters}>
                                Apply Filters
                            </Button>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}
