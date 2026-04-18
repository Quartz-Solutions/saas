import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';

import { Button } from '@/components/ui/button';

interface DataTablePaginationProps {
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    onPageChange?: (page: number) => void;
}

export function DataTablePagination({
    pagination,
    onPageChange,
}: DataTablePaginationProps) {
    const { current_page, last_page, total, from, to } = pagination;

    return (
        <div className="flex items-center justify-between px-2">
            <div className="text-sm text-muted-foreground">
                Showing {from || 0} to {to || 0} of {total} results
            </div>
            <div className="flex items-center space-x-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange?.(1)}
                    disabled={current_page === 1}
                >
                    <ChevronsLeft className="h-4 w-4" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange?.(current_page - 1)}
                    disabled={current_page === 1}
                >
                    <ChevronLeft className="h-4 w-4" />
                </Button>
                <div className="text-sm font-medium">
                    Page {current_page} of {last_page || 1}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange?.(current_page + 1)}
                    disabled={current_page === last_page || last_page === 0}
                >
                    <ChevronRight className="h-4 w-4" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onPageChange?.(last_page)}
                    disabled={current_page === last_page || last_page === 0}
                >
                    <ChevronsRight className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}
