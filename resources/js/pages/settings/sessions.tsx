import { Form, Head } from '@inertiajs/react';
import { Globe, Laptop, Smartphone } from 'lucide-react';
import { useState } from 'react';
import SessionsController from '@/actions/App/Http/Controllers/Settings/SessionsController';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/utils';
import { index as sessionsIndex } from '@/routes/sessions';

type SessionRow = {
    id: string;
    ip_address: string | null;
    device: string;
    platform: string | null;
    browser: string | null;
    last_active: string;
    is_current: boolean;
};

type Props = {
    sessions: SessionRow[];
    driverIsDatabase: boolean;
};

function DeviceIcon({ platform }: { platform: string | null }) {
    if (
        platform &&
        ['iOS', 'Android'].includes(platform)
    ) {
        return <Smartphone className="size-5" />;
    }

    if (platform) {
return <Laptop className="size-5" />;
}

    return <Globe className="size-5" />;
}

export default function Sessions({ sessions, driverIsDatabase }: Props) {
    const [confirmAll, setConfirmAll] = useState(false);
    const [confirmRow, setConfirmRow] = useState<SessionRow | null>(null);

    return (
        <>
            <Head title="Active sessions" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Active sessions"
                    description="Devices and browsers where your account is currently signed in. Revoke any session you don't recognise."
                />

                {!driverIsDatabase && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        Set <code className="font-mono">SESSION_DRIVER=database</code> so
                        we can list active sessions for you.
                    </div>
                )}

                {sessions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No other active sessions.
                    </p>
                ) : (
                    <ul
                        className="divide-y divide-border rounded-md border"
                        data-test="sessions-list"
                    >
                        {sessions.map((row) => (
                            <li
                                key={row.id}
                                className="flex items-center gap-4 p-4"
                            >
                                <DeviceIcon platform={row.platform} />
                                <div className="flex-1 space-y-1">
                                    <div className="flex items-center gap-2 text-sm font-medium">
                                        {row.device}
                                        {row.browser && (
                                            <span className="text-muted-foreground">
                                                · {row.browser}
                                            </span>
                                        )}
                                        {row.is_current && (
                                            <Badge>This device</Badge>
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {row.ip_address ?? 'Unknown IP'} ·{' '}
                                        Last active {formatDateTime(row.last_active)}
                                    </div>
                                </div>
                                {!row.is_current && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setConfirmRow(row)}
                                        data-test={`session-revoke-${row.id}`}
                                    >
                                        Revoke
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <div>
                    <Button
                        variant="destructive"
                        type="button"
                        onClick={() => setConfirmAll(true)}
                        disabled={sessions.filter((s) => !s.is_current).length === 0}
                        data-test="sessions-revoke-all-button"
                    >
                        Revoke all other sessions
                    </Button>
                </div>
            </div>

            <AlertDialog
                open={confirmAll}
                onOpenChange={(open) => !open && setConfirmAll(false)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke all other sessions?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Anyone signed in on another device will be logged out
                            immediately. Your current session will stay active.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <Form
                        {...SessionsController.destroyAll.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setConfirmAll(false)}
                    >
                        {({ processing }) => (
                            <AlertDialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setConfirmAll(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="sessions-revoke-all-confirm"
                                >
                                    {processing && <Spinner />}
                                    Revoke all
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={confirmRow !== null}
                onOpenChange={(open) => !open && setConfirmRow(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Revoke this session?</AlertDialogTitle>
                        <AlertDialogDescription>
                            The device {confirmRow?.device}
                            {confirmRow?.browser
                                ? ` (${confirmRow.browser})`
                                : ''}{' '}
                            will be signed out immediately.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {confirmRow && (
                        <Form
                            {...SessionsController.destroy.form({
                                session: confirmRow.id,
                            })}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setConfirmRow(null)}
                        >
                            {({ processing }) => (
                                <AlertDialogFooter>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setConfirmRow(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Revoke session
                                    </Button>
                                </AlertDialogFooter>
                            )}
                        </Form>
                    )}
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

Sessions.layout = {
    breadcrumbs: [
        {
            title: 'Active sessions',
            href: sessionsIndex(),
        },
    ],
};
