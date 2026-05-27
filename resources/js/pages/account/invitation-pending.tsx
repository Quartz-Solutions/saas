import { Head, Link } from '@inertiajs/react';
import { Building2, Clock, Mail, UserPlus } from 'lucide-react';
import BrandMark from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Mono } from '@/components/admin/entity-detail/fact-card';

type TenantBrief = {
    id: number | null;
    slug: string | null;
    name: string | null;
    logo_path: string | null;
};

type Props = {
    tenant: TenantBrief;
    invitedEmail: string;
    role: string;
    expiresAt: string | null;
    hasAccount: boolean;
    loginUrl: string;
    registerUrl: string;
};

function formatExpiry(iso: string | null): string | null {
    if (!iso) return null;
    const d = new Date(iso);
    const diff = d.getTime() - Date.now();
    if (diff <= 0) return 'Expired';
    const days = Math.floor(diff / 86_400_000);
    if (days >= 1) return `Expires in ${days} day${days === 1 ? '' : 's'}`;
    const hours = Math.floor(diff / 3_600_000);
    if (hours >= 1) return `Expires in ${hours} hour${hours === 1 ? '' : 's'}`;
    return 'Expires soon';
}

export default function InvitationPending({
    tenant,
    invitedEmail,
    role,
    expiresAt,
    hasAccount,
    loginUrl,
    registerUrl,
}: Props) {
    const expiry = formatExpiry(expiresAt);

    return (
        <>
            <Head title={`Join ${tenant.name ?? 'a workspace'}`} />

            <div className="flex min-h-svh items-center justify-center bg-muted/30 p-6">
                <div className="w-full max-w-md">
                    <div className="mb-6 flex justify-center">
                        <BrandMark appOnly className="size-10" innerClassName="size-6" />
                    </div>

                    <Card className="border-border/60 shadow-sm">
                        <CardContent className="flex flex-col gap-5 px-6 py-8">
                            <div className="flex flex-col items-center gap-3 text-center">
                                <div className="flex size-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    <Mail className="size-7" strokeWidth={1.5} />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <h1
                                        className="text-xl font-semibold tracking-tight"
                                        data-test="invitation-pending-title"
                                    >
                                        You're invited
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        Sign in (or create an account) with{' '}
                                        <Mono>{invitedEmail}</Mono> to accept.
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-col gap-2 rounded-md border bg-muted/40 p-3">
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <Building2 className="size-4" />
                                        Workspace
                                    </span>
                                    <span
                                        className="truncate font-medium"
                                        data-test="invitation-pending-tenant"
                                    >
                                        {tenant.name ?? '—'}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3 text-sm">
                                    <span className="text-muted-foreground">Role</span>
                                    <span className="font-medium">{role}</span>
                                </div>
                                {expiry && (
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <span className="flex items-center gap-2 text-muted-foreground">
                                            <Clock className="size-4" />
                                            Validity
                                        </span>
                                        <span className="text-muted-foreground">
                                            {expiry}
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                {hasAccount ? (
                                    <>
                                        <Button asChild className="w-full" data-test="invitation-sign-in">
                                            <Link href={loginUrl}>
                                                Sign in to accept
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="w-full"
                                            data-test="invitation-create-account"
                                        >
                                            <Link href={registerUrl}>
                                                <UserPlus className="size-4" />
                                                Use a different account
                                            </Link>
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <Button
                                            asChild
                                            className="w-full"
                                            data-test="invitation-create-account"
                                        >
                                            <Link href={registerUrl}>
                                                <UserPlus className="size-4" />
                                                Create your account
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="w-full"
                                            data-test="invitation-sign-in"
                                        >
                                            <Link href={loginUrl}>
                                                I already have an account
                                            </Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <p className="mt-4 text-center text-xs text-muted-foreground">
                        Not you? Reply to the invitation email or contact the
                        workspace owner.
                    </p>
                </div>
            </div>
        </>
    );
}
