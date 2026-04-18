import { Download, Filter, Loader2, Search } from 'lucide-react';

import type { DataTableColumn } from './data-table';
import { DataTableColumnToggle } from './data-table-column-toggle';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';


interface DataTableToolbarProps<T> {
    searchQuery: string;
    onSearch: (query: string) => void;
    onOpenFilters: () => void;
    hasFilters: boolean;
    columns?: DataTableColumn<T>[];
    visibleColumns?: Set<string>;
    onToggleColumn?: (columnKey: string) => void;
    onResetColumns?: () => void;
    onShowAllColumns?: () => void;
    showColumnToggle?: boolean;
    onExport?: () => void;
    exporting?: boolean;
}

export function DataTableToolbar<T>({
    searchQuery,
    onSearch,
    onOpenFilters,
    hasFilters,
    columns,
    visibleColumns,
    onToggleColumn,
    onResetColumns,
    onShowAllColumns,
    showColumnToggle = true,
    onExport,
    exporting = false,
}: DataTableToolbarProps<T>) {
    const canShowColumnToggle =
        showColumnToggle &&
        columns &&
        visibleColumns &&
        onToggleColumn &&
        onResetColumns &&
        onShowAllColumns;

    return (
        <div className="flex items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2">
                <div className="relative w-full max-w-sm">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Search..."
                        value={searchQuery}
                        onChange={(e) => onSearch(e.target.value)}
                        className="pl-9"
                    />
                </div>

            </div>
            {hasFilters && (
                <Button variant="outline" size="sm" onClick={onOpenFilters}>
                    <Filter className="h-4 w-4" />
                    Filters
                </Button>
            )}
            {canShowColumnToggle && (
                <DataTableColumnToggle
                    columns={columns}
                    visibleColumns={visibleColumns}
                    onToggleColumn={onToggleColumn}
                    onResetColumns={onResetColumns}
                    onShowAllColumns={onShowAllColumns}
                />
            )}
            {onExport && (
                <Button variant="outline" size="sm" onClick={onExport} disabled={exporting}>
                    {exporting ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                        <Download className="h-4 w-4" />
                    )}
                    Export
                </Button>
            )}
        </div>
    );
}
