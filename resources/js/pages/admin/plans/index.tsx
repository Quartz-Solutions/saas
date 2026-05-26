import { Head, Link, router } from '@inertiajs/react';
import { Archive, MoreHorizontal, Plus, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import {
    DataTable,
} from '@/components/data-table/data-table';
import type {
    DataTableColumn,
    DataTableFilter,
    PaginationData,
} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/utils';
import PlansController from '@/actions/App/Http/Controllers/Admin/PlansController';
import {
    create as plansCreate,
    edit as plansEdit,
    index as plansIndex,
} from '@/routes/admin/plans';

type PlanRow = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    price_cents: number;
    currency: string;
    billing_period: string;
    billing_interval: number;
    trial_days: number;
    features: string[];
    gateway_ids: Record<string, string | null>;
    is_active: boolean;
    is_public: boolean;
    sort_order: number;
    deleted_at: string | null;
    created_at: string | null;
    active_subscriptions_count: number;
};

type Props = {
    plans: {
        data: PlanRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

const formatMoney = (cents: number, currency: string) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
};

const formatCadence = (period: string, interval: number) => {
    if (period === 'one_time') return 'one-time';
    return interval === 1 ? `/${period}` : `/${interval} ${period}s`;
};

export default function PlansIndex({ plans, tableState }: Props) {
    const [archiving, setArchiving] = useState<PlanRow | null>(null);

    const reload = (params: {
        search?: string;
        filters?: Record<string, string>;
        sort?: { column: string; direction: 'asc' | 'desc' };
        page?: number;
    }) => {
        const data: Record<string, string | number | Record<string, string>> = {};
        if (params.search !== undefined && params.search !== '') data.search = params.search;
        if (params.filters && Object.keys(params.filters).length > 0) data.filter = params.filters;
        if (params.sort) {
            data.sort = params.sort.column;
            data.direction = params.sort.direction;
        }
        if (params.page && params.page > 1) data.page = params.page;

        router.get(plansIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['plans', 'tableState'],
        });
    };

    const columns: DataTableColumn<PlanRow>[] = [
        {
            key: 'name',
            header: 'Plan',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <Link
                        href={plansEdit({ plan: row.id })}
                        className="font-medium hover:underline"
                    >
                        {row.name}
                    </Link>
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.slug}
                    </span>
                </div>
            ),
        },
        {
            key: 'price_cents',
            header: 'Price',
            sortable: true,
            render: (row) => (
                <div className="font-mono text-sm">
                    {row.price_cents === 0 ? (
                        <span className="text-muted-foreground">Free</span>
                    ) : (
                        <>
                            {formatMoney(row.price_cents, row.currency)}
                            <span className="text-muted-foreground">
                                {formatCadence(row.billing_period, row.billing_interval)}
                            </span>
                        </>
                    )}
                </div>
            ),
        },
        {
            key: 'trial_days',
            header: 'Trial',
            render: (row) =>
                row.trial_days > 0 ? (
                    <span className="text-sm">{row.trial_days}d</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) =>
                row.deleted_at ? (
                    <Badge variant="outline" className="text-muted-foreground">Archived</Badge>
                ) : row.is_active ? (
                    row.is_public ? (
                        <Badge variant="default">Public</Badge>
                    ) : (
                        <Badge variant="secondary">Private</Badge>
                    )
                ) : (
                    <Badge variant="outline">Inactive</Badge>
                ),
        },
        {
            key: 'gateway_ids',
            header: 'Stripe',
            render: (row) => {
                const id = row.gateway_ids?.stripe;
                return id ? (
                    <span className="font-mono text-xs text-muted-foreground" title={id}>
                        {id.slice(0, 16)}…
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                );
            },
        },
        {
            key: 'active_subscriptions_count',
            header: 'Active subs',
            render: (row) => <span className="text-sm">{row.active_subscriptions_count}</span>,
        },
        {
            key: 'sort_order',
            header: 'Order',
            sortable: true,
            render: (row) => <span className="font-mono text-xs">{row.sort_order}</span>,
        },
        {
            key: 'created_at',
            header: 'Created',
            sortable: true,
            render: (row) =>
                row.created_at ? (
                    <span className="font-mono text-xs text-muted-foreground">
                        {formatDateTime(row.created_at)}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            className: 'w-px text-right',
            render: (row) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" className="size-8">
                            <MoreHorizontal />
                            <span className="sr-only">Open actions</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={plansEdit({ plan: row.id })}>Edit</Link>
                        </DropdownMenuItem>
                        {row.deleted_at ? (
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.post(`/admin/plans/${row.id}/restore`, {}, { preserveScroll: true })
                                }
                            >
                                <RotateCcw className="size-4" />
                                Restore
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setArchiving(row)}
                            >
                                <Archive className="size-4" />
                                Archive
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const filters: DataTableFilter[] = [
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            placeholder: 'Active only',
            options: [{ label: 'Show archived', value: 'archived' }],
        },
        {
            key: 'is_public',
            label: 'Visibility',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Public', value: 'yes' },
                { label: 'Private', value: 'no' },
            ],
        },
        {
            key: 'billing_period',
            label: 'Cadence',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Monthly', value: 'month' },
                { label: 'Yearly', value: 'year' },
                { label: 'Weekly', value: 'week' },
                { label: 'Daily', value: 'day' },
                { label: 'One-time', value: 'one_time' },
            ],
        },
    ];

    return (
        <>
            <Head title="Plans — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Plans"
                        description="Pricing tiers — source-of-truth for /pricing and the subscribe flow. Stripe Prices are auto-created when you save."
                    />
                    <Button asChild>
                        <Link href={plansCreate()}>
                            <Plus className="size-4" />
                            New plan
                        </Link>
                    </Button>
                </div>

                <DataTable<PlanRow>
                    tableId="admin-plans-index"
                    data={plans.data}
                    pagination={plans.meta}
                    columns={columns}
                    filters={filters}
                    initialSearch={tableState.search}
                    initialFilters={tableState.filters}
                    initialSort={tableState.sort}
                    onSearch={(search) => reload({ search })}
                    onFilter={(f) => reload({ filters: f, search: tableState.search })}
                    onClearAll={() => reload({})}
                    onSort={(column, direction) =>
                        reload({
                            search: tableState.search,
                            filters: tableState.filters,
                            sort: { column, direction },
                        })
                    }
                    onPageChange={(page) =>
                        reload({
                            search: tableState.search,
                            filters: tableState.filters,
                            sort: tableState.sort,
                            page,
                        })
                    }
                />
            </div>

            <ArchiveDialog plan={archiving} onClose={() => setArchiving(null)} />
        </>
    );
}

function ArchiveDialog({ plan, onClose }: { plan: PlanRow | null; onClose: () => void }) {
    const open = plan !== null;
    const blocked = (plan?.active_subscriptions_count ?? 0) > 0;

    return (
        <AlertDialog open={open} onOpenChange={(v) => !v && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Archive plan?</AlertDialogTitle>
                    <AlertDialogDescription>
                        {blocked ? (
                            <>
                                <strong>{plan?.name}</strong> has{' '}
                                {plan?.active_subscriptions_count} active subscription
                                {plan?.active_subscriptions_count === 1 ? '' : 's'}. Migrate them
                                to another plan before archiving.
                            </>
                        ) : (
                            <>
                                Archiving hides <strong>{plan?.name}</strong> from /pricing and
                                blocks new sign-ups. Existing subscriptions (if any) keep billing
                                normally.
                            </>
                        )}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel asChild>
                        <Button variant="secondary">Cancel</Button>
                    </AlertDialogCancel>
                    {!blocked && plan ? (
                        <AlertDialogAction asChild>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    router.delete(PlansController.destroy.url({ plan: plan.id }), {
                                        onFinish: onClose,
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                Archive
                            </Button>
                        </AlertDialogAction>
                    ) : null}
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
