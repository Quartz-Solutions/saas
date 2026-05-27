import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type SharedProps = {
    name?: string;
    /** CMS globals → brand: single source of truth for app branding. */
    cmsGlobals?: {
        brand?: {
            logo_light_url?: string | null;
            logo_dark_url?: string | null;
        };
    };
    /** Tenant logo (set in /t/{slug}/settings) — preferred when in tenant scope. */
    currentTenant?: {
        name: string;
        logo_url?: string | null;
        logo_path?: string | null;
    } | null;
};

export type BrandMarkProps = {
    className?: string;
    innerClassName?: string;
    /** Force the global app brand, ignoring the current tenant. */
    appOnly?: boolean;
    /** Background variant for the placeholder square. */
    variant?: 'tinted' | 'plain';
};

/**
 * Single source of truth for the app logo:
 *   1. Current tenant's logo (when in tenant scope, unless appOnly)
 *   2. CMS Globals → Brand & theme → logo_{light,dark}_url
 *      (admin-editable at /admin/cms/globals/brand)
 *   3. Default AppLogoIcon SVG
 *
 * Light/dark mode handled via two `<img>` tags + `dark:` classes when the
 * brand has separate dark-mode logo configured.
 */
export default function BrandMark({
    className,
    innerClassName,
    appOnly = false,
    variant = 'tinted',
}: BrandMarkProps) {
    const { currentTenant, cmsGlobals, name } = usePage<SharedProps>().props;

    const tenantLogo = appOnly
        ? null
        : (currentTenant?.logo_url ?? currentTenant?.logo_path ?? null);

    const brand = cmsGlobals?.brand;
    const light = tenantLogo ?? brand?.logo_light_url ?? null;
    const dark = tenantLogo ?? brand?.logo_dark_url ?? brand?.logo_light_url ?? null;

    const alt = (appOnly ? null : currentTenant?.name) ?? name ?? 'App';

    const wrapperClass = cn(
        'flex aspect-square items-center justify-center overflow-hidden rounded-md',
        variant === 'tinted'
            ? 'bg-sidebar-primary text-sidebar-primary-foreground'
            : 'bg-muted',
        'size-8',
        className,
    );

    // No brand image configured at all → default SVG icon.
    if (!light && !dark) {
        return (
            <div data-test="brand-mark" className={wrapperClass}>
                <AppLogoIcon
                    className={cn(
                        'size-5 fill-current text-white dark:text-black',
                        innerClassName,
                    )}
                />
            </div>
        );
    }

    return (
        <div data-test="brand-mark" className={wrapperClass}>
            {light && (
                <img
                    src={light}
                    alt={alt}
                    className={cn(
                        'size-full object-cover',
                        // Hide the light image in dark mode IF a dark variant
                        // exists (otherwise we keep the same image for both).
                        dark && dark !== light ? 'dark:hidden' : '',
                        innerClassName,
                    )}
                />
            )}
            {dark && dark !== light && (
                <img
                    src={dark}
                    alt={alt}
                    className={cn(
                        'hidden size-full object-cover dark:block',
                        innerClassName,
                    )}
                />
            )}
        </div>
    );
}
