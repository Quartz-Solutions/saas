import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    LogOut,
    Mail,
    XCircle,
} from 'lucide-react';
import BrandMark from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index as accountTenants } from '@/routes/account/tenants';
import { logout } from '@/routes';

type ReasonKey =
    | 'expired'
    | 'revoked'
    | 'already_accepted'
    | 'wrong_email'
    | 'not_found'
    | 'invalid';

type TenantBrief = {
    id: number;
    slug: string;
    name: string;
    logo_path: string | null;
} | null;

type Props = {
    reason: ReasonKey;
    title: string;
    message: string;
    tenant: TenantBrief;
    invitedEmail: string | null;
};

const ICONS: Record<ReasonKey, typeof AlertTriangle> = {
    expired: Clock,
    revoked: XCircle,
    already_accepted: Mail,
    wrong_email: Mail,
    not_found: AlertTriangle,
    invalid: AlertTriangle,
};

const ACCENT: Record<ReasonKey, string> = {
    expired: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30',
    revoked: 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/30',
    already_accepted:
        'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30',
    wrong_email:
        'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/30',
    not_found:
        'text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-900',
    invalid:
        'text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-900',
};

export default function InvitationInvalid({
    reason,
    title,
    message,
    tenant,
    invitedEmail,
}: Props) {
    const { auth } = usePage<{
        auth: { user: { id: number; email: string } | null };
    }>().props;

    const Icon = ICONS[reason] ?? AlertTriangle;
    const accent = ACCENT[reason] ?? ACCENT.invalid;

    return (
        <>
            <Head title={title} />

            <div className="flex min-h-svh items-center justify-center bg-muted/30 p-6">
                <div className="w-full max-w-md">
                    <div className="mb-6 flex justify-center">
                        <BrandMark appOnly className="size-10" innerClassName="size-6" />
                    </div>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="flex flex-col items-center gap-4 px-6 py-8 text-center">
                            <div
                                className={cn(
                                    'flex size-14 items-center justify-center rounded-full',
                                    accent,
                                )}
                                data-test="invitation-invalid-icon"
                            >
                                <Icon className="size-7" strokeWidth={1.5} />
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <h1
                                    className="text-xl font-semibold tracking-tight"
                                    data-test="invitation-invalid-title"
                                >
                                    {title}
                                </h1>
                                <p
                                    className="text-sm text-muted-foreground"
                                    data-test="invitation-invalid-message"
                                >
                                    {message}
                                </p>
                            </div>

                            {tenant && (
                                <div className="mt-2 flex w-full items-center justify-between gap-3 rounded-md border bg-muted/40 px-3 py-2 text-left text-sm">
                                    <span className="text-muted-foreground">
                                        Workspace
                                    </span>
                                    <span
                                        className="truncate font-medium"
                                        data-test="invitation-invalid-tenant"
                                    >
                                        {tenant.name}
                                    </span>
                                </div>
                            )}

                            {reason === 'wrong_email' && invitedEmail && (
                                <div className="mt-1 flex w-full flex-col gap-1 rounded-md border border-sky-200 bg-sky-50/60 px-3 py-2 text-left text-xs text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200">
                                    <span>
                                        Invitation sent to{' '}
                                        <span className="font-mono">
                                            {invitedEmail}
                                        </span>
                                    </span>
                                    {auth.user && (
                                        <span>
                                            You're signed in as{' '}
                                            <span className="font-mono">
                                                {auth.user.email}
                                            </span>
                                        </span>
                                    )}
                                </div>
                            )}

                            <div className="mt-3 flex w-full flex-col gap-2 sm:flex-row sm:justify-center">
                                {reason === 'wrong_email' ? (
                                    <Button asChild variant="outline" className="w-full sm:w-auto">
                                        <Link
                                            href={logout().url}
                                            method="post"
                                            as="button"
                                            data-test="invitation-invalid-logout"
                                        >
                                            <LogOut className="size-4" />
                                            Sign out &amp; switch account
                                        </Link>
                                    </Button>
                                ) : null}

                                <Button asChild className="w-full sm:w-auto" data-test="invitation-invalid-cta">
                                    <Link href={accountTenants().url}>
                                        <ArrowLeft className="size-4" />
                                        Go to my tenants
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <p className="mt-4 text-center text-xs text-muted-foreground">
                        Need help? Reply to the invitation email or contact
                        your workspace owner.
                    </p>
                </div>
            </div>
        </>
    );
}

