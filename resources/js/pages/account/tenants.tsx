import { Form, Head, Link } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import { useState } from 'react';
import TenantsController from '@/actions/App/Http/Controllers/Tenants/TenantsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index as tenantsIndex } from '@/routes/account/tenants';
import tenantRoutes from '@/routes/tenants';

type TenantRow = {
    id: number;
    slug: string;
    name: string;
    role: 'Owner' | 'Member';
    status: string;
    memberships_count: number;
    created_at: string | null;
};

type Props = {
    tenants: TenantRow[];
};

export default function AccountTenants({ tenants }: Props) {
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <>
            <Head title="My tenants" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="My tenants"
                        description="Tenants you own or have been invited to."
                    />
                    <Button onClick={() => setCreateOpen(true)} data-test="new-tenant">
                        <Plus />
                        New tenant
                    </Button>
                </div>

                {tenants.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Building2 className="size-10 text-muted-foreground" />
                            <p className="text-muted-foreground">
                                You don&apos;t have any tenants yet.
                            </p>
                            <Button onClick={() => setCreateOpen(true)}>
                                Create your first tenant
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {tenants.map((t) => (
                            <Card key={t.id} data-test={`tenant-card-${t.slug}`}>
                                <CardHeader>
                                    <div className="flex items-center justify-between gap-2">
                                        <CardTitle className="truncate">
                                            {t.name}
                                        </CardTitle>
                                        <Badge
                                            variant={
                                                t.role === 'Owner'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {t.role}
                                        </Badge>
                                    </div>
                                    <CardDescription>/t/{t.slug}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-1 text-sm text-muted-foreground">
                                    <div>
                                        {t.memberships_count} member
                                        {t.memberships_count === 1 ? '' : 's'}
                                    </div>
                                    <div className="capitalize">{t.status}</div>
                                </CardContent>
                                <CardFooter className="gap-2">
                                    <Button asChild className="w-full" size="sm">
                                        <Link
                                            href={tenantRoutes.dashboard({
                                                tenantSlug: t.slug,
                                            })}
                                        >
                                            Open
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <CreateTenantDialog open={createOpen} onOpenChange={setCreateOpen} />
        </>
    );
}

function CreateTenantDialog({
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
                    <DialogTitle>Create tenant</DialogTitle>
                    <DialogDescription>
                        You become the Owner of this tenant. You can rename or
                        delete it later from its settings page.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...TenantsController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="tenant-name">Name</Label>
                                <Input
                                    id="tenant-name"
                                    name="name"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tenant-slug">
                                    Slug{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="tenant-slug"
                                    name="slug"
                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                    placeholder="my-team"
                                />
                                <InputError message={errors.slug} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create tenant
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

AccountTenants.layout = {
    breadcrumbs: [{ title: 'My tenants', href: tenantsIndex() }],
};
