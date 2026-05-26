import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import TenantsController from '@/actions/App/Http/Controllers/Tenants/TenantsController';
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
import tenantRoutes from '@/routes/tenants';

type Currency = {
    code: string;
    name: string;
    symbol: string;
};

type Tenant = {
    id: number;
    slug: string;
    name: string;
    logo_path: string | null;
    logo_url: string | null;
    timezone: string;
    currency: string;
    locale: string;
    status: string;
    is_owner: boolean;
};

type Invitation = {
    id: number;
    email: string;
    role: string | null;
    expires_at: string | null;
    created_at: string | null;
};

type Props = {
    tenant: Tenant;
    invitations: Invitation[];
    currencies: Currency[];
};

export default function TenantSettings({ tenant, currencies }: Props) {
    const { currentTenant } = usePage<{
        currentTenant: { slug: string } | null;
    }>().props;
    const slug = currentTenant?.slug ?? tenant.slug;

    const [deleteOpen, setDeleteOpen] = useState(false);

    return (
        <>
            <Head title={`${tenant.name} – settings`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <Heading
                    title="Tenant settings"
                    description={`Manage settings for ${tenant.name}.`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>General</CardTitle>
                        <CardDescription>
                            Name, slug, locale, timezone, and currency.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...TenantsController.update.form({
                                tenantSlug: slug,
                            })}
                            options={{ preserveScroll: true }}
                            encType="multipart/form-data"
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="t-name">Name</Label>
                                        <Input
                                            id="t-name"
                                            name="name"
                                            defaultValue={tenant.name}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="t-slug">Slug</Label>
                                        <Input
                                            id="t-slug"
                                            name="slug"
                                            defaultValue={tenant.slug}
                                            pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                            required
                                        />
                                        <InputError message={errors.slug} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="t-locale">Locale</Label>
                                        <Input
                                            id="t-locale"
                                            name="locale"
                                            defaultValue={tenant.locale}
                                            required
                                        />
                                        <InputError message={errors.locale} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="t-timezone">
                                            Timezone
                                        </Label>
                                        <Input
                                            id="t-timezone"
                                            name="timezone"
                                            defaultValue={tenant.timezone}
                                            required
                                        />
                                        <InputError message={errors.timezone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="t-currency">
                                            Currency
                                        </Label>
                                        <Select
                                            name="currency"
                                            defaultValue={tenant.currency}
                                        >
                                            <SelectTrigger
                                                id="t-currency"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {currencies.map((c) => (
                                                    <SelectItem
                                                        key={c.code}
                                                        value={c.code}
                                                    >
                                                        {c.code} — {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.currency} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="t-logo">
                                            Logo{' '}
                                            <span className="text-muted-foreground">
                                                (PNG/JPG, max 2 MB)
                                            </span>
                                        </Label>
                                        {tenant.logo_url && (
                                            <img
                                                src={tenant.logo_url}
                                                alt=""
                                                className="h-12 w-auto rounded border"
                                            />
                                        )}
                                        <Input
                                            id="t-logo"
                                            name="logo"
                                            type="file"
                                            accept="image/*"
                                        />
                                        <InputError message={errors.logo} />
                                    </div>
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Save changes
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {tenant.is_owner && (
                    <Card className="border-destructive/30">
                        <CardHeader>
                            <CardTitle className="text-destructive">
                                Danger zone
                            </CardTitle>
                            <CardDescription>
                                Deleting the tenant soft-deletes it for 30 days
                                and suspends all members&apos; access.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <div className="font-medium">
                                        Delete tenant
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Status:{' '}
                                        <Badge variant="outline">
                                            {tenant.status}
                                        </Badge>
                                    </div>
                                </div>
                                <Button
                                    variant="destructive"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    Delete tenant
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <DeleteTenantDialog
                open={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                tenant={tenant}
                slug={slug}
            />
        </>
    );
}

function DeleteTenantDialog({
    open,
    onClose,
    tenant,
    slug,
}: {
    open: boolean;
    onClose: () => void;
    tenant: Tenant;
    slug: string;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(v) => !v && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete tenant?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This soft-deletes{' '}
                        <span className="font-medium">{tenant.name}</span> and
                        suspends every member&apos;s access. Recovery is
                        possible within 30 days.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...TenantsController.destroy.form({ tenantSlug: slug })}
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
                                Delete tenant
                            </Button>
                        </AlertDialogFooter>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

TenantSettings.layout = ({
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
            { title: 'Settings', href: tenantRoutes.settings({ tenantSlug: slug }) },
        ],
    };
};
