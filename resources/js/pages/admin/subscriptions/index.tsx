import { Head, Link, router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import {
    DataTable,
} from '@/components/data-table/data-table';
import type {
    DataTableColumn,
    DataTableFilter,
    PaginationData,
} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/utils';
import {
    index as subsIndex,
    show as subsShow,
} from '@/routes/admin/subscriptions';

type SubRow = {
    id: number;
    gateway: string;
    gateway_subscription_id: string | null;
    status: string;
    currency: string;
    unit_amount_cents: number;
    quantity: number;
    trial_ends_at: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
    canceled_at: string | null;
    created_at: string | null;
    tenant: {
        id: number;
        slug: string;
        name: string;
        owner: { id: number; name: string; email: string } | null;
    } | null;
    plan: {
        id: number;
        slug: string;
        name: string;
        billing_period: string;
        billing_interval: number;
    } | null;
};

type Stats = {
    active: number;
    trialing: number;
    past_due: number;
    canceled_30d: number;
    mrr_cents: number;
};

type Plan = { id: number; slug: string; name: string };

type Props = {
    subscriptions: { data: SubRow[]; meta: PaginationData };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
    stats: Stats;
    plans: Plan[];
};

const formatMoney = (cents: number, currency: string) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    active: 'default',
    trialing: 'secondary',
    past_due: 'destructive',
    canceled: 'outline',
    unpaid: 'destructive',
    paused: 'outline',
    incomplete: 'outline',
    incomplete_expired: 'outline',
};

export default function AdminSubscriptionsIndex({
    subscriptions,
    tableState,
    stats,
    plans,
}: Props) {
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

        router.get(subsIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['subscriptions', 'tableState'],
        });
    };

    const columns: DataTableColumn<SubRow>[] = [
        {
            key: 'tenant',
            header: 'Tenant',
            render: (row) => (
                <div className="flex flex-col">
                    <Link
                        href={subsShow({ subscription: row.id })}
                        className="font-medium hover:underline"
                    >
                        {row.tenant?.name ?? '—'}
                    </Link>
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.tenant?.slug ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'plan',
            header: 'Plan',
            render: (row) => (
                <div className="flex flex-col">
                    <span className="text-sm">{row.plan?.name ?? '—'}</span>
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.plan?.slug ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col gap-1">
                    <Badge variant={STATUS_VARIANT[row.status] ?? 'outline'}>
                        {row.status}
                    </Badge>
                    {row.cancel_at_period_end ? (
                        <span className="text-xs text-muted-foreground">cancels at period end</span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'unit_amount_cents',
            header: 'Price',
            sortable: true,
            render: (row) => (
                <span className="font-mono text-sm">
                    {row.unit_amount_cents === 0
                        ? <span className="text-muted-foreground">Free</span>
                        : formatMoney(row.unit_amount_cents * row.quantity, row.currency)}
                </span>
            ),
        },
        {
            key: 'gateway',
            header: 'Gateway',
            render: (row) => <span className="text-xs">{row.gateway}</span>,
        },
        {
            key: 'current_period_end',
            header: 'Period ends',
            sortable: true,
            render: (row) =>
                row.current_period_end ? (
                    <span className="font-mono text-xs text-muted-foreground">
                        {formatDateTime(row.current_period_end)}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'created_at',
            header: 'Signed up',
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
                <Button variant="ghost" size="sm" asChild>
                    <Link href={subsShow({ subscription: row.id })}>
                        <FileText className="size-4" />
                        Open
                    </Link>
                </Button>
            ),
        },
    ];

    const filters: DataTableFilter[] = [
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Active', value: 'active' },
                { label: 'Trialing', value: 'trialing' },
                { label: 'Past due', value: 'past_due' },
                { label: 'Canceled', value: 'canceled' },
                { label: 'Unpaid', value: 'unpaid' },
                { label: 'Paused', value: 'paused' },
            ],
        },
        {
            key: 'plan',
            label: 'Plan',
            type: 'select',
            placeholder: 'Any',
            options: plans.map((p) => ({ label: p.name, value: String(p.id) })),
        },
        {
            key: 'gateway',
            label: 'Gateway',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Stripe', value: 'stripe' },
                { label: 'Free (internal)', value: 'free' },
                { label: 'Manual', value: 'manual' },
            ],
        },
    ];

    return (
        <>
            <Head title="Subscriptions — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Subscriptions"
                        description="Every tenant subscription across all gateways."
                    />
                    <Button variant="outline" asChild>
                        <a href="/admin/subscriptions/export">
                            <Download className="size-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                    <StatCard label="MRR" value={formatMoney(stats.mrr_cents, 'USD')} subtle />
                    <StatCard label="Active" value={String(stats.active)} />
                    <StatCard label="Trialing" value={String(stats.trialing)} />
                    <StatCard label="Past due" value={String(stats.past_due)} accent="warning" />
                    <StatCard label="Canceled (30d)" value={String(stats.canceled_30d)} subtle />
                </div>

                <DataTable<SubRow>
                    tableId="admin-subscriptions-index"
                    data={subscriptions.data}
                    pagination={subscriptions.meta}
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
        </>
    );
}

function StatCard({
    label,
    value,
    accent,
    subtle,
}: {
    label: string;
    value: string;
    accent?: 'warning';
    subtle?: boolean;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle
                    className={
                        subtle
                            ? 'text-xs font-normal text-muted-foreground'
                            : 'text-xs font-medium text-muted-foreground'
                    }
                >
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    className={
                        accent === 'warning'
                            ? 'font-mono text-2xl font-semibold text-amber-600'
                            : 'font-mono text-2xl font-semibold'
                    }
                >
                    {value}
                </div>
            </CardContent>
        </Card>
    );
}
