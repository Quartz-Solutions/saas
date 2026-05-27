import { Head, router, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    DataTable,
} from '@/components/data-table/data-table';
import type {
    DataTableColumn,
    PaginationData,
} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/utils';
import tenantRoutes from '@/routes/tenants';

type Row = {
    public_id: string;
    intent: string;
    status: string;
    gateway: string | null;
    currency: string;
    amount_cents: number;
    plan: { slug: string; name: string } | null;
    created_at: string | null;
    completed_at: string | null;
    canceled_at: string | null;
    expires_at: string | null;
    can_resume: boolean;
};

type Props = {
    sessions: { data: Row[]; meta: PaginationData };
    currentTenant: { slug: string; name: string } | null;
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

export default function BillingCheckoutHistory({ sessions }: Props) {
    const { currentTenant } = usePage<{ currentTenant: { slug: string } | null }>().props;
    const tenantSlug = currentTenant?.slug ?? '';

    const columns: DataTableColumn<Row>[] = [
        {
            key: 'created_at',
            header: 'Started',
            render: (row) =>
                row.created_at ? (
                    <span className="font-mono text-xs">{formatDateTime(row.created_at)}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'plan',
            header: 'Plan',
            render: (row) => <span className="text-sm">{row.plan?.name ?? '—'}</span>,
        },
        {
            key: 'amount_cents',
            header: 'Amount',
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
            key: 'gateway',
            header: 'Gateway',
            render: (row) => <span className="text-xs">{row.gateway ?? '—'}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'outline'}>{row.status}</Badge>,
        },
        {
            key: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            className: 'w-px text-right',
            render: (row) =>
                row.can_resume ? (
                    <Button variant="outline" size="sm" asChild>
                        <a href={`/checkout/${row.public_id}`}>
                            Resume
                            <ArrowRight className="size-4" />
                        </a>
                    </Button>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Checkout history" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <Heading
                    title="Checkout history"
                    description="Every checkout you've started — in-progress, completed, or abandoned."
                />

                <DataTable<Row>
                    tableId="billing-checkout-history"
                    data={sessions.data}
                    pagination={sessions.meta}
                    columns={columns}
                    onPageChange={(page) => {
                        router.get(
                            tenantRoutes.billing.checkoutHistory({ tenantSlug }).url,
                            page > 1 ? { page } : {},
                            { preserveState: true, preserveScroll: true, replace: true, only: ['sessions'] },
                        );
                    }}
                />
            </div>
        </>
    );
}

BillingCheckoutHistory.layout = ({
    currentTenant,
}: {
    currentTenant: { slug: string; name: string } | null;
}) => {
    const slug = currentTenant?.slug ?? '';

    return {
        breadcrumbs: [
            { title: currentTenant?.name ?? 'Tenant', href: tenantRoutes.dashboard({ tenantSlug: slug }) },
            { title: 'Billing', href: tenantRoutes.billing.plans({ tenantSlug: slug }) },
            { title: 'Checkout history', href: tenantRoutes.billing.checkoutHistory({ tenantSlug: slug }) },
        ],
    };
};
