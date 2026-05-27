import { Form, Head, Link, router } from '@inertiajs/react';
import { LogIn, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import TenantsAdminController from '@/actions/App/Http/Controllers/Admin/TenantsAdminController';
import { SavedViews  } from '@/components/admin/entity-detail/saved-views';
import type {SavedView} from '@/components/admin/entity-detail/saved-views';
import { DataTable } from '@/components/data-table/data-table';
import type {
    DataTableColumn,
    DataTableFilter,
    PaginationData,
} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { index as adminTenantsIndex, show as adminTenantsShow } from '@/routes/admin/tenants';

type TenantRow = {
    id: number;
    slug: string;
    name: string;
    logo_path: string | null;
    status: string;
    currency: string;
    created_at: string | null;
    deleted_at: string | null;
    owner: { id: number; name: string; email: string } | null;
    members_count: number;
    plan: { slug: string; name: string } | null;
    mrr_cents: number | null;
    subscription_status: string | null;
};

type Props = {
    tenants: { data: TenantRow[]; meta: PaginationData };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
        view: string;
    };
    viewCounts: Record<string, number>;
};

function formatMoney(cents: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: (currency || 'USD').toUpperCase(),
            maximumFractionDigits: 0,
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(0)} ${currency}`;
    }
}

function statusBadge(status: string, deletedAt: string | null) {
    if (deletedAt) {
return { v: 'outline' as const, label: 'Archived' };
}

    switch (status) {
        case 'active':
            return { v: 'default' as const, label: 'Active' };
        case 'suspended':
            return { v: 'destructive' as const, label: 'Suspended' };
        case 'pending_deletion':
            return { v: 'secondary' as const, label: 'Pending deletion' };
        default:
            return { v: 'outline' as const, label: status };
    }
}

export default function AdminTenantsIndex({ tenants, tableState, viewCounts }: Props) {
    const reload = (params: {
        search?: string;
        filters?: Record<string, string>;
        sort?: { column: string; direction: 'asc' | 'desc' };
        page?: number;
        view?: string | null;
    }) => {
        const data: Record<string, unknown> = {};

        if (params.search) {
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

        if (params.view) {
data.view = params.view;
}

        router.get(adminTenantsIndex().url, data as Record<string, string | number | Record<string, string>>, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['tenants', 'tableState', 'viewCounts'],
        });
    };

    const onView = (v: string | null) =>
        reload({ view: v ?? undefined, search: tableState.search });

    const [impersonating, setImpersonating] = useState<TenantRow | null>(null);

    const savedViews: SavedView[] = [
        { value: 'active', label: 'Active', count: viewCounts.active },
        { value: 'trialing', label: 'Trialing' },
        { value: 'past_due', label: 'Past due', count: viewCounts.past_due },
        { value: 'suspended', label: 'Suspended', count: viewCounts.suspended },
        { value: 'archived', label: 'Archived', count: viewCounts.archived },
    ];

    const columns: DataTableColumn<TenantRow>[] = [
        {
            key: 'name',
            header: 'Tenant',
            sortable: true,
            render: (row) => {
                const badge = statusBadge(row.status, row.deleted_at);

                return (
                    <div className="flex items-center gap-2.5">
                        <Avatar className="size-8 rounded-md">
                            {row.logo_path && <AvatarImage src={row.logo_path} />}
                            <AvatarFallback className="rounded-md text-[10px]">
                                {row.name.slice(0, 2).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex flex-col">
                            <Link
                                href={adminTenantsShow({ tenant: row.id })}
                                className="font-medium hover:underline"
                                data-test={`tenant-link-${row.id}`}
                            >
                                {row.name}
                            </Link>
                            <span className="text-xs text-muted-foreground">
                                /t/{row.slug}
                            </span>
                        </div>
                        <Badge variant={badge.v} className="ml-1 text-[10px]">
                            {badge.label}
                        </Badge>
                    </div>
                );
            },
        },
        {
            key: 'owner',
            header: 'Owner',
            render: (row) =>
                row.owner ? (
                    <div className="flex flex-col">
                        <span className="text-sm">{row.owner.name}</span>
                        <span className="text-xs text-muted-foreground">{row.owner.email}</span>
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
        {
            key: 'plan',
            header: 'Plan',
            render: (row) => (
                <div className="flex flex-col">
                    <span className="text-sm">{row.plan?.name ?? '—'}</span>
                    {row.subscription_status && (
                        <Badge variant="outline" className="w-fit text-[10px]">
                            {row.subscription_status}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'mrr',
            header: 'MRR',
            render: (row) =>
                row.mrr_cents !== null && row.mrr_cents > 0 ? (
                    <span className="font-mono text-sm tabular-nums">
                        {formatMoney(row.mrr_cents, row.currency)}
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
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
                    '—'
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
                            disabled={row.owner === null || row.deleted_at !== null}
                            onSelect={(e) => {
                                e.preventDefault();

                                if (row.owner) {
setImpersonating(row);
}
                            }}
                        >
                            <LogIn className="size-4" />
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
                { label: 'Suspended', value: 'suspended' },
                { label: 'Pending deletion', value: 'pending_deletion' },
            ],
        },
        { key: 'currency', label: 'Currency', type: 'text', placeholder: 'USD' },
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
                        description="Every tenant across the system. Use saved views to filter quickly."
                    />
                </div>

                <SavedViews
                    views={savedViews}
                    value={tableState.view || null}
                    onChange={onView}
                    allCount={viewCounts.all}
                />

                <DataTable<TenantRow>
                    tableId="admin-tenants-index"
                    data={tenants.data}
                    pagination={tenants.meta}
                    columns={columns}
                    filters={filters}
                    initialSearch={tableState.search}
                    initialFilters={tableState.filters}
                    initialSort={tableState.sort}
                    onSearch={(s) => reload({ search: s, view: tableState.view })}
                    onFilter={(f) => reload({ filters: f, search: tableState.search, view: tableState.view })}
                    onClearAll={() => reload({})}
                    onSort={(column, direction) =>
                        reload({
                            search: tableState.search,
                            filters: tableState.filters,
                            sort: { column, direction },
                            view: tableState.view,
                        })
                    }
                    onPageChange={(page) =>
                        reload({
                            search: tableState.search,
                            filters: tableState.filters,
                            sort: tableState.sort,
                            page,
                            view: tableState.view,
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
        <AlertDialog open={tenant !== null} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Impersonate tenant owner?</AlertDialogTitle>
                    <AlertDialogDescription>
                        You will be logged in as{' '}
                        <span className="font-medium">{tenant?.owner?.email}</span>{' '}
                        for <span className="font-medium">{tenant?.name}</span>. Restore via
                        the impersonation banner.
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
                                <Button type="button" variant="secondary" onClick={onClose}>
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
