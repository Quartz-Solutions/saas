import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, RotateCw } from 'lucide-react';
import Heading from '@/components/heading';
import ActionsPanel from './_actions-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDateTime } from '@/lib/utils';
import { index as subsIndex } from '@/routes/admin/subscriptions';
import { show as tenantsShow } from '@/routes/admin/tenants';

type Subscription = {
    id: number;
    gateway: string;
    gateway_subscription_id: string | null;
    status: string;
    currency: string;
    unit_amount_cents: number;
    quantity: number;
    trial_starts_at: string | null;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
    canceled_at: string | null;
    cancellation_reason: string | null;
    created_at: string | null;
    tenant: {
        id: number;
        slug: string;
        name: string;
        owner: { id: number; name: string; email: string } | null;
    } | null;
    plan: {
        id: number;
        slug: string;
        name: string;
        billing_period: string;
        billing_interval: number;
    } | null;
};

type Invoice = {
    id: number;
    number: string | null;
    status: string;
    total_cents: number;
    amount_paid_cents: number;
    amount_due_cents: number;
    currency: string;
    issued_at: string | null;
    paid_at: string | null;
};

type Payment = {
    id: number;
    invoice_id: number | null;
    gateway: string;
    status: string;
    amount_cents: number;
    refunded_cents: number;
    currency: string;
    captured_at: string | null;
};

type WebhookEvent = {
    id: number;
    gateway: string;
    event_type: string;
    external_id: string | null;
    status: string;
    created_at: string | null;
};

type AuditEntry = {
    id: number;
    action: string;
    user_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string | null;
};

type Props = {
    subscription: Subscription;
    invoices: Invoice[];
    payments: Payment[];
    webhookEvents: WebhookEvent[];
    auditEntries: AuditEntry[];
    plans: Array<{
        id: number;
        slug: string;
        name: string;
        price_cents: number;
        currency: string;
        billing_period: string;
    }>;
    reasons: {
        credit: Record<string, string>;
        comp: Record<string, string>;
        refund: Record<string, string>;
        cancellation: Record<string, string>;
        manual_payment_method: Record<string, string>;
    };
};

const formatMoney = (cents: number, currency: string) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
};

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    active: 'default',
    trialing: 'secondary',
    past_due: 'destructive',
    canceled: 'outline',
    paid: 'default',
    open: 'secondary',
    uncollectible: 'destructive',
    succeeded: 'default',
    refunded: 'outline',
    partially_refunded: 'secondary',
    failed: 'destructive',
};

export default function AdminSubscriptionShow({
    subscription,
    invoices,
    payments,
    webhookEvents,
    auditEntries,
    plans,
    reasons,
}: Props) {

    return (
        <>
            <Head title={`Subscription #${subscription.id} — Admin`} />

            <div className="mb-6">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={subsIndex()}>
                        <ArrowLeft className="size-4" />
                        Back to subscriptions
                    </Link>
                </Button>
            </div>

            <Heading
                title={`${subscription.tenant?.name ?? 'Tenant'} — ${subscription.plan?.name ?? 'Plan'}`}
                description={`Subscription #${subscription.id} via ${subscription.gateway}`}
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center justify-between text-base">
                                Subscription state
                                <Badge variant={STATUS_VARIANT[subscription.status] ?? 'outline'}>
                                    {subscription.status}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 text-sm">
                            <Field label="Amount">
                                {subscription.unit_amount_cents === 0
                                    ? 'Free'
                                    : `${formatMoney(subscription.unit_amount_cents * subscription.quantity, subscription.currency)} / ${subscription.plan?.billing_period ?? 'month'}`}
                            </Field>
                            <Field label="Quantity">{subscription.quantity}</Field>
                            <Field label="Trial ends">
                                {subscription.trial_ends_at
                                    ? formatDateTime(subscription.trial_ends_at)
                                    : '—'}
                            </Field>
                            <Field label="Current period ends">
                                {subscription.current_period_end
                                    ? formatDateTime(subscription.current_period_end)
                                    : '—'}
                            </Field>
                            <Field label="Cancels at period end">
                                {subscription.cancel_at_period_end ? 'Yes' : 'No'}
                            </Field>
                            <Field label="Canceled at">
                                {subscription.canceled_at
                                    ? formatDateTime(subscription.canceled_at)
                                    : '—'}
                            </Field>
                            <Field label="Cancellation reason">
                                {subscription.cancellation_reason ?? '—'}
                            </Field>
                            <Field label="Gateway sub id">
                                <code className="text-xs">
                                    {subscription.gateway_subscription_id ?? '—'}
                                </code>
                            </Field>
                        </CardContent>
                    </Card>

                    <Tabs defaultValue="invoices">
                        <TabsList>
                            <TabsTrigger value="invoices">Invoices ({invoices.length})</TabsTrigger>
                            <TabsTrigger value="payments">Payments ({payments.length})</TabsTrigger>
                            <TabsTrigger value="webhooks">
                                Webhooks ({webhookEvents.length})
                            </TabsTrigger>
                            <TabsTrigger value="audit">Audit ({auditEntries.length})</TabsTrigger>
                        </TabsList>

                        <TabsContent value="invoices">
                            <Card>
                                <CardContent className="p-0">
                                    {invoices.length === 0 ? (
                                        <Empty label="No invoices yet" />
                                    ) : (
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/40 text-xs text-muted-foreground">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">#</th>
                                                    <th className="px-3 py-2 text-left">Status</th>
                                                    <th className="px-3 py-2 text-right">Total</th>
                                                    <th className="px-3 py-2 text-right">Paid</th>
                                                    <th className="px-3 py-2 text-right">Issued</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {invoices.map((i) => (
                                                    <tr key={i.id} className="border-t">
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {i.number ?? `#${i.id}`}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge
                                                                variant={
                                                                    STATUS_VARIANT[i.status] ??
                                                                    'outline'
                                                                }
                                                            >
                                                                {i.status}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                                            {formatMoney(i.total_cents, i.currency)}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                                            {formatMoney(
                                                                i.amount_paid_cents,
                                                                i.currency,
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-xs text-muted-foreground">
                                                            {i.issued_at
                                                                ? formatDateTime(i.issued_at)
                                                                : '—'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="payments">
                            <Card>
                                <CardContent className="p-0">
                                    {payments.length === 0 ? (
                                        <Empty label="No payments yet" />
                                    ) : (
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/40 text-xs text-muted-foreground">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">ID</th>
                                                    <th className="px-3 py-2 text-left">Status</th>
                                                    <th className="px-3 py-2 text-right">Amount</th>
                                                    <th className="px-3 py-2 text-right">
                                                        Refunded
                                                    </th>
                                                    <th className="px-3 py-2 text-right">
                                                        Captured
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {payments.map((p) => (
                                                    <tr key={p.id} className="border-t">
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            #{p.id}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge
                                                                variant={
                                                                    STATUS_VARIANT[p.status] ??
                                                                    'outline'
                                                                }
                                                            >
                                                                {p.status}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                                            {formatMoney(p.amount_cents, p.currency)}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                                            {p.refunded_cents > 0
                                                                ? formatMoney(
                                                                      p.refunded_cents,
                                                                      p.currency,
                                                                  )
                                                                : '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-xs text-muted-foreground">
                                                            {p.captured_at
                                                                ? formatDateTime(p.captured_at)
                                                                : '—'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="webhooks">
                            <Card>
                                <CardContent className="p-0">
                                    {webhookEvents.length === 0 ? (
                                        <Empty label="No webhook events" />
                                    ) : (
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/40 text-xs text-muted-foreground">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">Event</th>
                                                    <th className="px-3 py-2 text-left">Status</th>
                                                    <th className="px-3 py-2 text-left">External ID</th>
                                                    <th className="px-3 py-2 text-right">Received</th>
                                                    <th className="w-px px-3 py-2 text-right">
                                                        <span className="sr-only">Replay</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {webhookEvents.map((w) => (
                                                    <tr key={w.id} className="border-t">
                                                        <td className="px-3 py-2 text-xs">
                                                            {w.event_type}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge variant="outline">{w.status}</Badge>
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                                            {w.external_id ?? '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-xs text-muted-foreground">
                                                            {w.created_at
                                                                ? formatDateTime(w.created_at)
                                                                : '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                onClick={() =>
                                                                    router.post(
                                                                        `/admin/webhooks/${w.id}/replay`,
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                                title="Replay event"
                                                            >
                                                                <RotateCw className="size-3.5" />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="audit">
                            <Card>
                                <CardContent className="p-0">
                                    {auditEntries.length === 0 ? (
                                        <Empty label="No audit entries" />
                                    ) : (
                                        <ul className="divide-y">
                                            {auditEntries.map((a) => (
                                                <li key={a.id} className="px-3 py-2 text-sm">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium">
                                                            {a.action}
                                                        </span>
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            {a.created_at
                                                                ? formatDateTime(a.created_at)
                                                                : '—'}
                                                        </span>
                                                    </div>
                                                    {a.new_values ? (
                                                        <pre className="mt-1 max-h-24 overflow-auto rounded bg-muted/40 p-2 text-xs">
                                                            {JSON.stringify(a.new_values, null, 2)}
                                                        </pre>
                                                    ) : null}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Tenant</CardTitle>
                            <CardDescription>
                                <Link
                                    href={tenantsShow({ tenant: subscription.tenant?.id ?? 0 })}
                                    className="inline-flex items-center gap-1 hover:underline"
                                >
                                    {subscription.tenant?.name ?? '—'}
                                    <ExternalLink className="size-3" />
                                </Link>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Field label="Slug">
                                <code className="text-xs">{subscription.tenant?.slug ?? '—'}</code>
                            </Field>
                            <Field label="Owner">
                                {subscription.tenant?.owner?.name ?? '—'}
                            </Field>
                            <Field label="Owner email">
                                {subscription.tenant?.owner?.email ?? '—'}
                            </Field>
                        </CardContent>
                    </Card>

                    <ActionsPanel
                        subscriptionId={subscription.id}
                        cancelAtPeriodEnd={subscription.cancel_at_period_end}
                        plans={plans}
                        reasons={reasons}
                    />
                </div>
            </div>
        </>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span>{children}</span>
        </div>
    );
}

function Empty({ label }: { label: string }) {
    return <div className="p-6 text-center text-sm text-muted-foreground">{label}</div>;
}
