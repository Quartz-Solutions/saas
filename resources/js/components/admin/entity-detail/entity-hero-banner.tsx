import type { ComponentProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type HeroPill = {
    label: string;
    variant?: ComponentProps<typeof Badge>['variant'];
    className?: string;
};

export type EntityHeroBannerProps = {
    /** Small uppercase label, e.g. "Subscription". */
    label: string;
    /** Big metric, e.g. "$29 / month". */
    value: React.ReactNode;
    /** Optional status pill rendered next to the value. */
    pill?: HeroPill;
    /** Helper line under the metric. */
    helper?: React.ReactNode;
    /** Right-aligned slot for quick-action chips. */
    actions?: React.ReactNode;
    className?: string;
};

export function EntityHeroBanner({
    label,
    value,
    pill,
    helper,
    actions,
    className,
}: EntityHeroBannerProps) {
    return (
        <div
            data-test="entity-hero-banner"
            className={cn(
                'flex flex-col gap-3 rounded-lg border bg-gradient-to-br from-muted/30 to-transparent p-5 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="flex flex-col gap-1">
                <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    {label}
                </span>
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-2xl font-semibold tracking-tight tabular-nums">
                        {value}
                    </span>
                    {pill && (
                        <Badge
                            variant={pill.variant ?? 'outline'}
                            className={pill.className}
                            data-test="entity-hero-pill"
                        >
                            {pill.label}
                        </Badge>
                    )}
                </div>
                {helper && (
                    <span className="text-sm text-muted-foreground">{helper}</span>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">{actions}</div>
            )}
        </div>
    );
}
