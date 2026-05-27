import { cn } from '@/lib/utils';

export type EntityStatus =
    | 'active'
    | 'trialing'
    | 'past_due'
    | 'suspended'
    | 'archived'
    | 'canceled'
    | 'cancelled'
    | 'deleted'
    | 'unverified'
    | 'verified'
    | 'pending'
    | 'neutral';

const variantMap: Record<EntityStatus, string> = {
    active: 'bg-emerald-500',
    trialing: 'bg-sky-500',
    past_due: 'bg-amber-500',
    suspended: 'bg-rose-500',
    archived: 'bg-zinc-400',
    canceled: 'bg-zinc-400',
    cancelled: 'bg-zinc-400',
    deleted: 'bg-rose-500',
    unverified: 'bg-amber-500',
    verified: 'bg-emerald-500',
    pending: 'bg-sky-500',
    neutral: 'bg-zinc-400',
};

export function StatusDot({
    status,
    className,
}: {
    status: EntityStatus | string | null;
    className?: string;
}) {
    const key = (status ?? 'neutral') as EntityStatus;
    const cls = variantMap[key] ?? 'bg-zinc-400';

    return (
        <span
            data-test="status-dot"
            data-status={status ?? 'neutral'}
            className={cn(
                'inline-block size-2 rounded-full ring-2 ring-background',
                cls,
                className,
            )}
            aria-hidden="true"
        />
    );
}
