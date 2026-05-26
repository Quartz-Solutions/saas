import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
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
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import FeatureFlagOverridesController from '@/actions/App/Http/Controllers/Admin/FeatureFlagOverridesController';
import { index as featureFlagsIndex } from '@/routes/admin/feature-flags';

type Flag = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    enabled_globally: boolean;
    rules: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
};

type Override = {
    id: number;
    enabled: boolean;
    expires_at: string | null;
    reason: string | null;
    created_at: string | null;
    tenant: { id: number; slug: string; name: string } | null;
    user: { id: number; name: string; email: string } | null;
};

type Props = {
    featureFlag: Flag;
    overrides: Override[];
};

export default function FeatureFlagsShow({ featureFlag, overrides }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Override | null>(null);
    const [deleting, setDeleting] = useState<Override | null>(null);

    return (
        <>
            <Head title={`${featureFlag.key} — Feature flag`} />

            <div className="flex flex-col gap-6">
                <Button asChild variant="ghost" size="sm" className="self-start">
                    <Link href={featureFlagsIndex()}>
                        <ArrowLeft className="size-4" />
                        Back to flags
                    </Link>
                </Button>

                <Heading
                    title={featureFlag.key}
                    description={featureFlag.name}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Settings</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-3 text-sm">
                        <Field label="Enabled globally">
                            {featureFlag.enabled_globally ? (
                                <Badge variant="default">On</Badge>
                            ) : (
                                <Badge variant="outline">Off</Badge>
                            )}
                        </Field>
                        <Field label="Created">
                            {featureFlag.created_at
                                ? formatDateTime(featureFlag.created_at)
                                : '—'}
                        </Field>
                        <Field label="Description">
                            <span className="text-muted-foreground">
                                {featureFlag.description ?? '—'}
                            </span>
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-2">
                        <div>
                            <CardTitle>Overrides ({overrides.length})</CardTitle>
                            <CardDescription>
                                Per-tenant or per-user toggles that win over the
                                global default.
                            </CardDescription>
                        </div>
                        <Button onClick={() => setCreateOpen(true)} size="sm">
                            <Plus className="size-4" />
                            Add override
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {overrides.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No overrides yet.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {overrides.map((o) => (
                                    <li
                                        key={o.id}
                                        className="flex items-center justify-between gap-3 py-3"
                                    >
                                        <div className="flex flex-col gap-1">
                                            <div className="flex items-center gap-2">
                                                {o.enabled ? (
                                                    <Badge variant="default">On</Badge>
                                                ) : (
                                                    <Badge variant="outline">Off</Badge>
                                                )}
                                                {o.tenant && (
                                                    <span>
                                                        Tenant:{' '}
                                                        <span className="font-medium">
                                                            {o.tenant.name}
                                                        </span>
                                                    </span>
                                                )}
                                                {o.user && (
                                                    <span>
                                                        User:{' '}
                                                        <span className="font-medium">
                                                            {o.user.email}
                                                        </span>
                                                    </span>
                                                )}
                                            </div>
                                            {o.reason && (
                                                <span className="text-xs text-muted-foreground">
                                                    {o.reason}
                                                </span>
                                            )}
                                            {o.expires_at && (
                                                <span className="text-xs text-muted-foreground">
                                                    Expires{' '}
                                                    {formatDateTime(o.expires_at)}
                                                </span>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                >
                                                    <MoreHorizontal />
                                                    <span className="sr-only">
                                                        Open actions
                                                    </span>
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    onSelect={() => setEditing(o)}
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onSelect={() => setDeleting(o)}
                                                >
                                                    Remove
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <CreateOverrideDialog
                flag={featureFlag}
                open={createOpen}
                onOpenChange={setCreateOpen}
            />
            <EditOverrideDialog
                flag={featureFlag}
                override={editing}
                onClose={() => setEditing(null)}
            />
            <DeleteOverrideDialog
                flag={featureFlag}
                override={deleting}
                onClose={() => setDeleting(null)}
            />
        </>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-[11px] uppercase tracking-wide text-muted-foreground">
                {label}
            </span>
            <span>{children}</span>
        </div>
    );
}

function CreateOverrideDialog({
    flag,
    open,
    onOpenChange,
}: {
    flag: Flag;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add override</DialogTitle>
                    <DialogDescription>
                        Either Tenant ID or User ID must be set.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...FeatureFlagOverridesController.store.form({ feature_flag: flag.id })}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="ov-tenant">Tenant ID</Label>
                                <Input id="ov-tenant" name="tenant_id" type="number" min={1} />
                                <InputError message={errors.tenant_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ov-user">User ID</Label>
                                <Input id="ov-user" name="user_id" type="number" min={1} />
                                <InputError message={errors.user_id} />
                            </div>
                            <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                <Label htmlFor="ov-enabled" className="text-sm">
                                    Enabled
                                </Label>
                                <div className="flex items-center gap-2">
                                    <input type="hidden" name="enabled" value="0" />
                                    <Switch
                                        id="ov-enabled"
                                        name="enabled"
                                        value="1"
                                        defaultChecked
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ov-expires">Expires at</Label>
                                <Input
                                    id="ov-expires"
                                    name="expires_at"
                                    type="datetime-local"
                                />
                                <InputError message={errors.expires_at} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ov-reason">Reason</Label>
                                <Textarea id="ov-reason" name="reason" rows={2} />
                                <InputError message={errors.reason} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Add
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditOverrideDialog({
    flag,
    override,
    onClose,
}: {
    flag: Flag;
    override: Override | null;
    onClose: () => void;
}) {
    return (
        <Dialog open={override !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit override</DialogTitle>
                </DialogHeader>
                {override && (
                    <Form
                        {...FeatureFlagOverridesController.update.form({
                            feature_flag: flag.id,
                            override: override.id,
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                    <Label htmlFor="ov-edit-enabled" className="text-sm">
                                        Enabled
                                    </Label>
                                    <div className="flex items-center gap-2">
                                        <input type="hidden" name="enabled" value="0" />
                                        <Switch
                                            id="ov-edit-enabled"
                                            name="enabled"
                                            value="1"
                                            defaultChecked={override.enabled}
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ov-edit-expires">Expires at</Label>
                                    <Input
                                        id="ov-edit-expires"
                                        name="expires_at"
                                        type="datetime-local"
                                        defaultValue={
                                            override.expires_at
                                                ? override.expires_at.slice(0, 16)
                                                : ''
                                        }
                                    />
                                    <InputError message={errors.expires_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ov-edit-reason">Reason</Label>
                                    <Textarea
                                        id="ov-edit-reason"
                                        name="reason"
                                        rows={2}
                                        defaultValue={override.reason ?? ''}
                                    />
                                    <InputError message={errors.reason} />
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Save
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

function DeleteOverrideDialog({
    flag,
    override,
    onClose,
}: {
    flag: Flag;
    override: Override | null;
    onClose: () => void;
}) {
    return (
        <AlertDialog
            open={override !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Remove override?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This permanently removes the override.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {override && (
                    <Form
                        {...FeatureFlagOverridesController.destroy.form({
                            feature_flag: flag.id,
                            override: override.id,
                        })}
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
                                    Remove
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}
