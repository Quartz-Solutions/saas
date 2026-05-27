import { Head, Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
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
    index as sessionsIndex,
    show as sessionsShow,
} from '@/routes/admin/checkout-sessions';

type Row = {
    id: number;
    public_id: string;
    intent: string;
    status: string;
    gateway: string | null;
    currency: string;
    amount_cents: number;
    result_kind: string | null;
    created_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
    tenant: { id: number; slug: string; name: string } | null;
    plan: { slug: string; name: string } | null;
    user: { id: number; name: string; email: string } | null;
};

type Stats = {
    pending: number;
    awaiting_payment: number;
    completed_30d: number;
    expired_30d: number;
};

type Props = {
    sessions: { data: Row[]; meta: PaginationData };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
    stats: Stats;
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    awaiting_payment: 'secondary',
    completed: 'default',
    failed: 'destructive',
    canceled: 'outline',
    expired: 'outline',
};

const formatMoney = (cents: number, currency: string) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
};

export default function AdminCheckoutSessionsIndex({ sessions, tableState, stats }: Props) {
    const reload = (params: {
        search?: string;
        filters?: Record<string, string>;
        sort?: { column: string; direction: 'asc' | 'desc' };
        page?: number;
    }) => {
        const data: Record<string, string | number | Record<string, string>> = {};

        if (params.search !== undefined && params.search !== '') {
data.search = params.search;
}

        if (params.filters && Object.keys(params.filters).length > 0) {
data.filter = params.filters;
}

        if (params.sort) {
            data.sort = params.sort.column;
            data.direction = params.sort.direction;
        }

        if (params.page && params.page > 1) {
data.page = params.page;
}

        router.get(sessionsIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['sessions', 'tableState'],
        });
    };

    const columns: DataTableColumn<Row>[] = [
        {
            key: 'public_id',
            header: 'Session',
            render: (row) => (
                <div className="flex flex-col">
                    <Link
                        href={sessionsShow({ checkoutSession: row.public_id })}
                        className="font-mono text-xs hover:underline"
                    >
                        {row.public_id.slice(0, 16)}…
                    </Link>
                    <span className="text-xs text-muted-foreground">{row.intent}</span>
                </div>
            ),
        },
        {
            key: 'tenant',
            header: 'Tenant',
            render: (row) => (
                <div className="flex flex-col">
                    <span className="text-sm">{row.tenant?.name ?? '—'}</span>
                    <span className="font-mono text-xs text-muted-foreground">{row.tenant?.slug ?? '—'}</span>
                </div>
            ),
        },
        {
            key: 'plan',
            header: 'Plan',
            render: (row) => <span className="text-sm">{row.plan?.name ?? '—'}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            render: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'outline'}>{row.status}</Badge>,
        },
        {
            key: 'gateway',
            header: 'Gateway',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <span className="text-xs">{row.gateway ?? '—'}</span>
                    {row.result_kind ? (
                        <span className="text-xs text-muted-foreground">{row.result_kind}</span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'amount_cents',
            header: 'Amount',
            sortable: true,
            render: (row) => (
                <span className="font-mono text-sm">
                    {row.amount_cents === 0 ? (
                        <span className="text-muted-foreground">Free</span>
                    ) : (
                        formatMoney(row.amount_cents, row.currency)
                    )}
                </span>
            ),
        },
        {
            key: 'created_at',
            header: 'Started',
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
                    <Link href={sessionsShow({ checkoutSession: row.public_id })}>
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
                { label: 'Pending', value: 'pending' },
                { label: 'Awaiting payment', value: 'awaiting_payment' },
                { label: 'Completed', value: 'completed' },
                { label: 'Failed', value: 'failed' },
                { label: 'Canceled', value: 'canceled' },
                { label: 'Expired', value: 'expired' },
            ],
        },
        {
            key: 'gateway',
            label: 'Gateway',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Stripe', value: 'stripe' },
                { label: 'PayPal', value: 'paypal' },
                { label: 'Paymob', value: 'paymob' },
                { label: 'PayTabs', value: 'paytabs' },
                { label: 'Geidea', value: 'geidea' },
                { label: 'APS', value: 'aps' },
                { label: 'Telr', value: 'telr' },
                { label: 'HyperPay', value: 'hyperpay' },
                { label: 'MyFatoorah', value: 'myfatoorah' },
                { label: 'HitPay', value: 'hitpay' },
                { label: 'Billplz', value: 'billplz' },
                { label: 'iPay88', value: 'ipay88' },
                { label: 'Fawry', value: 'fawry' },
                { label: 'Free (internal)', value: 'free' },
            ],
        },
        {
            key: 'intent',
            label: 'Intent',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Subscription', value: 'subscription' },
                { label: 'One-time', value: 'one_time' },
            ],
        },
    ];

    return (
        <>
            <Head title="Checkout sessions — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <Heading
                    title="Checkout sessions"
                    description="Every checkout attempt — open ones, completed ones, and the ones that walked away."
                />

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Pending" value={String(stats.pending)} />
                    <StatCard label="Awaiting payment" value={String(stats.awaiting_payment)} accent="warning" />
                    <StatCard label="Completed (30d)" value={String(stats.completed_30d)} />
                    <StatCard label="Expired (30d)" value={String(stats.expired_30d)} subtle />
                </div>

                <DataTable<Row>
                    tableId="admin-checkout-sessions-index"
                    data={sessions.data}
                    pagination={sessions.meta}
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
                        'text-sm font-medium ' +
                        (accent === 'warning'
                            ? 'text-amber-600 dark:text-amber-400'
                            : subtle
                              ? 'text-muted-foreground'
                              : '')
                    }
                >
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <span className="text-2xl font-semibold tabular-nums">{value}</span>
            </CardContent>
        </Card>
    );
}
