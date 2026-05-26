import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    Component,
    FolderGit2,
    LayoutGrid,
    Mail,
    Settings as SettingsIcon,
    ShieldCheck,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TenantSwitcher } from '@/components/tenant-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard as rootDashboard } from '@/routes';
import { index as accountTenants } from '@/routes/account/tenants';
import { dashboard as adminDashboard } from '@/routes/admin';
import tenantRoutes from '@/routes/tenants';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth, currentTenant } = usePage<{
        auth: { user: { id: number } | null; isSuperAdmin?: boolean };
        currentTenant: { slug: string; name: string } | null;
    }>().props;

    const tenantSlug = currentTenant?.slug;
    const isSuperAdmin = !!auth?.isSuperAdmin;

    const tenantNavItems: NavItem[] = tenantSlug
        ? [
              {
                  title: 'Dashboard',
                  href: tenantRoutes.dashboard({ tenantSlug }),
                  icon: LayoutGrid,
              },
              {
                  title: 'Users',
                  href: tenantRoutes.users.index({ tenantSlug }),
                  icon: UsersRound,
              },
              {
                  title: 'Invitations',
                  href: tenantRoutes.invitations.index({ tenantSlug }),
                  icon: Mail,
              },
              {
                  title: 'Settings',
                  href: tenantRoutes.settings({ tenantSlug }),
                  icon: SettingsIcon,
              },
              {
                  title: 'Shared Components',
                  href: tenantRoutes.sharedComponents({ tenantSlug }),
                  icon: Component,
              },
          ]
        : [
              {
                  title: 'My tenants',
                  href: accountTenants(),
                  icon: Building2,
              },
          ];

    const adminEntry: NavItem = {
        title: 'Admin',
        href: adminDashboard(),
        icon: ShieldCheck,
    };

    const mainNavItems: NavItem[] = isSuperAdmin
        ? [...tenantNavItems, adminEntry]
        : tenantNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link
                                href={
                                    tenantSlug
                                        ? tenantRoutes.dashboard({ tenantSlug })
                                        : rootDashboard()
                                }
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <TenantSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
