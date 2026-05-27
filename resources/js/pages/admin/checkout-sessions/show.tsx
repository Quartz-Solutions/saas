import { Form, Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/utils';
import {
    forceCancel as sessionsForceCancel,
    index as sessionsIndex,
} from '@/routes/admin/checkout-sessions';

type Session = {
    id: number;
    public_id: string;
    intent: string;
    status: string;
    gateway: string | null;
    gateway_session_id: string | null;
    currency: string;
    amount_cents: number;
    result_kind: string | null;
    result_payload: Record<string, unknown> | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
    completed_at: string | null;
    canceled_at: string | null;
    cancel_reason: string | null;
    expires_at: string | null;
    tenant: {
        id: number;
        slug: string;
        name: string;
        owner: { id: number; name: string; email: string } | null;
    } | null;
    plan: {
        slug: string;
        name: string;
        price_cents: number;
        currency: string;
        billing_period: string;
    } | null;
    user: { id: number; name: string; email: string } | null;
    subscription: {
        id: number;
        status: string;
        unit_amount_cents: number;
        currency: string;
    } | null;
    invoice: {
        id: number;
        number: string;
        status: string;
        total_cents: number;
        currency: string;
    } | null;
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    awaiting_payment: 'secondary',
    completed: 'default',
    failed: 'destructive',
    canceled: 'outline',
    expired: 'outline',
};

const formatMoney = (cents: number, currency: string) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
};

const TERMINAL = ['completed', 'failed', 'canceled', 'expired'];

export default function AdminCheckoutSessionShow({ session }: { session: Session }) {
    const isTerminal = TERMINAL.includes(session.status);

    return (
        <>
            <Head title={`Checkout ${session.public_id} — Admin`} />

            <div className="flex h-full flex-1 flex-col gap-6">
                <Button variant="ghost" size="sm" asChild className="w-fit">
                    <Link href={sessionsIndex()}>
                        <ArrowLeft className="size-4" />
                        All sessions
                    </Link>
                </Button>

                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={`Session ${session.public_id}`}
                        description={`${session.intent} · ${session.gateway ?? 'no gateway'}`}
                    />
                    <Badge variant={STATUS_VARIANT[session.status] ?? 'outline'} className="text-sm">
                        {session.status}
                    </Badge>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Identity</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="public_id" value={<code className="font-mono text-xs">{session.public_id}</code>} />
                            <Row label="gateway_session_id" value={
                                session.gateway_session_id
                                    ? <code className="font-mono text-xs break-all">{session.gateway_session_id}</code>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="result_kind" value={session.result_kind ?? <span className="text-muted-foreground">—</span>} />
                            <Row label="amount" value={
                                session.amount_cents === 0
                                    ? <span className="text-muted-foreground">Free</span>
                                    : <span className="font-mono">{formatMoney(session.amount_cents, session.currency)}</span>
                            } />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Tenant + plan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="tenant" value={
                                session.tenant
                                    ? <span>{session.tenant.name} <code className="font-mono text-xs text-muted-foreground">{session.tenant.slug}</code></span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="owner" value={
                                session.tenant?.owner
                                    ? <span>{session.tenant.owner.email}</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="plan" value={
                                session.plan
                                    ? <span>{session.plan.name}</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="initiator" value={
                                session.user
                                    ? <span>{session.user.email}</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Timeline</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="created" value={
                                session.created_at
                                    ? <span className="font-mono text-xs">{formatDateTime(session.created_at)}</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="expires" value={
                                session.expires_at
                                    ? <span className="font-mono text-xs">{formatDateTime(session.expires_at)}</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="completed" value={
                                session.completed_at
                                    ? <span className="font-mono text-xs">{formatDateTime(session.completed_at)}</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="canceled" value={
                                session.canceled_at
                                    ? <>
                                        <span className="font-mono text-xs">{formatDateTime(session.canceled_at)}</span>
                                        {session.cancel_reason ? <span className="ml-2 text-xs text-muted-foreground">{session.cancel_reason}</span> : null}
                                      </>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Linked records</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="subscription" value={
                                session.subscription
                                    ? <span>#{session.subscription.id} ({session.subscription.status})</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                            <Row label="invoice" value={
                                session.invoice
                                    ? <span>{session.invoice.number} ({session.invoice.status})</span>
                                    : <span className="text-muted-foreground">—</span>
                            } />
                        </CardContent>
                    </Card>
                </div>

                {session.result_payload && Object.keys(session.result_payload).length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Result payload</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre className="overflow-x-auto rounded-md bg-muted p-3 text-xs">
                                {JSON.stringify(session.result_payload, null, 2)}
                            </pre>
                        </CardContent>
                    </Card>
                ) : null}

                {!isTerminal ? (
                    <Card className="border-amber-200 bg-amber-50 dark:bg-amber-950/30">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <AlertTriangle className="size-4 text-amber-600" />
                                Force cancel
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-3 text-sm text-muted-foreground">
                                Marks this session canceled with reason <code className="font-mono text-xs">admin_force_cancel</code>.
                                Does not call the gateway — use only for stuck sessions where the gateway never returned.
                            </p>
                            <Form
                                action={sessionsForceCancel({ checkoutSession: session.public_id })}
                                method="post"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button type="submit" variant="destructive" disabled={processing}>
                                        Cancel this session
                                    </Button>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid grid-cols-[120px_1fr] items-baseline gap-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}
