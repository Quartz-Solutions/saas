import { Form, Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    KeyRound,
    LogIn,
    MoreHorizontal,
    Pause,
    Play,
    ShieldCheck,
    UserCheck,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import UsersAdminController from '@/actions/App/Http/Controllers/Admin/UsersAdminController';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/utils';
import { index as adminUsersIndex, show as adminUsersShow } from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    avatar_path: string | null;
    verified: boolean;
    two_factor: boolean;
    suspended: boolean;
    roles: string[];
    is_super_admin: boolean;
    tenants_count: number;
    last_login_at: string | null;
    created_at: string | null;
};

type Props = {
    users: { data: UserRow[]; meta: PaginationData };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
        view: string;
    };
    viewCounts: Record<string, number>;
};

export default function AdminUsersIndex({ users, tableState, viewCounts }: Props) {
    const [activeAction, setActiveAction] = useState<
        | { kind: 'suspend' | 'restore' | 'impersonate'; user: UserRow }
        | null
    >(null);

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

        router.get(adminUsersIndex().url, data as Record<string, string | number | Record<string, string>>, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'tableState', 'viewCounts'],
        });
    };

    const onView = (v: string | null) =>
        reload({ view: v ?? undefined, search: tableState.search });

    const savedViews: SavedView[] = [
        { value: 'super_admins', label: 'Super Admins', count: viewCounts.super_admins },
        { value: 'unverified', label: 'Unverified', count: viewCounts.unverified },
        { value: 'suspended', label: 'Suspended', count: viewCounts.suspended },
        { value: 'recently_created', label: 'Recently created', count: viewCounts.recently_created },
    ];

    const columns: DataTableColumn<UserRow>[] = [
        {
            key: 'user',
            header: 'User',
            sortable: true,
            render: (row) => (
                <div className="flex items-center gap-2">
                    <Avatar className="size-8">
                        {row.avatar_path && <AvatarImage src={row.avatar_path} />}
                        <AvatarFallback className="text-[10px]">
                            {row.name.slice(0, 2).toUpperCase()}
                        </AvatarFallback>
                    </Avatar>
                    <div className="flex flex-col">
                        <Link
                            href={adminUsersShow({ user: row.id })}
                            className="font-medium hover:underline"
                            data-test={`user-link-${row.id}`}
                        >
                            {row.name}
                        </Link>
                        <span className="text-xs text-muted-foreground">{row.email}</span>
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => (
                <div className="flex flex-wrap items-center gap-1">
                    {row.verified ? (
                        <Badge variant="outline" className="gap-1 text-[10px]">
                            <BadgeCheck className="size-3" /> Verified
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="gap-1 border-amber-500/40 text-amber-700 text-[10px]">
                            Unverified
                        </Badge>
                    )}
                    {row.two_factor && (
                        <Badge variant="outline" className="gap-1 text-[10px]">
                            <KeyRound className="size-3" /> 2FA
                        </Badge>
                    )}
                    {row.suspended && (
                        <Badge variant="destructive" className="text-[10px]">
                            Suspended
                        </Badge>
                    )}
                    {row.is_super_admin && (
                        <Badge variant="default" className="gap-1 text-[10px]">
                            <ShieldCheck className="size-3" /> Super
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'tenants_count',
            header: 'Tenants',
            render: (row) => <span className="tabular-nums">{row.tenants_count}</span>,
        },
        {
            key: 'last_login_at',
            header: 'Last login',
            sortable: true,
            render: (row) =>
                row.last_login_at ? (
                    <span className="font-mono text-xs text-muted-foreground">
                        {formatDateTime(row.last_login_at)}
                    </span>
                ) : (
                    <span className="text-muted-foreground">never</span>
                ),
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
                            <Link href={adminUsersShow({ user: row.id })}>View details</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={(e) => {
                                e.preventDefault();
                                setActiveAction({ kind: 'impersonate', user: row });
                            }}
                        >
                            <LogIn className="size-4" />
                            Impersonate
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        {row.suspended ? (
                            <DropdownMenuItem
                                onSelect={(e) => {
                                    e.preventDefault();
                                    setActiveAction({ kind: 'restore', user: row });
                                }}
                            >
                                <Play className="size-4" />
                                Restore
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                onSelect={(e) => {
                                    e.preventDefault();
                                    setActiveAction({ kind: 'suspend', user: row });
                                }}
                            >
                                <Pause className="size-4" />
                                Suspend
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const filters: DataTableFilter[] = [
        {
            key: 'verified',
            label: 'Verified',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Yes', value: '1' },
                { label: 'No', value: '0' },
            ],
        },
        {
            key: 'two_factor',
            label: '2FA enabled',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Yes', value: '1' },
                { label: 'No', value: '0' },
            ],
        },
        {
            key: 'suspended',
            label: 'Suspended',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Yes', value: '1' },
                { label: 'No', value: '0' },
            ],
        },
        {
            key: 'role',
            label: 'Role',
            type: 'text',
            placeholder: 'Super Admin',
        },
    ];

    return (
        <>
            <Head title="Users — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Every user across the system. Use saved views to filter quickly."
                    />
                </div>

                <SavedViews
                    views={savedViews}
                    value={tableState.view || null}
                    onChange={onView}
                    allCount={viewCounts.all}
                />

                <DataTable<UserRow>
                    tableId="admin-users-index"
                    data={users.data}
                    pagination={users.meta}
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

            {activeAction?.kind === 'suspend' && (
                <SuspendDialog
                    user={activeAction.user}
                    onClose={() => setActiveAction(null)}
                />
            )}
            {activeAction?.kind === 'restore' && (
                <RestoreDialog
                    user={activeAction.user}
                    onClose={() => setActiveAction(null)}
                />
            )}
            {activeAction?.kind === 'impersonate' && (
                <ImpersonateDialog
                    user={activeAction.user}
                    onClose={() => setActiveAction(null)}
                />
            )}
        </>
    );
}

function SuspendDialog({ user, onClose }: { user: UserRow; onClose: () => void }) {
    return (
        <AlertDialog open onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Suspend {user.email}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        The user will be signed out of all sessions and prevented from logging
                        back in until restored.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...UsersAdminController.suspend.form({ user: user.id })}
                    onSuccess={onClose}
                >
                    {({ processing }) => (
                        <>
                            <Textarea
                                name="reason"
                                rows={3}
                                placeholder="Reason (recorded in audit log)"
                            />
                            <AlertDialogFooter>
                                <Button type="button" variant="secondary" onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button type="submit" variant="destructive" disabled={processing}>
                                    {processing && <Spinner />}
                                    <UserX className="size-4" />
                                    Suspend
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function RestoreDialog({ user, onClose }: { user: UserRow; onClose: () => void }) {
    return (
        <AlertDialog open onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Restore {user.email}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Lifts the suspension. The user can sign in again immediately.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...UsersAdminController.restore.form({ user: user.id })}
                    onSuccess={onClose}
                >
                    {({ processing }) => (
                        <AlertDialogFooter>
                            <Button type="button" variant="secondary" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                <UserCheck className="size-4" />
                                Restore
                            </Button>
                        </AlertDialogFooter>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function ImpersonateDialog({ user, onClose }: { user: UserRow; onClose: () => void }) {
    return (
        <AlertDialog open onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Impersonate {user.email}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        You will be logged in as this user. Return via the impersonation banner.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...UsersAdminController.impersonate.form({ user: user.id })}
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
            </AlertDialogContent>
        </AlertDialog>
    );
}
