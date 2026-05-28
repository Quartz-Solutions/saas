import { Form, Head, router } from '@inertiajs/react';
import { Crown, ShieldAlert, ShieldCheck, Trash2, UserMinus } from 'lucide-react';
import { useState } from 'react';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type Tenant = { id: number; slug: string; name: string; owner_id: number };

type Member = {
    membership_id: number;
    user_id: number | null;
    name: string | null;
    email: string | null;
    avatar_path: string | null;
    last_login_at: string | null;
    suspended: boolean;
    is_owner: boolean;
    joined_at: string | null;
    role: string;
};

type Props = {
    tenant: Tenant;
    members: Member[];
    roles: string[];
    isOwner: boolean;
    currentUserId: number | null;
};

const ROLE_COLOR: Record<string, string> = {
    Owner: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    Admin: 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
    Member: 'bg-zinc-100 text-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
};

export default function TenantMembers({
    tenant,
    members,
    roles,
    isOwner,
    currentUserId,
}: Props) {
    const [removing, setRemoving] = useState<Member | null>(null);

    const canEdit = isOwner; // simplification: only owner can edit roles via this UI

    const handleRoleChange = (member: Member, newRole: string) => {
        if (!member.user_id) {
return;
}

        router.patch(
            tenantRoutes.members.role({ tenantSlug: tenant.slug, user: member.user_id }).url,
            { role: newRole },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`${tenant.name} — Members`} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Members"
                    description={`People with access to ${tenant.name}.`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {members.length} member{members.length === 1 ? '' : 's'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Joined</TableHead>
                                    <TableHead>Last login</TableHead>
                                    <TableHead className="w-px text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {members.map((m) => {
                                    const isSelf = m.user_id === currentUserId;

                                    return (
                                        <TableRow
                                            key={m.membership_id}
                                            data-test={`member-row-${m.user_id}`}
                                        >
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Avatar className="size-8">
                                                        {m.avatar_path && (
                                                            <AvatarImage src={m.avatar_path} />
                                                        )}
                                                        <AvatarFallback className="text-[10px]">
                                                            {(m.name ?? '?').slice(0, 2).toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium">
                                                            {m.name ?? '—'}
                                                            {isSelf && (
                                                                <span className="ml-1.5 text-xs text-muted-foreground">
                                                                    (you)
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {m.email}
                                                        </span>
                                                    </div>
                                                    {m.suspended && (
                                                        <Badge
                                                            variant="destructive"
                                                            className="ml-1 text-[10px]"
                                                        >
                                                            Suspended
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {m.is_owner ? (
                                                    <span
                                                        className={
                                                            'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ' +
                                                            ROLE_COLOR.Owner
                                                        }
                                                    >
                                                        <Crown className="size-3" />
                                                        Owner
                                                    </span>
                                                ) : canEdit ? (
                                                    <Select
                                                        value={m.role}
                                                        onValueChange={(v) =>
                                                            handleRoleChange(m, v)
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            className="h-8 w-32"
                                                            data-test={`role-select-${m.user_id}`}
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {roles
                                                                .filter((r) => r !== 'Owner')
                                                                .map((r) => (
                                                                    <SelectItem key={r} value={r}>
                                                                        {r === 'Admin' ? (
                                                                            <span className="inline-flex items-center gap-1.5">
                                                                                <ShieldCheck className="size-3" />
                                                                                {r}
                                                                            </span>
                                                                        ) : (
                                                                            r
                                                                        )}
                                                                    </SelectItem>
                                                                ))}
                                                        </SelectContent>
                                                    </Select>
                                                ) : (
                                                    <Badge
                                                        variant="outline"
                                                        className={ROLE_COLOR[m.role] ?? ''}
                                                    >
                                                        {m.role}
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {m.joined_at
                                                        ? formatDateTime(m.joined_at)
                                                        : '—'}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {m.last_login_at
                                                        ? formatDateTime(m.last_login_at)
                                                        : 'never'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {!m.is_owner && !isSelf && canEdit && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7 text-destructive hover:text-destructive"
                                                        title="Remove from workspace"
                                                        onClick={() => setRemoving(m)}
                                                        data-test={`remove-${m.user_id}`}
                                                    >
                                                        <UserMinus className="size-4" />
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {!canEdit && (
                    <div className="flex items-start gap-2 rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200">
                        <ShieldAlert className="size-4 shrink-0" />
                        <span>
                            Only the workspace owner can change member roles or
                            remove members.
                        </span>
                    </div>
                )}
            </div>

            <AlertDialog
                open={removing !== null}
                onOpenChange={(o) => !o && setRemoving(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Remove {removing?.email} from {tenant.name}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            They will lose access immediately. Their user account
                            is not deleted; you can re-invite them later.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {removing && removing.user_id !== null && (
                        <Form
                            action={tenantRoutes.members.destroy({
                                tenantSlug: tenant.slug,
                                user: removing.user_id,
                            }).url}
                            method="delete"
                            onSuccess={() => setRemoving(null)}
                        >
                            {({ processing }) => (
                                <AlertDialogFooter>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setRemoving(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        <Trash2 className="size-4" />
                                        Remove
                                    </Button>
                                </AlertDialogFooter>
                            )}
                        </Form>
                    )}
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
