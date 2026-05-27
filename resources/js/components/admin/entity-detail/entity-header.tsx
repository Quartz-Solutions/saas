import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { StatusDot  } from './status-dot';
import type {EntityStatus} from './status-dot';

export type Crumb = { label: string; href?: string };

export type EntityHeaderProps = {
    /** Back arrow target — usually the index page. */
    backHref?: string;
    backLabel?: string;
    /** Inline breadcrumb segments rendered after the back arrow. */
    breadcrumb?: Crumb[];
    /** Optional avatar image URL. */
    avatarUrl?: string | null;
    /** 1–2 char fallback initials for the avatar. */
    avatarFallback?: string;
    /** Heading line — e.g. tenant or user name. */
    name: string;
    /** Subtitle line — slug, email, etc. */
    subtitle?: React.ReactNode;
    /** Status dot rendered next to the name. */
    statusDot?: EntityStatus | string | null;
    /** Right-aligned actions slot (usually <ActionsMenu />). */
    actions?: React.ReactNode;
    className?: string;
};

export function EntityHeader({
    backHref,
    backLabel = 'Back',
    breadcrumb,
    avatarUrl,
    avatarFallback,
    name,
    subtitle,
    statusDot,
    actions,
    className,
}: EntityHeaderProps) {
    return (
        <div
            data-test="entity-header"
            className={cn('flex flex-col gap-4', className)}
        >
            {(backHref || breadcrumb) && (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    {backHref && (
                        <Button asChild variant="ghost" size="sm" className="-ml-2 h-7 px-2">
                            <Link href={backHref}>
                                <ArrowLeft className="size-4" />
                                {backLabel}
                            </Link>
                        </Button>
                    )}
                    {breadcrumb && breadcrumb.length > 0 && (
                        <nav className="flex items-center gap-1">
                            {breadcrumb.map((c, i) => (
                                <span key={`${c.label}-${i}`} className="flex items-center gap-1">
                                    {i > 0 && <ChevronRight className="size-3.5" />}
                                    {c.href ? (
                                        <Link
                                            href={c.href}
                                            className="hover:text-foreground"
                                        >
                                            {c.label}
                                        </Link>
                                    ) : (
                                        <span className={i === breadcrumb.length - 1 ? 'text-foreground' : ''}>
                                            {c.label}
                                        </span>
                                    )}
                                </span>
                            ))}
                        </nav>
                    )}
                </div>
            )}

            <div className="flex items-start justify-between gap-4">
                <div className="flex items-center gap-3">
                    {(avatarUrl || avatarFallback) && (
                        <Avatar className="size-12 rounded-lg">
                            {avatarUrl && <AvatarImage src={avatarUrl} alt={name} />}
                            <AvatarFallback className="rounded-lg text-sm font-semibold">
                                {avatarFallback ?? name.slice(0, 2).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                    )}
                    <div className="flex flex-col gap-0.5">
                        <div className="flex items-center gap-2">
                            <h1
                                data-test="entity-name"
                                className="text-xl font-semibold tracking-tight"
                            >
                                {name}
                            </h1>
                            {statusDot && <StatusDot status={statusDot} />}
                        </div>
                        {subtitle && (
                            <div className="text-sm text-muted-foreground">{subtitle}</div>
                        )}
                    </div>
                </div>
                {actions && <div className="flex items-center gap-2">{actions}</div>}
            </div>
        </div>
    );
}
