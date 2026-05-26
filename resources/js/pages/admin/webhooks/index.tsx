import { Head, Link, router } from '@inertiajs/react';
import { Eye, MoreHorizontal } from 'lucide-react';
import {
    DataTable
    
    
    
} from '@/components/data-table/data-table';
import type {DataTableColumn, DataTableFilter, PaginationData} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/utils';
import { index as webhooksIndex, show as webhookShow } from '@/routes/admin/webhooks';

type WebhookRow = {
    id: number;
    gateway: string;
    gateway_event_id: string;
    event_type: string;
    status: string;
    processing_attempts: number;
    received_at: string | null;
    processed_at: string | null;
    tenant_id: number | null;
};

type Props = {
    webhookEvents: {
        data: WebhookRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

const STATUS_VARIANTS: Record<string, 'default' | 'outline' | 'destructive' | 'secondary'> = {
    received: 'outline',
    processing: 'secondary',
    processed: 'default',
    failed: 'destructive',
    ignored: 'outline',
};

export default function AdminWebhooksIndex({ webhookEvents, tableState }: Props) {
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

        router.get(webhooksIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['webhookEvents', 'tableState'],
        });
    };

    const columns: DataTableColumn<WebhookRow>[] = [
        {
            key: 'gateway',
            header: 'Gateway',
            sortable: true,
            render: (row) => (
                <span className="font-mono text-xs uppercase">{row.gateway}</span>
            ),
        },
        {
            key: 'event_type',
            header: 'Event',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.event_type}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.gateway_event_id}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            render: (row) => (
                <Badge variant={STATUS_VARIANTS[row.status] ?? 'outline'}>
                    {row.status}
                </Badge>
            ),
        },
        {
            key: 'processing_attempts',
            header: 'Attempts',
            render: (row) => (
                <span className="tabular-nums">{row.processing_attempts}</span>
            ),
            headerClassName: 'justify-end',
            className: 'text-right tabular-nums',
        },
        {
            key: 'received_at',
            header: 'Received',
            sortable: true,
            render: (row) =>
                row.received_at ? (
                    <span className="font-mono text-xs text-muted-foreground">
                        {formatDateTime(row.received_at)}
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
                            <Link href={webhookShow({ webhookEvent: row.id })}>
                                <Eye className="size-4" />
                                Inspect
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const filters: DataTableFilter[] = [
        {
            key: 'gateway',
            label: 'Gateway',
            type: 'text',
            placeholder: 'stripe',
        },
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Received', value: 'received' },
                { label: 'Processing', value: 'processing' },
                { label: 'Processed', value: 'processed' },
                { label: 'Failed', value: 'failed' },
                { label: 'Ignored', value: 'ignored' },
            ],
        },
        {
            key: 'received_at',
            label: 'Received between',
            type: 'daterange',
            placeholder: 'Any date',
        },
    ];

    return (
        <>
            <Head title="Webhook events — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <Heading
                    title="Webhook events"
                    description="Every raw payload received from a payment gateway, retained for audit + replay."
                />

                <DataTable<WebhookRow>
                    tableId="admin-webhooks-index"
                    data={webhookEvents.data}
                    pagination={webhookEvents.meta}
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
