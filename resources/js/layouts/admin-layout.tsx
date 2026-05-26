import { Link, router, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    Cog,
    CreditCard,
    Flag,
    LayoutGrid,
    LogOut,
    Receipt,
    Webhook,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';
import { dashboard as adminDashboard, stopImpersonating } from '@/routes/admin';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as featureFlagsIndex } from '@/routes/admin/feature-flags';
import { index as plansIndex } from '@/routes/admin/plans';
import { index as settingsIndex } from '@/routes/admin/settings';
import { index as subscriptionsIndex } from '@/routes/admin/subscriptions';
import { index as tenantsIndex } from '@/routes/admin/tenants';
import { index as webhooksIndex } from '@/routes/admin/webhooks';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: adminDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Tenants',
        href: tenantsIndex(),
        icon: Building2,
    },
    {
        title: 'Plans',
        href: plansIndex(),
        icon: CreditCard,
    },
    {
        title: 'Subscriptions',
        href: subscriptionsIndex(),
        icon: Receipt,
    },
    {
        title: 'Webhooks',
        href: webhooksIndex(),
        icon: Webhook,
    },
    {
        title: 'Audit log',
        href: auditIndex(),
        icon: ClipboardList,
    },
    {
        title: 'Feature flags',
        href: featureFlagsIndex(),
        icon: Flag,
    },
    {
        title: 'Settings',
        href: settingsIndex(),
        icon: Cog,
    },
];

type ImpersonationProp = {
    impersonator: { id: number; name: string; email: string };
} | null;

export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { impersonation } = usePage<{ impersonation: ImpersonationProp }>().props;

    return (
        <div className="px-4 py-6">
            {impersonation && (
                <div
                    data-test="impersonation-banner"
                    className="mb-6 flex flex-col gap-2 rounded-md border border-amber-500/40 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:bg-amber-950/40 dark:text-amber-100"
                >
                    <span>
                        You are impersonating —{' '}
                        <span className="font-medium">
                            originally signed in as {impersonation.impersonator.email}
                        </span>
                        .
                    </span>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => router.post(stopImpersonating().url)}
                    >
                        <LogOut className="size-4" />
                        Stop impersonating
                    </Button>
                </div>
            )}

            <Heading
                title="Admin"
                description="Internal staff tools — bypasses tenant scoping."
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-56">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Admin"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1">{children}</div>
            </div>
        </div>
    );
}
