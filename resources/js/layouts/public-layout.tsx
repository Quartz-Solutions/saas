import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { show as getStarted } from '@/actions/App/Http/Controllers/Onboarding/GetStartedController';
import BrandMark from '@/components/brand-mark';
import CookieConsentBanner from '@/components/cookie-consent-banner';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { home } from '@/routes';
import marketingRoutes from '@/routes/marketing';
import docsRoutes from '@/routes/marketing/docs';
import legalRoutes from '@/routes/marketing/legal';
import type { Auth } from '@/types';

type MenuItem = {
    label: string;
    url: string;
    target?: '_self' | '_blank';
    children?: MenuItem[];
};

type FooterColumn = {
    title: string;
    items: Array<{ label: string; url: string }>;
};

type CmsGlobals = {
    brand?: {
        logo_light_url?: string | null;
        logo_dark_url?: string | null;
    };
    header_menu?: { items?: MenuItem[] };
    footer_menu?: {
        columns?: FooterColumn[];
        copyright_line?: string;
        tagline?: string;
    };
    announcement?: {
        enabled?: boolean;
        message?: string;
        link_url?: string;
        link_label?: string;
        variant?: 'info' | 'success' | 'warning';
    };
    contact?: {
        company_name?: string;
        support_url?: string;
    };
};

type SharedProps = {
    name: string;
    auth: { user: Auth['user'] | null };
    cookieConsent: 'accepted' | 'rejected' | null;
    canRegister?: boolean;
    cmsGlobals?: CmsGlobals;
};

const ANN_CLASS: Record<NonNullable<NonNullable<CmsGlobals['announcement']>['variant']>, string> = {
    info: 'bg-primary/10 text-primary',
    success: 'bg-emerald-500/10 text-emerald-600',
    warning: 'bg-amber-500/10 text-amber-700',
};

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { name, auth, cookieConsent, canRegister, cmsGlobals } = usePage<SharedProps>().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    const brand = cmsGlobals?.brand;
    const announcement = cmsGlobals?.announcement;
    const headerItems = cmsGlobals?.header_menu?.items ?? [
        { label: 'Features', url: home().url + '#features' },
        { label: 'Pricing', url: marketingRoutes.pricing().url },
        { label: 'Docs', url: docsRoutes.index().url },
    ];
    const footerColumns = cmsGlobals?.footer_menu?.columns ?? [
        {
            title: 'Product',
            items: [
                { label: 'Pricing', url: marketingRoutes.pricing().url },
                { label: 'Docs', url: docsRoutes.index().url },
                { label: 'Features', url: home().url + '#features' },
            ],
        },
        {
            title: 'Legal',
            items: [
                { label: 'Privacy', url: legalRoutes.show('privacy').url },
                { label: 'Terms', url: legalRoutes.show('terms').url },
                { label: 'Cookies', url: legalRoutes.show('cookies').url },
            ],
        },
    ];

    const footerTagline = cmsGlobals?.footer_menu?.tagline ||
        'The Laravel + Inertia + React SaaS boilerplate. Multi-tenant, multi-gateway billing, admin scope — fork it and ship faster.';
    const copyrightLine = (cmsGlobals?.footer_menu?.copyright_line || '© {year} {site}. All rights reserved.')
        .replace('{year}', String(new Date().getFullYear()))
        .replace('{site}', name);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            {announcement?.enabled && announcement.message && (
                <div
                    className={cn('w-full px-4 py-2 text-center text-sm', ANN_CLASS[announcement.variant ?? 'info'])}
                    data-test="public-announcement"
                >
                    <span>{announcement.message}</span>
                    {announcement.link_url && announcement.link_label && (
                        <Link href={announcement.link_url} className="ml-2 underline">
                            {announcement.link_label}
                        </Link>
                    )}
                </div>
            )}

            <header
                className="sticky top-0 z-30 border-b border-border/40 bg-background/80 backdrop-blur"
                data-test="public-header"
            >
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-3 md:px-6">
                    <Link href={home().url} className="flex items-center gap-2 font-semibold" data-test="public-logo">
                        <BrandMark appOnly variant="plain" className="size-7" />
                        {!brand?.logo_light_url && <span>{name}</span>}
                    </Link>

                    <nav className="hidden items-center gap-6 md:flex" data-test="public-nav">
                        {headerItems.map((item) => (
                            <Link
                                key={item.url + item.label}
                                href={item.url}
                                className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                                target={item.target}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="hidden items-center gap-2 md:flex">
                        {auth?.user ? (
                            <Button asChild size="sm" data-test="public-dashboard-cta">
                                <Link href="/dashboard">Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild size="sm" variant="ghost" data-test="public-login">
                                    <Link href={login().url}>Login</Link>
                                </Button>
                                {canRegister !== false && (
                                    <Button asChild size="sm" data-test="public-signup">
                                        <Link href={getStarted().url}>Get started</Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </div>

                    <Button
                        variant="ghost"
                        size="icon"
                        className="md:hidden"
                        onClick={() => setMobileOpen((open) => !open)}
                        aria-label="Toggle menu"
                        data-test="public-mobile-menu-toggle"
                    >
                        {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                    </Button>
                </div>

                {mobileOpen && (
                    <div className="border-t border-border/40 bg-background md:hidden" data-test="public-mobile-menu">
                        <nav className="flex flex-col gap-1 px-4 py-3">
                            {headerItems.map((item) => (
                                <Link
                                    key={item.url + item.label}
                                    href={item.url}
                                    className="rounded-md px-2 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    onClick={() => setMobileOpen(false)}
                                >
                                    {item.label}
                                </Link>
                            ))}
                            <div className="mt-2 flex flex-col gap-2 border-t pt-3">
                                {auth?.user ? (
                                    <Button asChild size="sm">
                                        <Link href="/dashboard">Dashboard</Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button asChild size="sm" variant="ghost">
                                            <Link href={login().url}>Login</Link>
                                        </Button>
                                        {canRegister !== false && (
                                            <Button asChild size="sm">
                                                <Link href={getStarted().url}>Get started</Link>
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>
                        </nav>
                    </div>
                )}
            </header>

            <main className={cn('flex-1')} data-test="public-main">
                {children}
            </main>

            <footer className="border-t border-border/40 bg-muted/30" data-test="public-footer">
                <div className="mx-auto w-full max-w-6xl px-4 py-10 md:px-6">
                    <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
                        <div className="col-span-2">
                            <Link href={home().url} className="flex items-center gap-2 font-semibold">
                                <BrandMark appOnly variant="plain" className="size-6" />
                                <span>{name}</span>
                            </Link>
                            <p className="mt-3 max-w-sm text-sm text-muted-foreground">{footerTagline}</p>
                        </div>
                        {footerColumns.map((col, i) => (
                            <div key={`${col.title}-${i}`}>
                                <h3 className="text-sm font-semibold">{col.title}</h3>
                                <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                                    {col.items.map((item, j) => (
                                        <li key={`${item.url}-${j}`}>
                                            <Link href={item.url} className="hover:text-foreground">
                                                {item.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>

                    <div className="mt-10 flex flex-col gap-3 border-t pt-6 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                        <p data-test="public-copyright">{copyrightLine}</p>
                        <p>Built with Laravel, Inertia, React.</p>
                    </div>
                </div>
            </footer>

            <CookieConsentBanner initialChoice={cookieConsent ?? null} />
        </div>
    );
}
