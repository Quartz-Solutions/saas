import { Form, Head, usePage } from '@inertiajs/react';
import { Mail, MoreHorizontal, UserPlus } from 'lucide-react';
import { useState } from 'react';
import TenantInvitationsController from '@/actions/App/Http/Controllers/Tenants/TenantInvitationsController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/utils';
import tenantRoutes from '@/routes/tenants';

type Invitation = {
    id: number;
    email: string;
    role: string | null;
    token: string;
    expires_at: string | null;
    accepted_at: string | null;
    revoked_at: string | null;
    created_at: string | null;
};

type Props = {
    invitations: Invitation[];
};

const ROLES = ['Owner', 'Admin', 'Member'] as const;

export default function TenantInvitations({ invitations }: Props) {
    const { currentTenant } = usePage<{
        currentTenant: { slug: string; name: string } | null;
    }>().props;
    const slug = currentTenant?.slug ?? '';

    const [createOpen, setCreateOpen] = useState(false);
    const [deletingInv, setDeletingInv] = useState<Invitation | null>(null);

    return (
        <>
            <Head title="Invitations" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Invitations"
                        description="Invite teammates by email. Invites expire after 7 days."
                    />
                    <Button onClick={() => setCreateOpen(true)} data-test="new-invitation">
                        <UserPlus />
                        Invite member
                    </Button>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Sent</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead className="w-px"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invitations.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        <Mail className="mx-auto mb-2 size-6" />
                                        No invitations yet.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                invitations.map((inv) => (
                                    <TableRow key={inv.id}>
                                        <TableCell className="font-medium">
                                            {inv.email}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {inv.role ?? '—'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge inv={inv} />
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {inv.created_at
                                                ? formatDateTime(inv.created_at)
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {inv.expires_at
                                                ? formatDateTime(inv.expires_at)
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            {inv.accepted_at === null &&
                                                inv.revoked_at === null && (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8"
                                                            >
                                                                <MoreHorizontal />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                onSelect={() =>
                                                                    setDeletingInv(
                                                                        inv,
                                                                    )
                                                                }
                                                            >
                                                                Revoke
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <CreateInvitationDialog
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                slug={slug}
            />

            <RevokeInvitationDialog
                invitation={deletingInv}
                onClose={() => setDeletingInv(null)}
                slug={slug}
            />
        </>
    );
}

function StatusBadge({ inv }: { inv: Invitation }) {
    if (inv.accepted_at) {
        return <Badge>Accepted</Badge>;
    }

    if (inv.revoked_at) {
        return <Badge variant="outline">Revoked</Badge>;
    }

    if (inv.expires_at && new Date(inv.expires_at) < new Date()) {
        return <Badge variant="outline">Expired</Badge>;
    }

    return <Badge variant="secondary">Pending</Badge>;
}

function CreateInvitationDialog({
    open,
    onClose,
    slug,
}: {
    open: boolean;
    onClose: () => void;
    slug: string;
}) {
    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite member</DialogTitle>
                    <DialogDescription>
                        Existing users are added immediately; new emails get a
                        signed-token link.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...TenantInvitationsController.store.form({
                        tenantSlug: slug,
                    })}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="inv-email">Email</Label>
                                <Input
                                    id="inv-email"
                                    name="email"
                                    type="email"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="inv-role">Role</Label>
                                <Select name="role" defaultValue="Member">
                                    <SelectTrigger
                                        id="inv-role"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ROLES.map((r) => (
                                            <SelectItem key={r} value={r}>
                                                {r}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Send invite
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RevokeInvitationDialog({
    invitation,
    onClose,
    slug,
}: {
    invitation: Invitation | null;
    onClose: () => void;
    slug: string;
}) {
    return (
        <AlertDialog open={invitation !== null} onOpenChange={(v) => !v && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Revoke invitation?</AlertDialogTitle>
                    <AlertDialogDescription>
                        The invite link for{' '}
                        <span className="font-medium">{invitation?.email}</span>{' '}
                        will stop working immediately.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {invitation && (
                    <Form
                        {...TenantInvitationsController.destroy.form({
                            tenantSlug: slug,
                            invitation: invitation.id,
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
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Revoke
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}

TenantInvitations.layout = {
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
            {
                title: 'Invitations',
                href: tenantRoutes.invitations.index({ tenantSlug: slug }),
            },
        ];
    },
};
