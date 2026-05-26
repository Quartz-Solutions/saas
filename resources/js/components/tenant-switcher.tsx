import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { index as accountTenants } from '@/routes/account/tenants';
import tenantRoutes from '@/routes/tenants';

type TenantOption = {
    id: number;
    slug: string;
    name: string;
};

type Props = {
    /** Fallback list — when omitted the switcher fetches `/account/tenants?for=switcher` lazily. */
    tenants?: TenantOption[];
};

export function TenantSwitcher({ tenants: tenantsProp }: Props) {
    const { currentTenant, auth } = usePage<{
        currentTenant: { id: number; slug: string; name: string } | null;
        auth: { user: { id: number; name: string } | null };
    }>().props;

    const [tenants, setTenants] = useState<TenantOption[]>(tenantsProp ?? []);
    const [loaded, setLoaded] = useState(tenantsProp !== undefined);

    useEffect(() => {
        if (loaded || !auth.user) {
return;
}

        // Lazy-fetch the user's tenants when the menu first opens. For v0 we
        // reuse the `/account/tenants` Inertia endpoint — it ships exactly the
        // shape we need.
        fetch(accountTenants().url, {
            headers: { 'X-Inertia': 'true', Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((json) => {
                const list =
                    json?.props?.tenants ?? (Array.isArray(json) ? json : []);
                setTenants(
                    (list as Array<{ id: number; slug: string; name: string }>).map((t) => ({
                        id: t.id,
                        slug: t.slug,
                        name: t.name,
                    })),
                );
                setLoaded(true);
            })
            .catch(() => setLoaded(true));
    }, [auth.user, loaded]);

    if (!auth.user) {
return null;
}

    const handleSwitch = (slug: string) => {
        router.post(tenantRoutes.switch({ tenant: slug }).url);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <SidebarMenuButton
                    size="lg"
                    className="text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                    data-test="tenant-switcher"
                >
                    <Building2 className="size-4" />
                    <div className="flex min-w-0 flex-1 flex-col">
                        <span className="truncate text-sm font-medium">
                            {currentTenant?.name ?? 'No tenant'}
                        </span>
                        {currentTenant && (
                            <span className="truncate text-[10px] text-muted-foreground">
                                {currentTenant.slug}
                            </span>
                        )}
                    </div>
                    <ChevronsUpDown className="ml-auto size-4" />
                </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Tenants
                </DropdownMenuLabel>
                {tenants.length === 0 && (
                    <DropdownMenuItem disabled>No tenants</DropdownMenuItem>
                )}
                {tenants.map((t) => (
                    <DropdownMenuItem
                        key={t.id}
                        onSelect={() => handleSwitch(t.slug)}
                        className="gap-2"
                        data-test={`tenant-switch-${t.slug}`}
                    >
                        <Check
                            className={cn(
                                'size-4',
                                currentTenant?.id === t.id
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            )}
                        />
                        <span className="truncate">{t.name}</span>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={accountTenants()} className="gap-2">
                        <Plus className="size-4" />
                        Manage tenants
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
