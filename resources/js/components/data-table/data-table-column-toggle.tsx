import { Settings2 } from 'lucide-react';

import type { DataTableColumn } from './data-table';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';


interface DataTableColumnToggleProps<T> {
    columns: DataTableColumn<T>[];
    visibleColumns: Set<string>;
    onToggleColumn: (columnKey: string) => void;
    onResetColumns: () => void;
    onShowAllColumns: () => void;
}

export function DataTableColumnToggle<T>({
    columns,
    visibleColumns,
    onToggleColumn,
    onResetColumns,
    onShowAllColumns,
}: DataTableColumnToggleProps<T>) {
    // Filter out columns without headers (like action columns) or with function headers (like select columns)
    const toggleableColumns = columns.filter((col) => {
        if (!col.header) return false;
        if (typeof col.header === 'function') return false;
        return col.header.trim() !== '';
    });
    const hiddenCount = toggleableColumns.filter((col) => !visibleColumns.has(col.key)).length;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="ml-auto">
                    <Settings2 className="h-4 w-4" />
                    {hiddenCount > 0 && (
                        <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium">
                            {hiddenCount} hidden
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
                            onCheckedChange={() => onToggleColumn(column.key)}
                            onSelect={(e) => e.preventDefault()}
                            disabled={visibleColumns.size === 1 && visibleColumns.has(column.key)}
                        >
                            {typeof column.header === 'function' ? column.key : column.header}
                        </DropdownMenuCheckboxItem>
                    ))}
                </div>
                <DropdownMenuSeparator />
                <div className="flex gap-1 p-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="flex-1 text-xs"
                        onClick={onResetColumns}
                    >
                        Reset
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="flex-1 text-xs"
                        onClick={onShowAllColumns}
                    >
                        Show all
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
