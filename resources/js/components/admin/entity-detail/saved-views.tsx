import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type SavedView = {
    value: string;
    label: string;
    count?: number | null;
    hidden?: boolean;
};

export type SavedViewsProps = {
    views: SavedView[];
    value: string | null;
    onChange: (value: string | null) => void;
    /** Whether the "All" pseudo-view is included (null value). Default true. */
    includeAll?: boolean;
    allLabel?: string;
    allCount?: number | null;
    className?: string;
    /** Right-aligned slot (often the bulk-action toolbar). */
    extra?: ReactNode;
};

export function SavedViews({
    views,
    value,
    onChange,
    includeAll = true,
    allLabel = 'All',
    allCount,
    className,
    extra,
}: SavedViewsProps) {
    const visible = views.filter((v) => !v.hidden);

    return (
        <div
            data-test="saved-views"
            className={cn(
                'flex flex-wrap items-center justify-between gap-3',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-1">
                {includeAll && (
                    <ViewChip
                        active={value === null}
                        onClick={() => onChange(null)}
                        count={allCount}
                        testId="view-all"
                    >
                        {allLabel}
                    </ViewChip>
                )}
                {visible.map((v) => (
                    <ViewChip
                        key={v.value}
                        active={value === v.value}
                        onClick={() => onChange(v.value)}
                        count={v.count}
                        testId={`view-${v.value}`}
                    >
                        {v.label}
                    </ViewChip>
                ))}
            </div>
            {extra && <div className="flex items-center gap-2">{extra}</div>}
        </div>
    );
}

function ViewChip({
    children,
    active,
    onClick,
    count,
    testId,
}: {
    children: ReactNode;
    active: boolean;
    onClick: () => void;
    count?: number | null;
    testId: string;
}) {
    return (
        <Button
            type="button"
            variant={active ? 'default' : 'ghost'}
            size="sm"
            onClick={onClick}
            data-test={testId}
            className={cn(
                'h-8 gap-1.5 rounded-full px-3 text-xs',
                active && 'shadow-sm',
            )}
        >
            <span>{children}</span>
            {count !== undefined && count !== null && (
                <span
                    className={cn(
                        'rounded-full px-1.5 text-[10px] tabular-nums',
                        active
                            ? 'bg-primary-foreground/15 text-primary-foreground'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {count}
                </span>
            )}
        </Button>
    );
}
