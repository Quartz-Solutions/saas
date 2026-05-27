import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    Crown,
    Mail,
    MoreHorizontal,
    Plus,
    Search,
    Settings as SettingsIcon,
    Trash2,
    Users as UsersIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import TenantsController from '@/actions/App/Http/Controllers/Tenants/TenantsController';
import BrandMark from '@/components/brand-mark';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index as tenantsIndex } from '@/routes/account/tenants';
import tenantRoutes from '@/routes/tenants';

type TenantRow = {
    id: number;
    slug: string;
    name: string;
    logo_path: string | null;
    logo_url: string | null;
    role: 'Owner' | 'Member';
    status: string;
    currency: string;
    trial_ends_at: string | null;
    memberships_count: number;
    created_at: string | null;
    last_seen_at: string | null;
    plan: {
        slug: string;
        name: string;
        price_cents: number;
        currency: string;
        billing_period: string;
        subscription_status: string;
    } | null;
    pending_invites_count: number | null;
};

type Props = {
    tenants: TenantRow[];
};

function formatMoney(cents: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: (currency || 'USD').toUpperCase(),
            maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(0)} ${currency}`;
    }
}

function formatRelative(iso: string | null): string {
    if (!iso) {
return 'Never';
}

    const ts = new Date(iso).getTime();

    if (!Number.isFinite(ts)) {
return 'Never';
}

    const diff = Date.now() - ts;
    const min = Math.floor(diff / 60_000);

    if (min < 1) {
return 'Just now';
}

    if (min < 60) {
return `${min}m ago`;
}

    const hr = Math.floor(min / 60);

    if (hr < 24) {
return `${hr}h ago`;
}

    const day = Math.floor(hr / 24);

    if (day < 7) {
return `${day}d ago`;
}

    const week = Math.floor(day / 7);

    if (week < 5) {
return `${week}w ago`;
}

    const month = Math.floor(day / 30);

    if (month < 12) {
return `${month}mo ago`;
}

    return new Date(iso).toISOString().slice(0, 10);
}

function formatJoinedDate(iso: string | null): string {
    if (!iso) {
return '—';
}

    const d = new Date(iso);

    return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
}

function statusVariant(t: TenantRow): {
    label: string;
    cls: string;
    dot: string;
} {
    if (t.status === 'suspended') {
        return {
            label: 'Suspended',
            cls: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900',
            dot: 'bg-rose-500',
        };
    }

    if (t.status === 'pending_deletion') {
        return {
            label: 'Pending deletion',
            cls: 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:border-zinc-800',
            dot: 'bg-zinc-400',
        };
    }

    if (t.trial_ends_at && new Date(t.trial_ends_at) > new Date()) {
        return {
            label: 'Trialing',
            cls: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-900',
            dot: 'bg-sky-500',
        };
    }

    if (t.plan?.subscription_status === 'past_due') {
        return {
            label: 'Past due',
            cls: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900',
            dot: 'bg-amber-500',
        };
    }

    return {
        label: 'Active',
        cls: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900',
        dot: 'bg-emerald-500',
    };
}

export default function AccountTenants({ tenants }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [deleting, setDeleting] = useState<TenantRow | null>(null);
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();

        if (!q) {
return tenants;
}

        return tenants.filter(
            (t) =>
                t.name.toLowerCase().includes(q) ||
                t.slug.toLowerCase().includes(q),
        );
    }, [tenants, search]);

    return (
        <>
            <Head title="My tenants" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="My tenants"
                        description="Tenants you own or have been invited to."
                    />
                    <Button onClick={() => setCreateOpen(true)} data-test="new-tenant">
                        <Plus className="size-4" />
                        New tenant
                    </Button>
                </div>

                {tenants.length > 0 && (
                    <div className="relative max-w-md">
                        <Search className="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search tenants..."
                            className="pl-8"
                            data-test="tenant-search"
                        />
                    </div>
                )}

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
                ) : filtered.length === 0 ? (
                    <p className="py-12 text-center text-sm text-muted-foreground">
                        No tenants match &ldquo;{search}&rdquo;.
                    </p>
                ) : (
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {filtered.map((t) => (
                            <TenantCard
                                key={t.id}
                                tenant={t}
                                onDelete={() => setDeleting(t)}
                            />
                        ))}
                    </div>
                )}
            </div>

            <CreateTenantDialog open={createOpen} onOpenChange={setCreateOpen} />
            <DeleteTenantDialog
                tenant={deleting}
                onClose={() => setDeleting(null)}
            />
        </>
    );
}

function TenantCard({
    tenant,
    onDelete,
}: {
    tenant: TenantRow;
    onDelete: () => void;
}) {
    const status = statusVariant(tenant);
    const isOwner = tenant.role === 'Owner';
    const dashboardHref = tenantRoutes.dashboard({ tenantSlug: tenant.slug });
    const settingsHref = tenantRoutes.settings({ tenantSlug: tenant.slug });

    return (
        <Card
            data-test={`tenant-card-${tenant.slug}`}
            className="group flex flex-col gap-0 overflow-hidden py-0 transition-shadow hover:shadow-md"
        >
            {/* Hero — custom logo or app-icon fallback */}
            <Link
                href={dashboardHref}
                className="relative flex aspect-[16/9] items-center justify-center overflow-hidden border-b bg-gradient-to-br from-muted/40 via-muted/20 to-transparent"
                data-test={`tenant-hero-${tenant.slug}`}
            >
                {tenant.logo_url ? (
                    <img
                        src={tenant.logo_url}
                        alt={tenant.name}
                        className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                    />
                ) : (
                    <BrandMark
                        appOnly
                        className="size-16 rounded-lg"
                        innerClassName="size-8"
                    />
                )}

                {/* Owner crown */}
                {isOwner && (
                    <span
                        className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-background/90 px-2 py-0.5 text-[10px] font-medium text-foreground backdrop-blur"
                        data-test="owner-badge"
                    >
                        <Crown className="size-3" />
                        Owner
                    </span>
                )}
            </Link>

            {/* Body */}
            <div className="flex flex-1 flex-col gap-3 p-4">
                {/* Status pill */}
                <div className="flex items-center justify-between gap-2">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium',
                            status.cls,
                        )}
                        data-test="tenant-status"
                    >
                        <span
                            className={cn('size-1.5 rounded-full', status.dot)}
                        />
                        {status.label}
                    </span>

                    {/* Actions menu */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                data-test={`tenant-menu-${tenant.slug}`}
                            >
                                <MoreHorizontal className="size-4" />
                                <span className="sr-only">Open actions</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuItem asChild>
                                <Link href={dashboardHref} className="gap-2">
                                    <ArrowRight className="size-4" />
                                    Open dashboard
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href={settingsHref} className="gap-2">
                                    <SettingsIcon className="size-4" />
                                    Settings
                                </Link>
                            </DropdownMenuItem>
                            {isOwner && (
                                <>
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={tenantRoutes.invitations.index({
                                                tenantSlug: tenant.slug,
                                            })}
                                            className="gap-2"
                                        >
                                            <Mail className="size-4" />
                                            Invite members
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={tenantRoutes.billing.plans({
                                                tenantSlug: tenant.slug,
                                            })}
                                            className="gap-2"
                                        >
                                            <UsersIcon className="size-4" />
                                            Billing &amp; plans
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        className="gap-2 text-destructive focus:bg-destructive/10 focus:text-destructive"
                                        onSelect={(e) => {
                                            e.preventDefault();
                                            onDelete();
                                        }}
                                        data-test={`tenant-delete-${tenant.slug}`}
                                    >
                                        <Trash2 className="size-4" />
                                        Delete tenant
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* Name + slug */}
                <div className="flex flex-col gap-0.5">
                    <Link
                        href={dashboardHref}
                        className="line-clamp-2 text-base font-semibold leading-snug hover:underline"
                        data-test="tenant-name"
                    >
                        {tenant.name}
                    </Link>
                    <span className="font-mono text-xs text-muted-foreground">
                        /t/{tenant.slug}
                    </span>
                </div>

                {/* Pending invites pill (Owner only) */}
                {isOwner &&
                    tenant.pending_invites_count !== null &&
                    tenant.pending_invites_count > 0 && (
                        <Link
                            href={tenantRoutes.invitations.index({
                                tenantSlug: tenant.slug,
                            })}
                            className="inline-flex w-fit items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-[11px] font-medium text-amber-800 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-200"
                            data-test="pending-invites"
                        >
                            <Mail className="size-3" />
                            {tenant.pending_invites_count} pending invite
                            {tenant.pending_invites_count === 1 ? '' : 's'}
                        </Link>
                    )}

                {/* Stat row */}
                <div className="mt-auto border-t pt-3">
                    <div className="grid grid-cols-3 gap-2">
                        <Stat
                            label="Plan"
                            value={tenant.plan?.name ?? 'Free'}
                            sub={
                                tenant.plan && tenant.plan.price_cents > 0
                                    ? `${formatMoney(
                                          tenant.plan.price_cents,
                                          tenant.plan.currency,
                                      )}/${tenant.plan.billing_period === 'year' ? 'yr' : 'mo'}`
                                    : undefined
                            }
                        />
                        <Stat
                            label="Members"
                            value={String(tenant.memberships_count)}
                        />
                        <Stat label="Role" value={tenant.role} />
                    </div>

                    <div className="mt-3 flex items-center justify-between text-[11px] text-muted-foreground">
                        <span title={tenant.last_seen_at ?? ''}>
                            Visited {formatRelative(tenant.last_seen_at)}
                        </span>
                        <span title={tenant.created_at ?? ''}>
                            Joined {formatJoinedDate(tenant.created_at)}
                        </span>
                    </div>
                </div>

                {/* Primary CTA */}
                <Button asChild className="mt-1 w-full" size="sm" data-test={`tenant-open-${tenant.slug}`}>
                    <Link href={dashboardHref}>
                        Open
                        <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </div>
        </Card>
    );
}

function Stat({
    label,
    value,
    sub,
}: {
    label: string;
    value: string;
    sub?: string;
}) {
    return (
        <div className="flex flex-col gap-0.5 text-center">
            <span className="text-[10px] uppercase tracking-wide text-muted-foreground">
                {label}
            </span>
            <span className="truncate text-sm font-medium">{value}</span>
            {sub && (
                <span className="truncate text-[10px] text-muted-foreground">
                    {sub}
                </span>
            )}
        </div>
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

function DeleteTenantDialog({
    tenant,
    onClose,
}: {
    tenant: TenantRow | null;
    onClose: () => void;
}) {
    const [confirmSlug, setConfirmSlug] = useState('');
    const matches = tenant !== null && confirmSlug === tenant.slug;

    return (
        <AlertDialog
            open={tenant !== null}
            onOpenChange={(o) => {
                if (!o) {
                    setConfirmSlug('');
                    onClose();
                }
            }}
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete {tenant?.name}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This soft-deletes the tenant. Members lose access
                        immediately. Type{' '}
                        <span className="font-mono text-foreground">
                            {tenant?.slug}
                        </span>{' '}
                        to confirm.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <Input
                    name="confirm_slug"
                    value={confirmSlug}
                    onChange={(e) => setConfirmSlug(e.target.value)}
                    placeholder="Retype slug to confirm"
                    autoComplete="off"
                    autoFocus
                />

                <AlertDialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => {
                            setConfirmSlug('');
                            onClose();
                        }}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={!matches}
                        onClick={() => {
                            if (!tenant) {
return;
}

                            router.delete(`/account/tenants/${tenant.id}`, {
                                onSuccess: () => {
                                    setConfirmSlug('');
                                    onClose();
                                },
                            });
                        }}
                        data-test="confirm-delete-tenant"
                    >
                        <Trash2 className="size-4" />
                        Delete
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

AccountTenants.layout = {
    breadcrumbs: [{ title: 'My tenants', href: tenantsIndex() }],
};
