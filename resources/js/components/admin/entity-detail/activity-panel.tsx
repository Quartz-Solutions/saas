import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type ActivityColumn<T> = {
    key: string;
    header: ReactNode;
    render: (row: T) => ReactNode;
    className?: string;
    headerClassName?: string;
};

export type ActivityPanelProps<T> = {
    title: ReactNode;
    description?: ReactNode;
    /** Optional deep-link to a full list. */
    viewAllHref?: string;
    viewAllLabel?: string;
    columns: ActivityColumn<T>[];
    rows: T[];
    /** Message when rows is empty. */
    emptyMessage?: string;
    /** Stable key for each row. */
    rowKey: (row: T, idx: number) => string | number;
    className?: string;
    /** Optional small-density spacing. */
    compact?: boolean;
};

export function ActivityPanel<T>({
    title,
    description,
    viewAllHref,
    viewAllLabel = 'View all',
    columns,
    rows,
    emptyMessage = 'No activity yet.',
    rowKey,
    className,
    compact = true,
}: ActivityPanelProps<T>) {
    return (
        <Card
            data-test="activity-panel"
            className={cn('gap-2 py-4', className)}
        >
            <CardHeader className="px-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex flex-col gap-0.5">
                        <CardTitle className="text-sm font-semibold">{title}</CardTitle>
                        {description && (
                            <span className="text-xs text-muted-foreground">
                                {description}
                            </span>
                        )}
                    </div>
                    {viewAllHref && (
                        <Link
                            href={viewAllHref}
                            className="flex items-center gap-0.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                            data-test="activity-view-all"
                        >
                            {viewAllLabel}
                            <ChevronRight className="size-3" />
                        </Link>
                    )}
                </div>
            </CardHeader>
            <CardContent className="px-0 pb-0">
                {rows.length === 0 ? (
                    <p className="px-4 pb-3 text-sm text-muted-foreground">
                        {emptyMessage}
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-b">
                                    {columns.map((c) => (
                                        <TableHead
                                            key={c.key}
                                            className={cn(
                                                'h-8 px-4 text-[11px] font-medium uppercase tracking-wide text-muted-foreground',
                                                c.headerClassName,
                                            )}
                                        >
                                            {c.header}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, idx) => (
                                    <TableRow key={rowKey(row, idx)}>
                                        {columns.map((c) => (
                                            <TableCell
                                                key={c.key}
                                                className={cn(
                                                    compact ? 'px-4 py-2 text-sm' : 'px-4 py-3 text-sm',
                                                    c.className,
                                                )}
                                            >
                                                {c.render(row)}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
