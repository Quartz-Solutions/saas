import { Head, Link, router, usePage } from '@inertiajs/react';
import { Download, ExternalLink } from 'lucide-react';
import {
    DataTable,
    type DataTableColumn,
    type DataTableFilter,
    type PaginationData,
} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/utils';
import tenantRoutes from '@/routes/tenants';

type InvoiceRow = {
    id: number;
    number: string;
    status: string;
    gateway: string;
    currency: string;
    total_cents: number;
    amount_paid_cents: number;
    amount_due_cents: number;
    issued_at: string | null;
    due_at: string | null;
    paid_at: string | null;
    hosted_invoice_url: string | null;
};

type Props = {
    invoices: {
        data: InvoiceRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

function formatMoney(cents: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
}

export default function BillingInvoices({ invoices, tableState }: Props) {
    const { currentTenant } = usePage<{ currentTenant: { slug: string } | null }>().props;
    const tenantSlug = currentTenant?.slug ?? '';

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

        router.get(tenantRoutes.billing.invoices.index({ tenantSlug }).url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['invoices', 'tableState'],
        });
    };

    const columns: DataTableColumn<InvoiceRow>[] = [
        {
            key: 'number',
            header: 'Invoice',
            sortable: true,
            render: (row) => (
                <span className="font-mono text-xs">{row.number}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            render: (row) => (
                <Badge
                    variant={
                        row.status === 'paid'
                            ? 'default'
                            : row.status === 'void' || row.status === 'uncollectible'
                              ? 'outline'
                              : 'secondary'
                    }
                >
                    {row.status}
                </Badge>
            ),
        },
        {
            key: 'gateway',
            header: 'Gateway',
            render: (row) => (
                <span className="text-sm text-muted-foreground">{row.gateway}</span>
            ),
        },
        {
            key: 'total_cents',
            header: 'Total',
            sortable: true,
            render: (row) => (
                <span className="font-medium">{formatMoney(row.total_cents, row.currency)}</span>
            ),
        },
        {
            key: 'issued_at',
            header: 'Issued',
            sortable: true,
            render: (row) => (
                <span className="font-mono text-xs text-muted-foreground">
                    {formatDateTime(row.issued_at)}
                </span>
            ),
        },
        {
            key: 'paid_at',
            header: 'Paid',
            sortable: true,
            render: (row) => (
                <span className="font-mono text-xs text-muted-foreground">
                    {formatDateTime(row.paid_at)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            className: 'w-px text-right',
            render: (row) => (
                <div className="flex items-center justify-end gap-1.5">
                    <Button asChild variant="ghost" size="sm" title="Download PDF">
                        <Link
                            href={tenantRoutes.billing.invoices.pdf({ tenantSlug, invoice: row.id })}
                        >
                            <Download className="size-4" />
                            <span className="sr-only">Download PDF</span>
                        </Link>
                    </Button>
                    {row.hosted_invoice_url && (
                        <Button asChild variant="ghost" size="sm" title="Open at gateway">
                            <a
                                href={row.hosted_invoice_url}
                                target="_blank"
                                rel="noreferrer noopener"
                            >
                                <ExternalLink className="size-4" />
                                <span className="sr-only">Open at gateway</span>
                            </a>
                        </Button>
                    )}
                </div>
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
                { label: 'Draft', value: 'draft' },
                { label: 'Open', value: 'open' },
                { label: 'Paid', value: 'paid' },
                { label: 'Void', value: 'void' },
                { label: 'Uncollectible', value: 'uncollectible' },
            ],
        },
        {
            key: 'gateway',
            label: 'Gateway',
            type: 'select',
            placeholder: 'Any',
            options: [{ label: 'Stripe', value: 'stripe' }],
        },
    ];

    return (
        <>
            <Head title="Invoices" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Invoices"
                        description="Every invoice issued to this tenant. PDF downloads available."
                    />
                    <Button asChild variant="outline">
                        <Link href={tenantRoutes.billing.plans({ tenantSlug })}>
                            Back to plans
                        </Link>
                    </Button>
                </div>

                <DataTable<InvoiceRow>
                    tableId="billing-invoices"
                    data={invoices.data}
                    pagination={invoices.meta}
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

BillingInvoices.layout = {
    breadcrumbs: ({
        currentTenant,
    }: {
        currentTenant: { slug: string; name: string } | null;
    }) => {
        const slug = currentTenant?.slug ?? '';
        return [
            {
                title: currentTenant?.name ?? 'Tenant',
                href: tenantRoutes.dashboard({ tenantSlug: slug }),
            },
            { title: 'Billing', href: tenantRoutes.billing.plans({ tenantSlug: slug }) },
            { title: 'Invoices', href: tenantRoutes.billing.invoices.index({ tenantSlug: slug }) },
        ];
    },
};
