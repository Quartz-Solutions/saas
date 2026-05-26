import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, LogIn } from 'lucide-react';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/utils';
import TenantsAdminController from '@/actions/App/Http/Controllers/Admin/TenantsAdminController';
import { index as adminTenantsIndex } from '@/routes/admin/tenants';

type Tenant = {
    id: number;
    slug: string;
    name: string;
    status: string;
    currency: string;
    timezone: string;
    locale: string;
    logo_path: string | null;
    settings: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    owner: { id: number; name: string; email: string } | null;
    members: Array<{
        id: number | null;
        name: string | null;
        email: string | null;
        joined_at: string | null;
    }>;
};

export default function AdminTenantsShow({ tenant }: { tenant: Tenant }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <>
            <Head title={`${tenant.name} — Tenant`} />

            <div className="flex flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={adminTenantsIndex()}>
                                <ArrowLeft className="size-4" />
                                Back to tenants
                            </Link>
                        </Button>
                    </div>
                    <Button
                        onClick={() => setConfirmOpen(true)}
                        disabled={tenant.owner === null}
                    >
                        <LogIn className="size-4" />
                        Impersonate owner
                    </Button>
                </div>

                <Heading
                    title={tenant.name}
                    description={`/t/${tenant.slug}`}
                />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                            <CardDescription>Read-only.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-3 text-sm">
                            <Field label="Status">
                                <Badge variant="outline">{tenant.status}</Badge>
                            </Field>
                            <Field label="Currency">{tenant.currency}</Field>
                            <Field label="Timezone">{tenant.timezone}</Field>
                            <Field label="Locale">{tenant.locale}</Field>
                            <Field label="Created">
                                {tenant.created_at ? formatDateTime(tenant.created_at) : '—'}
                            </Field>
                            <Field label="Updated">
                                {tenant.updated_at ? formatDateTime(tenant.updated_at) : '—'}
                            </Field>
                            {tenant.deleted_at && (
                                <Field label="Deleted">
                                    <span className="text-destructive">
                                        {formatDateTime(tenant.deleted_at)}
                                    </span>
                                </Field>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Owner</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {tenant.owner ? (
                                <div className="flex flex-col gap-1">
                                    <span className="font-medium">{tenant.owner.name}</span>
                                    <span className="text-muted-foreground">
                                        {tenant.owner.email}
                                    </span>
                                </div>
                            ) : (
                                <span className="text-muted-foreground">No owner.</span>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Members ({tenant.members.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tenant.members.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No members yet.
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {tenant.members.map((m, idx) => (
                                    <li
                                        key={m.id ?? `member-${idx}`}
                                        className="flex items-center justify-between py-2"
                                    >
                                        <div className="flex flex-col">
                                            <span>{m.name ?? '—'}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {m.email ?? ''}
                                            </span>
                                        </div>
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {m.joined_at ? formatDateTime(m.joined_at) : '—'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Impersonate tenant owner?</AlertDialogTitle>
                        <AlertDialogDescription>
                            You will be logged in as{' '}
                            <span className="font-medium">{tenant.owner?.email}</span>.
                            Your original session can be restored from the impersonation
                            banner.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <Form
                        {...TenantsAdminController.impersonate.form({ tenant: tenant.id })}
                        onSuccess={() => setConfirmOpen(false)}
                    >
                        {({ processing }) => (
                            <AlertDialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setConfirmOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Impersonate
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                </AlertDialogContent>
            </AlertDialog>
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
