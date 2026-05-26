import { router, usePage } from '@inertiajs/react';
import {
    Building2,
    Check,
    Component,
    KeyRound,
    LayoutGrid,
    Mail,
    Monitor,
    Palette,
    Settings as SettingsIcon,
    UsersRound,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from '@/components/ui/command';
import { index as accountTenants } from '@/routes/account/tenants';
import { edit as appearanceEdit } from '@/routes/appearance';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';
import { index as sessionsIndex } from '@/routes/sessions';
import tenantRoutes from '@/routes/tenants';
import { switchMethod as tenantSwitch } from '@/routes/tenants';

type TenantOption = { id: number; slug: string; name: string };

type RecentItem = {
    label: string;
    href: string;
};

const RECENT_KEY = 'command-palette:recent';

function loadRecent(): RecentItem[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(RECENT_KEY);

        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw);

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed
            .filter(
                (i): i is RecentItem =>
                    typeof i === 'object' &&
                    i !== null &&
                    typeof i.label === 'string' &&
                    typeof i.href === 'string',
            )
            .slice(0, 5);
    } catch {
        return [];
    }
}

function saveRecent(items: RecentItem[]): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            RECENT_KEY,
            JSON.stringify(items.slice(0, 5)),
        );
    } catch {
        // localStorage may be unavailable; ignore.
    }
}

export default function CommandPalette() {
    const { currentTenant, auth } = usePage<{
        currentTenant: { id: number; slug: string; name: string } | null;
        auth: { user: { id: number; name: string } | null };
    }>().props;

    const [open, setOpen] = useState(false);
    const [tenants, setTenants] = useState<TenantOption[]>([]);
    const [tenantsLoaded, setTenantsLoaded] = useState(false);
    const [recent, setRecent] = useState<RecentItem[]>(() => loadRecent());

    // Cmd+K / Ctrl+K opens the palette.
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setOpen((prev) => !prev);
            }
        };
        window.addEventListener('keydown', handler);

        return () => window.removeEventListener('keydown', handler);
    }, []);

    // Lazy-load the user's tenants when the palette first opens.
    useEffect(() => {
        if (!open || tenantsLoaded || !auth.user) {
            return;
        }

        fetch(accountTenants().url, {
            headers: { 'X-Inertia': 'true', Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((json) => {
                const list =
                    json?.props?.tenants ?? (Array.isArray(json) ? json : []);
                setTenants(
                    (
                        list as Array<{
                            id: number;
                            slug: string;
                            name: string;
                        }>
                    ).map((t) => ({
                        id: t.id,
                        slug: t.slug,
                        name: t.name,
                    })),
                );
                setTenantsLoaded(true);
            })
            .catch(() => setTenantsLoaded(true));
    }, [open, tenantsLoaded, auth.user]);

    const slug = currentTenant?.slug;

    const navItems = useMemo(() => {
        const items: Array<{
            label: string;
            href: string;
            icon: typeof LayoutGrid;
            keywords?: string[];
        }> = [];

        if (slug) {
            items.push(
                {
                    label: 'Dashboard',
                    href: tenantRoutes.dashboard({ tenantSlug: slug }).url,
                    icon: LayoutGrid,
                    keywords: ['home', 'overview'],
                },
                {
                    label: 'Users',
                    href: tenantRoutes.users.index({ tenantSlug: slug }).url,
                    icon: UsersRound,
                    keywords: ['members', 'team'],
                },
                {
                    label: 'Invitations',
                    href: tenantRoutes.invitations.index({ tenantSlug: slug })
                        .url,
                    icon: Mail,
                    keywords: ['invite'],
                },
                {
                    label: 'Tenant Settings',
                    href: tenantRoutes.settings({ tenantSlug: slug }).url,
                    icon: SettingsIcon,
                    keywords: ['workspace', 'organization'],
                },
                {
                    label: 'Shared Components',
                    href: tenantRoutes.sharedComponents({ tenantSlug: slug })
                        .url,
                    icon: Component,
                    keywords: ['ui', 'catalog'],
                },
            );
        }

        items.push(
            {
                label: 'My tenants',
                href: accountTenants().url,
                icon: Building2,
                keywords: ['workspaces', 'organizations'],
            },
            {
                label: 'Profile',
                href: profileEdit().url,
                icon: UsersRound,
                keywords: ['account', 'me'],
            },
            {
                label: 'Security',
                href: securityEdit().url,
                icon: KeyRound,
                keywords: ['password', '2fa', 'two factor'],
            },
            {
                label: 'Sessions',
                href: sessionsIndex().url,
                icon: Monitor,
                keywords: ['devices', 'logins'],
            },
            {
                label: 'Appearance',
                href: appearanceEdit().url,
                icon: Palette,
                keywords: ['theme', 'dark mode', 'light mode'],
            },
        );

        return items;
    }, [slug]);

    const go = useCallback(
        (item: { label: string; href: string }) => {
            setOpen(false);
            const next: RecentItem[] = [
                { label: item.label, href: item.href },
                ...recent.filter((r) => r.href !== item.href),
            ].slice(0, 5);
            setRecent(next);
            saveRecent(next);
            router.visit(item.href);
        },
        [recent],
    );

    const switchTenant = useCallback((target: TenantOption) => {
        setOpen(false);
        router.post(tenantSwitch({ tenant: target.slug }).url);
    }, []);

    if (!auth.user) {
        return null;
    }

    return (
        <CommandDialog
            open={open}
            onOpenChange={setOpen}
            title="Command Palette"
            description="Navigate or switch tenants"
        >
            <CommandInput
                placeholder="Type a command or search..."
                data-test="command-palette-input"
            />
            <CommandList>
                <CommandEmpty>No results found.</CommandEmpty>

                {recent.length > 0 && (
                    <>
                        <CommandGroup heading="Recent">
                            {recent.map((item) => (
                                <CommandItem
                                    key={`recent-${item.href}`}
                                    value={`recent ${item.label}`}
                                    onSelect={() => go(item)}
                                    data-test="command-palette-recent"
                                >
                                    <LayoutGrid />
                                    <span>{item.label}</span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                        <CommandSeparator />
                    </>
                )}

                <CommandGroup heading="Navigate">
                    {navItems.map((item) => {
                        const Icon = item.icon;

                        return (
                            <CommandItem
                                key={item.href}
                                value={`${item.label} ${(item.keywords ?? []).join(' ')}`}
                                onSelect={() => go(item)}
                                data-test={`command-palette-nav-${item.label.toLowerCase().replace(/\s+/g, '-')}`}
                            >
                                <Icon />
                                <span>{item.label}</span>
                            </CommandItem>
                        );
                    })}
                </CommandGroup>

                {tenants.length > 0 && (
                    <>
                        <CommandSeparator />
                        <CommandGroup heading="Switch tenant">
                            {tenants.map((t) => (
                                <CommandItem
                                    key={`tenant-${t.id}`}
                                    value={`switch tenant ${t.name} ${t.slug}`}
                                    onSelect={() => switchTenant(t)}
                                    data-test={`command-palette-tenant-${t.slug}`}
                                >
                                    <Building2 />
                                    <span>Switch to {t.name}</span>
                                    {currentTenant?.id === t.id && (
                                        <CommandShortcut>
                                            <Check className="size-4" />
                                        </CommandShortcut>
                                    )}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </>
                )}

                <CommandSeparator />
                <CommandGroup heading="Hints">
                    <CommandItem
                        value="hint shortcut"
                        disabled
                        className="opacity-60"
                    >
                        <span>Press</span>
                        <CommandShortcut>Cmd / Ctrl + K</CommandShortcut>
                        <span>to toggle this palette</span>
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    );
}
