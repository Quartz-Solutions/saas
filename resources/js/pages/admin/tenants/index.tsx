import { Form, Head, Link, router } from '@inertiajs/react';
import { LogIn, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import {
    DataTable
    
    
    
} from '@/components/data-table/data-table';
import type {DataTableColumn, DataTableFilter, PaginationData} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import {
    AlertDialog,
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
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/utils';
import TenantsAdminController from '@/actions/App/Http/Controllers/Admin/TenantsAdminController';
import { index as adminTenantsIndex, show as adminTenantsShow } from '@/routes/admin/tenants';

type TenantRow = {
    id: number;
    slug: string;
    name: string;
    status: string;
    currency: string;
    created_at: string | null;
    owner: { id: number; name: string; email: string } | null;
    members_count: number;
};

type Props = {
    tenants: {
        data: TenantRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

export default function AdminTenantsIndex({ tenants, tableState }: Props) {
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

        router.get(adminTenantsIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tenants', 'tableState'],
        });
    };

    const [impersonating, setImpersonating] = useState<TenantRow | null>(null);

    const columns: DataTableColumn<TenantRow>[] = [
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <Link
                        href={adminTenantsShow({ tenant: row.id })}
                        className="font-medium hover:underline"
                    >
                        {row.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">{row.slug}</span>
                </div>
            ),
        },
        {
            key: 'owner',
            header: 'Owner',
            render: (row) =>
                row.owner ? (
                    <div className="flex flex-col">
                        <span>{row.owner.name}</span>
                        <span className="text-xs text-muted-foreground">{row.owner.email}</span>
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            render: (row) => <Badge variant="outline">{row.status}</Badge>,
        },
        {
            key: 'currency',
            header: 'Currency',
            render: (row) => (
                <span className="font-mono text-xs">{row.currency}</span>
            ),
        },
        {
            key: 'members_count',
            header: 'Members',
            render: (row) => (
                <span className="tabular-nums">{row.members_count}</span>
            ),
            headerClassName: 'justify-end',
            className: 'text-right tabular-nums',
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
                            <Link href={adminTenantsShow({ tenant: row.id })}>
                                View details
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={row.owner === null}
                            onSelect={() => row.owner && setImpersonating(row)}
                        >
                            Impersonate owner
                        </DropdownMenuItem>
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
            placeholder: 'Any',
            options: [
                { label: 'Active', value: 'active' },
                { label: 'Trialing', value: 'trialing' },
                { label: 'Past due', value: 'past_due' },
                { label: 'Cancelled', value: 'cancelled' },
            ],
        },
        {
            key: 'currency',
            label: 'Currency',
            type: 'text',
            placeholder: 'USD',
        },
        {
            key: 'created_at',
            label: 'Created between',
            type: 'daterange',
            placeholder: 'Any date',
        },
    ];

    return (
        <>
            <Head title="Tenants — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Tenants"
                        description="Every tenant across the system. Includes soft-deleted records on detail pages."
                    />
                </div>

                <DataTable<TenantRow>
                    tableId="admin-tenants-index"
                    data={tenants.data}
                    pagination={tenants.meta}
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

            <ConfirmImpersonateDialog
                tenant={impersonating}
                onClose={() => setImpersonating(null)}
            />
        </>
    );
}

function ConfirmImpersonateDialog({
    tenant,
    onClose,
}: {
    tenant: TenantRow | null;
    onClose: () => void;
}) {
    return (
        <AlertDialog open={tenant !== null} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Impersonate tenant owner?</AlertDialogTitle>
                    <AlertDialogDescription>
                        You will be logged in as{' '}
                        <span className="font-medium">{tenant?.owner?.email}</span>{' '}
                        for tenant <span className="font-medium">{tenant?.name}</span>.
                        Your original session can be restored from the impersonation banner.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {tenant && (
                    <Form
                        {...TenantsAdminController.impersonate.form({ tenant: tenant.id })}
                        options={{ preserveScroll: false }}
                        onSuccess={onClose}
                    >
                        {({ processing }) => (
                            <AlertDialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    <LogIn className="size-4" />
                                    Impersonate
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}
