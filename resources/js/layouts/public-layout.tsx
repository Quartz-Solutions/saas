import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useState  } from 'react';
import type {ReactNode} from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import CookieConsentBanner from '@/components/cookie-consent-banner';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import { home } from '@/routes';
import { show as getStarted } from '@/actions/App/Http/Controllers/Onboarding/GetStartedController';
import marketingRoutes from '@/routes/marketing';
import docsRoutes from '@/routes/marketing/docs';
import legalRoutes from '@/routes/marketing/legal';
import type { Auth } from '@/types';

type SharedProps = {
    name: string;
    auth: { user: Auth['user'] | null };
    cookieConsent: 'accepted' | 'rejected' | null;
    canRegister?: boolean;
};

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { name, auth, cookieConsent, canRegister } = usePage<SharedProps>().props;
    const [mobileOpen, setMobileOpen] = useState(false);

    const navItems: Array<{ label: string; href: string }> = [
        { label: 'Features', href: home().url + '#features' },
        { label: 'Pricing', href: marketingRoutes.pricing().url },
        { label: 'Docs', href: docsRoutes.index().url },
    ];

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header
                className="sticky top-0 z-30 border-b border-border/40 bg-background/80 backdrop-blur"
                data-test="public-header"
            >
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-3 md:px-6">
                    <Link
                        href={home().url}
                        className="flex items-center gap-2 font-semibold"
                        data-test="public-logo"
                    >
                        <AppLogoIcon className="size-6 fill-current" />
                        <span>{name}</span>
                    </Link>

                    <nav className="hidden items-center gap-6 md:flex" data-test="public-nav">
                        {navItems.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className="text-sm text-muted-foreground transition-colors hover:text-foreground"
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
                    <div
                        className="border-t border-border/40 bg-background md:hidden"
                        data-test="public-mobile-menu"
                    >
                        <nav className="flex flex-col gap-1 px-4 py-3">
                            {navItems.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
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

            <footer
                className="border-t border-border/40 bg-muted/30"
                data-test="public-footer"
            >
                <div className="mx-auto w-full max-w-6xl px-4 py-10 md:px-6">
                    <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
                        <div className="col-span-2">
                            <Link href={home().url} className="flex items-center gap-2 font-semibold">
                                <AppLogoIcon className="size-5 fill-current" />
                                <span>{name}</span>
                            </Link>
                            <p className="mt-3 max-w-sm text-sm text-muted-foreground">
                                The Laravel + Inertia + React SaaS boilerplate. Multi-tenant,
                                multi-gateway billing, admin scope — fork it and ship faster.
                            </p>
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold">Product</h3>
                            <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                                <li><Link href={marketingRoutes.pricing().url} className="hover:text-foreground">Pricing</Link></li>
                                <li><Link href={docsRoutes.index().url} className="hover:text-foreground">Docs</Link></li>
                                <li><Link href={home().url + '#features'} className="hover:text-foreground">Features</Link></li>
                            </ul>
                        </div>
                        <div>
                            <h3 className="text-sm font-semibold">Legal</h3>
                            <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
                                <li>
                                    <Link
                                        href={legalRoutes.show('privacy').url}
                                        className="hover:text-foreground"
                                        data-test="footer-privacy"
                                    >
                                        Privacy
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href={legalRoutes.show('terms').url}
                                        className="hover:text-foreground"
                                        data-test="footer-terms"
                                    >
                                        Terms
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        href={legalRoutes.show('cookies').url}
                                        className="hover:text-foreground"
                                        data-test="footer-cookies"
                                    >
                                        Cookies
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div className="mt-10 flex flex-col gap-3 border-t pt-6 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                        <p data-test="public-copyright">
                            &copy; {new Date().getFullYear()} {name}. All rights reserved.
                        </p>
                        <p>Built with Laravel, Inertia, React.</p>
                    </div>
                </div>
            </footer>

            <CookieConsentBanner initialChoice={cookieConsent ?? null} />
        </div>
    );
}
