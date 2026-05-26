import { Head, router } from '@inertiajs/react';
import {
    DataTable
    
    
    
} from '@/components/data-table/data-table';
import type {DataTableColumn, DataTableFilter, PaginationData} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/utils';
import { index as auditIndex } from '@/routes/admin/audit';

type AuditRow = {
    id: number;
    action: string;
    auditable_type: string | null;
    auditable_id: number | null;
    ip: string | null;
    created_at: string | null;
    user: { id: number; name: string; email: string } | null;
    tenant: { id: number; slug: string; name: string } | null;
};

type Props = {
    auditLogs: {
        data: AuditRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

export default function AdminAuditIndex({ auditLogs, tableState }: Props) {
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

        router.get(auditIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['auditLogs', 'tableState'],
        });
    };

    const columns: DataTableColumn<AuditRow>[] = [
        {
            key: 'action',
            header: 'Action',
            sortable: true,
            render: (row) => <Badge variant="outline">{row.action}</Badge>,
        },
        {
            key: 'auditable',
            header: 'Subject',
            render: (row) =>
                row.auditable_type ? (
                    <div className="flex flex-col">
                        <span className="font-mono text-xs">
                            {row.auditable_type.split('\\').pop() ?? row.auditable_type}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            #{row.auditable_id}
                        </span>
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'user',
            header: 'Actor',
            render: (row) =>
                row.user ? (
                    <div className="flex flex-col">
                        <span>{row.user.name}</span>
                        <span className="text-xs text-muted-foreground">
                            {row.user.email}
                        </span>
                    </div>
                ) : (
                    <span className="text-muted-foreground">system</span>
                ),
        },
        {
            key: 'tenant',
            header: 'Tenant',
            render: (row) =>
                row.tenant ? (
                    <span className="text-sm">{row.tenant.name}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'ip',
            header: 'IP',
            render: (row) => (
                <span className="font-mono text-xs text-muted-foreground">
                    {row.ip ?? '—'}
                </span>
            ),
        },
        {
            key: 'created_at',
            header: 'When',
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
    ];

    const filters: DataTableFilter[] = [
        {
            key: 'action',
            label: 'Action',
            type: 'text',
            placeholder: 'created',
        },
        {
            key: 'auditable_type',
            label: 'Subject type',
            type: 'text',
            placeholder: 'App\\Models\\Tenant',
        },
        {
            key: 'user_id',
            label: 'User ID',
            type: 'text',
            placeholder: 'numeric',
        },
        {
            key: 'tenant_id',
            label: 'Tenant ID',
            type: 'text',
            placeholder: 'numeric',
        },
        {
            key: 'created_at',
            label: 'When',
            type: 'daterange',
            placeholder: 'Any date',
        },
    ];

    return (
        <>
            <Head title="Audit log — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <Heading
                    title="Audit log"
                    description="System-wide record of every observed model mutation."
                />

                <DataTable<AuditRow>
                    tableId="admin-audit-index"
                    data={auditLogs.data}
                    pagination={auditLogs.meta}
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
