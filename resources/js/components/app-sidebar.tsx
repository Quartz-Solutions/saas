import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRightLeft,
    BookOpen,
    Building2,
    ClipboardList,
    Cog,
    Component,
    CreditCard,
    FileText,
    Flag,
    LayoutGrid,
    Mail,
    Palette,
    Receipt,
    Settings as SettingsIcon,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
    UsersRound,
    Wallet,
    Webhook,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { index as adminAudit } from '@/routes/admin/audit';
import { index as adminCheckoutSessions } from '@/routes/admin/checkout-sessions';
import { index as adminCmsBlogPosts } from '@/routes/admin/cms/blog/posts';
import { index as adminCmsCollections } from '@/routes/admin/cms/collections';
import { index as adminCmsForms } from '@/routes/admin/cms/forms';
import { index as adminCmsGlobals } from '@/routes/admin/cms/globals';
import { index as adminCmsMedia } from '@/routes/admin/cms/media';
import { index as adminCmsPages } from '@/routes/admin/cms/pages';
import { index as adminCmsRedirects } from '@/routes/admin/cms/redirects';
import { index as adminFeatureFlags } from '@/routes/admin/feature-flags';
import { index as adminGateways } from '@/routes/admin/gateways';
import { index as adminPlans } from '@/routes/admin/plans';
import { index as adminSettings } from '@/routes/admin/settings';
import { index as adminSubscriptions } from '@/routes/admin/subscriptions';
import { index as adminTenants } from '@/routes/admin/tenants';
import { index as adminThemes } from '@/routes/admin/themes';
import { index as adminUsers } from '@/routes/admin/users';
import { index as adminWebhooks } from '@/routes/admin/webhooks';
import tenantRoutes from '@/routes/tenants';
import type { NavItem } from '@/types';

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
                  title: 'Members',
                  href: tenantRoutes.members.index({ tenantSlug }),
                  icon: UsersRound,
              },
              {
                  title: 'Invitations',
                  href: tenantRoutes.invitations.index({ tenantSlug }),
                  icon: Mail,
              },
              {
                  title: 'Billing',
                  href: tenantRoutes.billing.plans({ tenantSlug }),
                  icon: CreditCard,
              },
              {
                  title: 'Invoices',
                  href: tenantRoutes.billing.invoices.index({ tenantSlug }),
                  icon: Receipt,
              },
              {
                  title: 'Checkout history',
                  href: tenantRoutes.billing.checkoutHistory({ tenantSlug }),
                  icon: ShoppingCart,
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
        children: [
            { title: 'Overview', href: adminDashboard(), icon: LayoutGrid },
            { title: 'Tenants', href: adminTenants(), icon: Building2 },
            { title: 'Users', href: adminUsers(), icon: UsersRound },
            { title: 'Plans', href: adminPlans(), icon: CreditCard },
            { title: 'Subscriptions', href: adminSubscriptions(), icon: Receipt },
            { title: 'Checkout sessions', href: adminCheckoutSessions(), icon: ShoppingCart },
            { title: 'Payment gateways', href: adminGateways(), icon: Wallet },
            { title: 'Webhooks', href: adminWebhooks(), icon: Webhook },
            { title: 'Audit log', href: adminAudit(), icon: ClipboardList },
            { title: 'Feature flags', href: adminFeatureFlags(), icon: Flag },
            { title: 'Themes', href: adminThemes(), icon: Palette },
            { title: 'Settings', href: adminSettings(), icon: Cog },
        ],
    };

    const cmsEntry: NavItem = {
        title: 'CMS',
        href: adminCmsPages(),
        icon: Sparkles,
        children: [
            { title: 'Pages', href: adminCmsPages(), icon: FileText },
            { title: 'Blog', href: adminCmsBlogPosts(), icon: BookOpen },
            { title: 'Globals', href: adminCmsGlobals(), icon: SettingsIcon },
            { title: 'Media', href: adminCmsMedia(), icon: Component },
            { title: 'Features', href: adminCmsCollections({ type: 'features' }), icon: Sparkles },
            { title: 'Testimonials', href: adminCmsCollections({ type: 'testimonials' }), icon: UsersRound },
            { title: 'FAQs', href: adminCmsCollections({ type: 'faqs' }), icon: BookOpen },
            { title: 'Logos', href: adminCmsCollections({ type: 'logos' }), icon: ShieldCheck },
            { title: 'Forms', href: adminCmsForms(), icon: Mail },
            { title: 'Redirects', href: adminCmsRedirects(), icon: ArrowRightLeft },
        ],
    };

    const mainNavItems: NavItem[] = isSuperAdmin
        ? [...tenantNavItems, adminEntry, cmsEntry]
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
