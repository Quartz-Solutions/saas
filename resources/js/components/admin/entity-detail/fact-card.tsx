import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type FactCardProps = {
    title: ReactNode;
    description?: ReactNode;
    /** Optional right-aligned slot in the header (e.g. a "View all" link). */
    headerExtra?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
};

export function FactCard({
    title,
    description,
    headerExtra,
    children,
    className,
    contentClassName,
}: FactCardProps) {
    return (
        <Card data-test="fact-card" className={cn('gap-3 py-4', className)}>
            <CardHeader className="px-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex flex-col gap-1">
                        <CardTitle className="text-sm font-semibold">{title}</CardTitle>
                        {description && (
                            <CardDescription className="text-xs">
                                {description}
                            </CardDescription>
                        )}
                    </div>
                    {headerExtra && <div className="shrink-0">{headerExtra}</div>}
                </div>
            </CardHeader>
            <CardContent className={cn('px-4 text-sm', contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}

export type FactRow = [label: ReactNode, value: ReactNode];

export function FactGrid({
    rows,
    className,
}: {
    rows: ReadonlyArray<FactRow | null | false | '' | undefined>;
    className?: string;
}) {
    const filtered = rows.filter(Boolean) as FactRow[];

    return (
        <dl
            data-test="fact-grid"
            className={cn('grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2', className)}
        >
            {filtered.map(([label, value], i) => (
                <FactRowItem key={i} label={label} value={value} />
            ))}
        </dl>
    );
}

function FactRowItem({ label, value }: { label: ReactNode; value: ReactNode }) {
    return (
        <>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm break-words">{value ?? '—'}</dd>
        </>
    );
}

export function Mono({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <code
            className={cn(
                'rounded bg-muted px-1.5 py-0.5 font-mono text-xs',
                className,
            )}
        >
            {children}
        </code>
    );
}
