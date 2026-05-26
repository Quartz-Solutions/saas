import { Form, Head, Link, router } from '@inertiajs/react';
import { Flag, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import {
    DataTable
    
    
    
} from '@/components/data-table/data-table';
import type {DataTableColumn, DataTableFilter, PaginationData} from '@/components/data-table/data-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/utils';
import FeatureFlagsController from '@/actions/App/Http/Controllers/Admin/FeatureFlagsController';
import { index as featureFlagsIndex, show as featureFlagShow } from '@/routes/admin/feature-flags';

type FlagRow = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    enabled_globally: boolean;
    rules: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
};

type Props = {
    featureFlags: {
        data: FlagRow[];
        meta: PaginationData;
    };
    tableState: {
        search: string;
        filters: Record<string, string>;
        sort: { column: string; direction: 'asc' | 'desc' };
    };
};

export default function FeatureFlagsIndex({ featureFlags, tableState }: Props) {
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

        router.get(featureFlagsIndex().url, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['featureFlags', 'tableState'],
        });
    };

    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<FlagRow | null>(null);
    const [deleting, setDeleting] = useState<FlagRow | null>(null);

    const columns: DataTableColumn<FlagRow>[] = [
        {
            key: 'key',
            header: 'Key',
            sortable: true,
            render: (row) => (
                <div className="flex flex-col">
                    <Link
                        href={featureFlagShow({ feature_flag: row.id })}
                        className="font-mono text-sm hover:underline"
                    >
                        {row.key}
                    </Link>
                    <span className="text-xs text-muted-foreground">{row.name}</span>
                </div>
            ),
        },
        {
            key: 'enabled_globally',
            header: 'Enabled',
            sortable: true,
            render: (row) =>
                row.enabled_globally ? (
                    <Badge variant="default">On</Badge>
                ) : (
                    <Badge variant="outline">Off</Badge>
                ),
        },
        {
            key: 'description',
            header: 'Description',
            render: (row) => (
                <span className="text-sm text-muted-foreground">
                    {row.description ?? '—'}
                </span>
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
                            <Link href={featureFlagShow({ feature_flag: row.id })}>
                                Open detail
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={() => setEditing(row)}>
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setDeleting(row)}
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
            key: 'enabled_globally',
            label: 'Enabled globally',
            type: 'select',
            placeholder: 'Any',
            options: [
                { label: 'On', value: 'yes' },
                { label: 'Off', value: 'no' },
            ],
        },
    ];

    return (
        <>
            <Head title="Feature flags — Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Feature flags"
                        description="Global toggles + per-tenant overrides."
                    />
                    <Button onClick={() => setCreateOpen(true)}>
                        <Flag className="size-4" />
                        New flag
                    </Button>
                </div>

                <DataTable<FlagRow>
                    tableId="admin-feature-flags-index"
                    data={featureFlags.data}
                    pagination={featureFlags.meta}
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

            <CreateFlagDialog open={createOpen} onOpenChange={setCreateOpen} />
            <EditFlagDialog flag={editing} onClose={() => setEditing(null)} />
            <DeleteFlagDialog flag={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}

function CreateFlagDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create feature flag</DialogTitle>
                    <DialogDescription>
                        Keys must be lowercase with dashes, dots or underscores.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...FeatureFlagsController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-key">Key</Label>
                                <Input
                                    id="create-key"
                                    name="key"
                                    required
                                    autoFocus
                                    placeholder="billing.dunning"
                                />
                                <InputError message={errors.key} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-name">Name</Label>
                                <Input id="create-name" name="name" required />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-description">Description</Label>
                                <Textarea id="create-description" name="description" rows={3} />
                                <InputError message={errors.description} />
                            </div>
                            <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                <Label htmlFor="create-enabled" className="text-sm">
                                    Enabled globally
                                </Label>
                                <div className="flex items-center gap-2">
                                    <input type="hidden" name="enabled_globally" value="0" />
                                    <Switch id="create-enabled" name="enabled_globally" value="1" />
                                </div>
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create flag
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditFlagDialog({
    flag,
    onClose,
}: {
    flag: FlagRow | null;
    onClose: () => void;
}) {
    return (
        <Dialog open={flag !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit feature flag</DialogTitle>
                </DialogHeader>
                {flag && (
                    <Form
                        {...FeatureFlagsController.update.form({ feature_flag: flag.id })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-key">Key</Label>
                                    <Input
                                        id="edit-key"
                                        name="key"
                                        defaultValue={flag.key}
                                        required
                                    />
                                    <InputError message={errors.key} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-name">Name</Label>
                                    <Input
                                        id="edit-name"
                                        name="name"
                                        defaultValue={flag.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-description">Description</Label>
                                    <Textarea
                                        id="edit-description"
                                        name="description"
                                        defaultValue={flag.description ?? ''}
                                        rows={3}
                                    />
                                    <InputError message={errors.description} />
                                </div>
                                <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                    <Label htmlFor="edit-enabled" className="text-sm">
                                        Enabled globally
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="hidden"
                                            name="enabled_globally"
                                            value="0"
                                        />
                                        <Switch
                                            id="edit-enabled"
                                            name="enabled_globally"
                                            value="1"
                                            defaultChecked={flag.enabled_globally}
                                        />
                                    </div>
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
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

function DeleteFlagDialog({
    flag,
    onClose,
}: {
    flag: FlagRow | null;
    onClose: () => void;
}) {
    return (
        <AlertDialog open={flag !== null} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete feature flag?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This permanently removes{' '}
                        <span className="font-mono">{flag?.key}</span> and all of
                        its overrides.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {flag && (
                    <Form
                        {...FeatureFlagsController.destroy.form({ feature_flag: flag.id })}
                        options={{ preserveScroll: true }}
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
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-destructive text-white hover:bg-destructive/90"
                                >
                                    {processing && <Spinner />}
                                    Delete
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}
