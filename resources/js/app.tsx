import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import SettingsLayout from '@/layouts/settings/layout';

const envFallback = import.meta.env.VITE_APP_NAME || 'App';

// Mutable brand string read by the title callback. We keep it in sync with
// the current Inertia page's shared props: when the user is inside a tenant
// the brand is the tenant name (white-label); otherwise it falls back to the
// global `name` shared prop (admin-editable via /admin/settings) and finally
// to VITE_APP_NAME.
let currentBrand = envFallback;

type BrandProps = {
    name?: string;
    currentTenant?: { name?: string | null } | null;
};

function brandFromPage(props: BrandProps | undefined | null): string {
    if (props?.currentTenant?.name) {
return props.currentTenant.name;
}

    if (props?.name) {
return props.name;
}

    return envFallback;
}

function readInitialBrand(): string {
    if (typeof document === 'undefined') {
return envFallback;
}

    try {
        const el = document.getElementById('app');
        const raw = el?.dataset.page;

        if (!raw) {
return envFallback;
}

        const page = JSON.parse(raw);

        return brandFromPage(page?.props as BrandProps);
    } catch {
        return envFallback;
    }
}

currentBrand = readInitialBrand();

// On every navigation, refresh the brand BEFORE Inertia's <Head> re-renders
// (which is what calls the title callback below).
router.on('navigate', (event) => {
    currentBrand = brandFromPage(
        (event as unknown as { detail?: { page?: { props?: BrandProps } } })
            .detail?.page?.props,
    );
});

createInertiaApp({
    title: (title) => {
        if (!title) {
return currentBrand;
}

        // Pages (e.g. marketing docs via <SeoMeta>) may already render a
        // "{page} - {site}" title themselves. Don't double-append the brand
        // when it's already there, or when the page title IS the brand.
        const suffix = ` - ${currentBrand}`;

        if (title === currentBrand || title.endsWith(suffix)) {
return title;
}

        return `${title}${suffix}`;
    },
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('marketing/'):
                return PublicLayout;
            case name.startsWith('onboarding/'):
                return PublicLayout;
            case name.startsWith('checkout/'):
                return PublicLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('admin/'):
                return [AppLayout, AdminLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
