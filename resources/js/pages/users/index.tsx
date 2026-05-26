import { Form, Head, router, usePage } from '@inertiajs/react';
import { MoreHorizontal, ShieldCheck, UserPlus } from 'lucide-react';
import { useState } from 'react';
import UsersController from '@/actions/App/Http/Controllers/Users/UsersController';
import {
    DataTable,
    type DataTableColumn,
    type DataTableFilter,
    type PaginationData,
} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime } from '@/lib/utils';
import tenantRoutes from '@/routes/tenants';

type UserRow = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    two_factor_confirmed_at: string | null;
    created_at: string;
};

type Props = {
    users: {
        data: UserRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

export default function UsersIndex({ users, tableState }: Props) {
    const { auth, currentTenant } = usePage<{
        auth: { user: { id: number } };
        currentTenant: { slug: string } | null;
    }>().props;
    const currentUserId = auth.user.id;
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

        router.get(tenantRoutes.users.index({ tenantSlug }).url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['users', 'tableState'],
        });
    };

    const [createOpen, setCreateOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [deletingUser, setDeletingUser] = useState<UserRow | null>(null);

    const columns: DataTableColumn<UserRow>[] = [
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.name}</span>
                    {row.id === currentUserId && (
                        <span className="text-[10px] uppercase tracking-wide text-muted-foreground">
                            You
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'email',
            header: 'Email',
            sortable: true,
            render: (row) => (
                <span className="text-muted-foreground">{row.email}</span>
            ),
        },
        {
            key: 'email_verified_at',
            header: 'Verified',
            sortable: true,
            render: (row) =>
                row.email_verified_at ? (
                    <Badge variant="default" className="font-mono text-[11px]">
                        {formatDateTime(row.email_verified_at)}
                    </Badge>
                ) : (
                    <Badge variant="outline">Unverified</Badge>
                ),
        },
        {
            key: 'two_factor_confirmed_at',
            header: '2FA',
            render: (row) =>
                row.two_factor_confirmed_at ? (
                    <span className="inline-flex items-center gap-1 text-sm">
                        <ShieldCheck className="size-4 text-green-600" />
                        Enabled
                    </span>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
        {
            key: 'created_at',
            header: 'Created',
            sortable: true,
            render: (row) => (
                <span className="font-mono text-xs text-muted-foreground">
                    {formatDateTime(row.created_at)}
                </span>
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
                        <DropdownMenuItem onSelect={() => setEditingUser(row)}>
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            disabled={row.id === currentUserId}
                            onSelect={() =>
                                row.id !== currentUserId &&
                                setDeletingUser(row)
                            }
                        >
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    const filters: DataTableFilter[] = [
        {
            key: 'verified',
            label: 'Email verified',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'Verified', value: 'yes' },
                { label: 'Unverified', value: 'no' },
            ],
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
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Manage user accounts that can sign in to the app."
                    />
                    <Button onClick={() => setCreateOpen(true)}>
                        <UserPlus />
                        New user
                    </Button>
                </div>

                <DataTable<UserRow>
                    tableId="users-index"
                    data={users.data}
                    pagination={users.meta}
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

            <CreateUserDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                tenantSlug={tenantSlug}
            />

            <EditUserDialog
                user={editingUser}
                onClose={() => setEditingUser(null)}
                tenantSlug={tenantSlug}
            />

            <DeleteUserDialog
                user={deletingUser}
                onClose={() => setDeletingUser(null)}
                tenantSlug={tenantSlug}
            />
        </>
    );
}

function CreateUserDialog({
    open,
    onOpenChange,
    tenantSlug,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    tenantSlug: string;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create user</DialogTitle>
                    <DialogDescription>
                        The account will be pre-verified. They can change their
                        password after first sign-in.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...UsersController.store.form({ tenantSlug })}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Name</Label>
                                <Input
                                    id="create-name"
                                    name="name"
                                    required
                                    autoFocus
                                    autoComplete="name"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-email">Email</Label>
                                <Input
                                    id="create-email"
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-password">Password</Label>
                                <PasswordInput
                                    id="create-password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="create-password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create user
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditUserDialog({
    user,
    onClose,
    tenantSlug,
}: {
    user: UserRow | null;
    onClose: () => void;
    tenantSlug: string;
}) {
    return (
        <Dialog open={user !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit user</DialogTitle>
                    <DialogDescription>
                        Leave the password fields blank to keep the current
                        password.
                    </DialogDescription>
                </DialogHeader>
                {user && (
                    <Form
                        {...UsersController.update.form({ tenantSlug, user: user.id })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-name">Name</Label>
                                    <Input
                                        id="edit-name"
                                        name="name"
                                        defaultValue={user.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-email">Email</Label>
                                    <Input
                                        id="edit-email"
                                        name="email"
                                        type="email"
                                        defaultValue={user.email}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <VerifiedRow user={user} />

                                <div className="grid gap-2">
                                    <Label htmlFor="edit-password">
                                        New password
                                    </Label>
                                    <PasswordInput
                                        id="edit-password"
                                        name="password"
                                        autoComplete="new-password"
                                        placeholder="Unchanged"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-password_confirmation">
                                        Confirm new password
                                    </Label>
                                    <PasswordInput
                                        id="edit-password_confirmation"
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                    />
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Save changes
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function VerifiedRow({ user }: { user: UserRow }) {
    return (
        <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2 text-sm">
            <span>Email verified</span>
            {user.email_verified_at ? (
                <Badge>{formatDate(user.email_verified_at)}</Badge>
            ) : (
                <Badge variant="outline">Not verified</Badge>
            )}
        </div>
    );
}

function DeleteUserDialog({
    user,
    onClose,
    tenantSlug,
}: {
    user: UserRow | null;
    onClose: () => void;
    tenantSlug: string;
}) {
    return (
        <AlertDialog
            open={user !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete user?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This permanently removes{' '}
                        <span className="font-medium">{user?.name}</span>. Their
                        account, sessions, and two-factor settings cannot be
                        recovered.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {user && (
                    <Form
                        {...UsersController.destroy.form({ tenantSlug, user: user.id })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                    >
                        {({ processing }) => (
                            <AlertDialogFooter>
                                <AlertDialogCancel type="button">
                                    Cancel
                                </AlertDialogCancel>
                                <AlertDialogAction
                                    type="submit"
                                    disabled={processing}
                                    className="bg-destructive text-white hover:bg-destructive/90"
                                >
                                    {processing && <Spinner />}
                                    Delete user
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        )}
                    </Form>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}

UsersIndex.layout = ({
    currentTenant,
}: {
    currentTenant: { slug: string; name: string } | null;
}) => {
    const slug = currentTenant?.slug ?? '';
    return {
        breadcrumbs: [
            {
                title: currentTenant?.name ?? 'Tenant',
                href: tenantRoutes.dashboard({ tenantSlug: slug }),
            },
            { title: 'Users', href: tenantRoutes.users.index({ tenantSlug: slug }) },
        ],
    };
};
