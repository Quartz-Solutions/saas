import { Form, Head, Link, router } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    ArrowRight,
    Download,
    ExternalLink,
    LogIn,
    Pause,
    Play,
    RotateCcw,
    Trash2,
    UserMinus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import TenantsAdminController from '@/actions/App/Http/Controllers/Admin/TenantsAdminController';
import { ActionsMenu  } from '@/components/admin/entity-detail/actions-menu';
import type {ActionItem} from '@/components/admin/entity-detail/actions-menu';
import { ActivityPanel } from '@/components/admin/entity-detail/activity-panel';
import { EntityHeader } from '@/components/admin/entity-detail/entity-header';
import { EntityHeroBanner } from '@/components/admin/entity-detail/entity-hero-banner';
import { FactCard, FactGrid, Mono } from '@/components/admin/entity-detail/fact-card';
import { TabBar  } from '@/components/admin/entity-detail/tab-bar';
import type {TabSpec} from '@/components/admin/entity-detail/tab-bar';
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
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn, formatDateTime } from '@/lib/utils';
import { index as adminAuditIndex } from '@/routes/admin/audit';
import { show as adminSubscriptionsShow } from '@/routes/admin/subscriptions';
import { index as adminTenantsIndex } from '@/routes/admin/tenants';
import { show as adminUsersShow } from '@/routes/admin/users';
import { index as adminWebhooksIndex } from '@/routes/admin/webhooks';

type TenantProp = {
    id: number;
    slug: string;
    name: string;
    status: string;
    currency: string;
    timezone: string;
    locale: string;
    logo_path: string | null;
    settings: Record<string, unknown> | null;
    trial_ends_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    members_count: number;
    owner: {
        id: number;
        name: string;
        email: string;
        last_login_at: string | null;
    } | null;
};

type SubscriptionProp = {
    id: number;
    status: string;
    gateway: string | null;
    gateway_subscription_id: string | null;
    unit_amount_cents: number;
    currency: string;
    quantity: number;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
    canceled_at: string | null;
    ends_at: string | null;
    plan: {
        id: number;
        slug: string;
        name: string;
        price_cents: number;
        currency: string;
        billing_period: string;
    } | null;
} | null;

type InvoiceRow = {
    id: number;
    number: string | null;
    status: string;
    currency: string;
    total_cents: number;
    amount_paid_cents: number;
    amount_due_cents: number;
    issued_at: string | null;
    paid_at: string | null;
};

type PaymentRow = {
    id: number;
    gateway: string | null;
    status: string;
    amount_cents: number;
    refunded_cents: number;
    currency: string;
    captured_at: string | null;
    created_at: string | null;
    failed_at: string | null;
    refunded_at: string | null;
};

type WebhookRow = {
    id: number;
    gateway: string | null;
    event_type: string;
    gateway_event_id: string | null;
    status: string;
    created_at: string | null;
};

type AuditRow = {
    id: number;
    action: string;
    user: { id: number; name: string; email: string } | null;
    auditable_type: string | null;
    auditable_id: number | null;
    new_values: Record<string, unknown> | null;
    created_at: string | null;
};

type OutboundDeliveryRow = {
    id: number;
    webhook_url: string | null;
    event_type: string;
    status: string;
    attempt: number;
    response_code: number | null;
    duration_ms: number | null;
    created_at: string | null;
    failed_at: string | null;
    retryable: boolean;
};

type LoginRow = {
    id: number;
    user: { id: number; name: string; email: string } | null;
    outcome: string;
    method: string | null;
    ip: string | null;
    created_at: string | null;
};

type MemberRow = {
    membership_id: number;
    id: number | null;
    name: string | null;
    email: string | null;
    avatar_path: string | null;
    last_login_at: string | null;
    suspended_at: string | null;
    is_owner: boolean;
    joined_at: string | null;
};

type Props = {
    tenant: TenantProp;
    subscription: SubscriptionProp;
    invoices: InvoiceRow[];
    payments: PaymentRow[];
    webhookEvents: WebhookRow[];
    auditLog: AuditRow[];
    loginHistory: LoginRow[];
    outboundWebhooks: {
        count: number;
        active: number;
        deliveries_total: number;
        deliveries_failed: number;
    };
    outboundDeliveries: OutboundDeliveryRow[];
    members: {
        data: MemberRow[];
        meta: { current_page: number; last_page: number; per_page: number; total: number };
    };
};

function formatMoney(cents: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: (currency || 'USD').toUpperCase(),
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
}

function tenantStatusVariant(status: string): {
    pill: 'default' | 'secondary' | 'destructive' | 'outline';
    label: string;
} {
    switch (status) {
        case 'active':
            return { pill: 'default', label: 'Active' };
        case 'suspended':
            return { pill: 'destructive', label: 'Suspended' };
        case 'pending_deletion':
            return { pill: 'secondary', label: 'Pending deletion' };
        default:
            return { pill: 'outline', label: status };
    }
}

function subscriptionStatusVariant(status: string | null | undefined): {
    pill: 'default' | 'secondary' | 'destructive' | 'outline';
    label: string;
} {
    switch (status) {
        case 'active':
            return { pill: 'default', label: 'Active' };
        case 'trialing':
            return { pill: 'secondary', label: 'Trialing' };
        case 'past_due':
            return { pill: 'destructive', label: 'Past due' };
        case 'canceled':
        case 'cancelled':
            return { pill: 'outline', label: 'Canceled' };
        default:
            return { pill: 'outline', label: status ?? 'No subscription' };
    }
}

function getTab(): string {
    if (typeof window === 'undefined') {
return 'overview';
}

    return new URL(window.location.href).searchParams.get('tab') || 'overview';
}

export default function AdminTenantsShow({
    tenant,
    subscription,
    invoices,
    payments,
    webhookEvents,
    auditLog,
    loginHistory,
    outboundWebhooks,
    outboundDeliveries,
    members,
}: Props) {
    const [tab, setTab] = useState<string>(getTab());
    const [actionDialog, setActionDialog] = useState<
        | null
        | 'impersonate'
        | 'suspend'
        | 'restore'
        | 'delete'
        | 'force-delete'
    >(null);

    const statusInfo = tenantStatusVariant(tenant.status);
    const subStatus = subscriptionStatusVariant(subscription?.status);

    const heroValue = subscription?.plan
        ? `${formatMoney(subscription.unit_amount_cents || subscription.plan.price_cents, subscription.currency || subscription.plan.currency)} / ${subscription.plan.billing_period}`
        : 'No subscription';

    const heroHelper = useMemo(() => {
        if (!subscription) {
return tenant.trial_ends_at ? `Trial ends ${formatDateTime(tenant.trial_ends_at)}` : 'Not subscribed yet.';
}

        if (subscription.status === 'trialing' && subscription.trial_ends_at) {
            return `Trial ends ${formatDateTime(subscription.trial_ends_at)}`;
        }

        if (subscription.cancel_at_period_end && subscription.current_period_end) {
            return (
                <span className="flex items-center gap-1 text-destructive">
                    <AlertTriangle className="size-3.5" />
                    Cancels {formatDateTime(subscription.current_period_end)}
                </span>
            );
        }

        if (subscription.current_period_end) {
            return `Renews ${formatDateTime(subscription.current_period_end)}`;
        }

        return null;
    }, [subscription, tenant.trial_ends_at]);

    const isArchived = tenant.deleted_at !== null;

    const actions: ActionItem[] = [
        {
            label: 'Impersonate owner',
            icon: <LogIn className="size-4" />,
            disabled: tenant.owner === null || isArchived,
            onSelect: () => setActionDialog('impersonate'),
            'data-test': 'action-impersonate',
        },
        {
            label: 'GDPR data export',
            icon: <Download className="size-4" />,
            onSelect: () => {
                window.location.href = TenantsAdminController.gdprExport.url({ tenant: tenant.id });
            },
            'data-test': 'action-gdpr-export',
        },
        {
            label: tenant.status === 'suspended' ? 'Reactivate tenant' : 'Suspend tenant',
            icon:
                tenant.status === 'suspended' ? (
                    <Play className="size-4" />
                ) : (
                    <Pause className="size-4" />
                ),
            destructive: tenant.status !== 'suspended',
            hidden: isArchived,
            onSelect: () =>
                setActionDialog(tenant.status === 'suspended' ? 'restore' : 'suspend'),
            'data-test':
                tenant.status === 'suspended' ? 'action-reactivate' : 'action-suspend',
        },
        {
            label: 'Soft-delete tenant',
            icon: <Trash2 className="size-4" />,
            destructive: true,
            hidden: isArchived,
            onSelect: () => setActionDialog('delete'),
            'data-test': 'action-delete',
        },
        {
            label: 'Restore tenant',
            icon: <RotateCcw className="size-4" />,
            destructive: false,
            hidden: !isArchived,
            onSelect: () => setActionDialog('restore'),
            'data-test': 'action-restore',
        },
        {
            label: 'Force-delete (GDPR)',
            icon: <AlertOctagon className="size-4" />,
            destructive: true,
            hidden: !isArchived,
            onSelect: () => setActionDialog('force-delete'),
            'data-test': 'action-force-delete',
        },
    ];

    const tabs: TabSpec[] = [
        { value: 'overview', label: 'Overview' },
        { value: 'billing', label: 'Billing', badge: invoices.length || null },
        { value: 'members', label: 'Members', badge: tenant.members_count },
        { value: 'activity', label: 'Activity' },
        { value: 'danger', label: 'Danger zone', danger: true },
    ];

    return (
        <>
            <Head title={`${tenant.name} — Tenant`} />

            <div className="flex flex-col gap-6">
                <EntityHeader
                    backHref={adminTenantsIndex().url}
                    backLabel="Tenants"
                    breadcrumb={[{ label: 'Tenants', href: adminTenantsIndex().url }, { label: tenant.name }]}
                    avatarUrl={tenant.logo_path}
                    avatarFallback={tenant.name.slice(0, 2).toUpperCase()}
                    name={tenant.name}
                    subtitle={
                        <span className="flex flex-wrap items-center gap-2">
                            <Mono>/t/{tenant.slug}</Mono>
                            <Badge variant={statusInfo.pill} data-test="tenant-status-badge">
                                {statusInfo.label}
                            </Badge>
                            {isArchived && (
                                <Badge variant="outline" className="border-rose-500/40 text-rose-600">
                                    Archived
                                </Badge>
                            )}
                        </span>
                    }
                    statusDot={tenant.status}
                    actions={
                        <ActionsMenu
                            items={actions}
                            leading={
                                tenant.owner ? (
                                    <Button
                                        size="sm"
                                        onClick={() => setActionDialog('impersonate')}
                                        disabled={tenant.owner === null || isArchived}
                                        data-test="impersonate-quick"
                                    >
                                        <LogIn className="size-4" />
                                        Impersonate
                                    </Button>
                                ) : null
                            }
                        />
                    }
                />

                <EntityHeroBanner
                    label="Subscription"
                    value={heroValue}
                    pill={{ label: subStatus.label, variant: subStatus.pill }}
                    helper={heroHelper}
                    actions={
                        subscription && (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                data-test="open-subscription"
                            >
                                <Link href={adminSubscriptionsShow({ subscription: subscription.id })}>
                                    Open subscription
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )
                    }
                />

                <TabBar tabs={tabs} value={tab} onChange={setTab} />

                {tab === 'overview' && (
                    <OverviewLayout
                        tenant={tenant}
                        subscription={subscription}
                        invoices={invoices}
                        payments={payments}
                        webhookEvents={webhookEvents}
                        auditLog={auditLog}
                        loginHistory={loginHistory}
                        outboundWebhooks={outboundWebhooks}
                    />
                )}

                {tab === 'billing' && (
                    <BillingTab
                        subscription={subscription}
                        invoices={invoices}
                        payments={payments}
                    />
                )}

                {tab === 'members' && (
                    <MembersTab tenant={tenant} members={members} />
                )}

                {tab === 'activity' && (
                    <ActivityTab
                        tenant={tenant}
                        auditLog={auditLog}
                        webhookEvents={webhookEvents}
                        loginHistory={loginHistory}
                        outboundDeliveries={outboundDeliveries}
                    />
                )}

                {tab === 'danger' && (
                    <DangerTab
                        tenant={tenant}
                        onSuspend={() => setActionDialog('suspend')}
                        onRestore={() => setActionDialog('restore')}
                        onDelete={() => setActionDialog('delete')}
                        onForceDelete={() => setActionDialog('force-delete')}
                    />
                )}
            </div>

            <ImpersonateDialog
                open={actionDialog === 'impersonate'}
                onClose={() => setActionDialog(null)}
                tenant={tenant}
            />
            <SuspendDialog
                open={actionDialog === 'suspend'}
                onClose={() => setActionDialog(null)}
                tenant={tenant}
            />
            <RestoreDialog
                open={actionDialog === 'restore'}
                onClose={() => setActionDialog(null)}
                tenant={tenant}
            />
            <SoftDeleteDialog
                open={actionDialog === 'delete'}
                onClose={() => setActionDialog(null)}
                tenant={tenant}
            />
            <ForceDeleteDialog
                open={actionDialog === 'force-delete'}
                onClose={() => setActionDialog(null)}
                tenant={tenant}
            />
        </>
    );
}

function OverviewLayout({
    tenant,
    subscription,
    invoices,
    payments,
    webhookEvents,
    auditLog,
    loginHistory,
    outboundWebhooks,
}: {
    tenant: TenantProp;
    subscription: SubscriptionProp;
    invoices: InvoiceRow[];
    payments: PaymentRow[];
    webhookEvents: WebhookRow[];
    auditLog: AuditRow[];
    loginHistory: LoginRow[];
    outboundWebhooks: Props['outboundWebhooks'];
}) {
    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
            {/* Left column: facts (2 cols) */}
            <div className="flex flex-col gap-4 lg:col-span-2">
                <FactCard title="Personal details">
                    <FactGrid
                        rows={[
                            ['Name', tenant.name],
                            ['Slug', <Mono key="s">/t/{tenant.slug}</Mono>],
                            ['Status', tenant.status],
                            ['Currency', tenant.currency],
                            ['Locale', tenant.locale],
                            ['Timezone', tenant.timezone],
                            ['Members', tenant.members_count],
                            [
                                'Created',
                                tenant.created_at ? formatDateTime(tenant.created_at) : '—',
                            ],
                            tenant.deleted_at && [
                                'Deleted',
                                <span key="d" className="text-destructive">
                                    {formatDateTime(tenant.deleted_at)}
                                </span>,
                            ],
                        ]}
                    />
                </FactCard>

                <FactCard title="Owner & contact">
                    {tenant.owner ? (
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-col gap-0.5">
                                <Link
                                    href={adminUsersShow({ user: tenant.owner.id })}
                                    className="flex items-center gap-1 text-sm font-medium hover:underline"
                                >
                                    {tenant.owner.name}
                                    <ExternalLink className="size-3" />
                                </Link>
                                <span className="text-xs text-muted-foreground">
                                    {tenant.owner.email}
                                </span>
                                {tenant.owner.last_login_at && (
                                    <span className="text-xs text-muted-foreground">
                                        Last login {formatDateTime(tenant.owner.last_login_at)}
                                    </span>
                                )}
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">No owner assigned.</p>
                    )}
                </FactCard>

                {subscription && (
                    <FactCard
                        title="Subscription"
                        headerExtra={
                            <Link
                                href={adminSubscriptionsShow({ subscription: subscription.id })}
                                className="flex items-center gap-0.5 text-xs text-muted-foreground hover:text-foreground"
                            >
                                Open <ChevronRightInline />
                            </Link>
                        }
                    >
                        <FactGrid
                            rows={[
                                ['Plan', subscription.plan?.name ?? '—'],
                                [
                                    'Amount',
                                    formatMoney(
                                        subscription.unit_amount_cents,
                                        subscription.currency,
                                    ),
                                ],
                                ['Gateway', subscription.gateway ?? '—'],
                                [
                                    'Gateway sub id',
                                    subscription.gateway_subscription_id ? (
                                        <Mono>{subscription.gateway_subscription_id}</Mono>
                                    ) : (
                                        '—'
                                    ),
                                ],
                                [
                                    'Period',
                                    subscription.current_period_start
                                        ? `${formatDateTime(subscription.current_period_start)} → ${formatDateTime(subscription.current_period_end ?? '')}`
                                        : '—',
                                ],
                                subscription.trial_ends_at && [
                                    'Trial ends',
                                    formatDateTime(subscription.trial_ends_at),
                                ],
                                subscription.cancel_at_period_end && [
                                    'Cancel scheduled',
                                    <span key="c" className="text-destructive">
                                        Yes
                                    </span>,
                                ],
                            ]}
                        />
                    </FactCard>
                )}

                <FactCard title="Outbound webhooks">
                    <FactGrid
                        rows={[
                            ['Endpoints', outboundWebhooks.count],
                            ['Active', outboundWebhooks.active],
                            [
                                'Deliveries',
                                <span key="d">
                                    {outboundWebhooks.deliveries_total}{' '}
                                    {outboundWebhooks.deliveries_failed > 0 && (
                                        <span className="text-destructive">
                                            ({outboundWebhooks.deliveries_failed} failed)
                                        </span>
                                    )}
                                </span>,
                            ],
                        ]}
                    />
                </FactCard>

                <FactCard title="Tenant settings">
                    {tenant.settings && Object.keys(tenant.settings).length > 0 ? (
                        <pre className="max-h-64 overflow-auto rounded-md bg-muted p-3 text-xs">
                            {JSON.stringify(tenant.settings, null, 2)}
                        </pre>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No custom settings.
                        </p>
                    )}
                </FactCard>
            </div>

            {/* Right column: activity (3 cols) */}
            <div className="flex flex-col gap-4 lg:col-span-3">
                <ActivityPanel
                    title="Recent invoices"
                    viewAllHref={
                        subscription
                            ? adminSubscriptionsShow({ subscription: subscription.id }).url
                            : undefined
                    }
                    rows={invoices}
                    rowKey={(r) => r.id}
                    columns={[
                        {
                            key: 'issued',
                            header: 'Issued',
                            render: (r) => (
                                <span className="font-mono text-xs">
                                    {r.issued_at ? formatDateTime(r.issued_at) : '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'number',
                            header: 'Number',
                            render: (r) => (
                                <Mono>{r.number ?? `#${r.id}`}</Mono>
                            ),
                        },
                        {
                            key: 'amount',
                            header: 'Amount',
                            render: (r) => formatMoney(r.total_cents, r.currency),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (r) => <Badge variant="outline">{r.status}</Badge>,
                        },
                    ]}
                    emptyMessage="No invoices yet."
                />

                <ActivityPanel
                    title="Recent payments"
                    rows={payments}
                    rowKey={(r) => r.id}
                    columns={[
                        {
                            key: 'date',
                            header: 'Date',
                            render: (r) => (
                                <span className="font-mono text-xs">
                                    {formatDateTime(
                                        r.captured_at ?? r.failed_at ?? r.created_at ?? '',
                                    )}
                                </span>
                            ),
                        },
                        {
                            key: 'amount',
                            header: 'Amount',
                            render: (r) => (
                                <span>
                                    {formatMoney(r.amount_cents, r.currency)}
                                    {r.refunded_cents > 0 && (
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            (−{formatMoney(r.refunded_cents, r.currency)})
                                        </span>
                                    )}
                                </span>
                            ),
                        },
                        {
                            key: 'gateway',
                            header: 'Gateway',
                            render: (r) => (
                                <Mono>{r.gateway ?? 'manual'}</Mono>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (r) => (
                                <Badge
                                    variant={
                                        r.status === 'succeeded'
                                            ? 'default'
                                            : r.status === 'failed'
                                              ? 'destructive'
                                              : 'outline'
                                    }
                                >
                                    {r.status}
                                </Badge>
                            ),
                        },
                    ]}
                    emptyMessage="No payments yet."
                />

                <ActivityPanel
                    title="Webhook events"
                    viewAllHref={adminWebhooksIndex({ query: { tenant_id: tenant.id } }).url}
                    rows={webhookEvents}
                    rowKey={(r) => r.id}
                    columns={[
                        {
                            key: 'date',
                            header: 'Date',
                            render: (r) => (
                                <span className="font-mono text-xs">
                                    {r.created_at ? formatDateTime(r.created_at) : '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'gateway',
                            header: 'Gateway',
                            render: (r) => <Mono>{r.gateway ?? '—'}</Mono>,
                        },
                        {
                            key: 'event',
                            header: 'Event',
                            render: (r) => (
                                <span className="font-mono text-xs">{r.event_type}</span>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (r) => (
                                <Badge
                                    variant={
                                        r.status === 'processed'
                                            ? 'default'
                                            : r.status === 'failed'
                                              ? 'destructive'
                                              : 'outline'
                                    }
                                >
                                    {r.status}
                                </Badge>
                            ),
                        },
                    ]}
                    emptyMessage="No webhook events."
                />

                <ActivityPanel
                    title="Audit log"
                    description="Last 10 changes to this tenant."
                    viewAllHref={adminAuditIndex({ query: { tenant_id: tenant.id } }).url}
                    rows={auditLog}
                    rowKey={(r) => r.id}
                    columns={[
                        {
                            key: 'date',
                            header: 'Date',
                            render: (r) => (
                                <span className="font-mono text-xs">
                                    {r.created_at ? formatDateTime(r.created_at) : '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'user',
                            header: 'Actor',
                            render: (r) =>
                                r.user ? (
                                    <Link
                                        href={adminUsersShow({ user: r.user.id })}
                                        className="hover:underline"
                                    >
                                        {r.user.email}
                                    </Link>
                                ) : (
                                    <span className="text-muted-foreground">system</span>
                                ),
                        },
                        {
                            key: 'action',
                            header: 'Action',
                            render: (r) => (
                                <span className="font-mono text-xs">{r.action}</span>
                            ),
                        },
                    ]}
                    emptyMessage="No audit entries."
                />

                <ActivityPanel
                    title="Login history (members)"
                    rows={loginHistory}
                    rowKey={(r) => r.id}
                    columns={[
                        {
                            key: 'date',
                            header: 'Date',
                            render: (r) => (
                                <span className="font-mono text-xs">
                                    {r.created_at ? formatDateTime(r.created_at) : '—'}
                                </span>
                            ),
                        },
                        {
                            key: 'user',
                            header: 'User',
                            render: (r) =>
                                r.user ? (
                                    <Link
                                        href={adminUsersShow({ user: r.user.id })}
                                        className="hover:underline"
                                    >
                                        {r.user.email}
                                    </Link>
                                ) : (
                                    '—'
                                ),
                        },
                        {
                            key: 'ip',
                            header: 'IP',
                            render: (r) => (
                                <Mono>{r.ip ?? '—'}</Mono>
                            ),
                        },
                        {
                            key: 'outcome',
                            header: 'Outcome',
                            render: (r) => (
                                <Badge
                                    variant={
                                        r.outcome === 'succeeded' ? 'default' : 'destructive'
                                    }
                                >
                                    {r.outcome}
                                </Badge>
                            ),
                        },
                    ]}
                    emptyMessage="No login activity."
                />
            </div>
        </div>
    );
}

function BillingTab({
    subscription,
    invoices,
    payments,
}: {
    subscription: SubscriptionProp;
    invoices: InvoiceRow[];
    payments: PaymentRow[];
}) {
    if (!subscription) {
        return (
            <div className="rounded-md border bg-muted/30 p-6 text-sm text-muted-foreground">
                This tenant has no subscription on file.
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <FactCard title="Subscription details">
                <FactGrid
                    rows={[
                        ['Plan', subscription.plan?.name ?? '—'],
                        ['Status', subscription.status],
                        ['Gateway', subscription.gateway ?? '—'],
                        ['Gateway sub id', subscription.gateway_subscription_id ?? '—'],
                        [
                            'Amount',
                            formatMoney(subscription.unit_amount_cents, subscription.currency),
                        ],
                        ['Quantity', subscription.quantity],
                        [
                            'Current period',
                            subscription.current_period_start
                                ? `${formatDateTime(subscription.current_period_start)} → ${formatDateTime(subscription.current_period_end ?? '')}`
                                : '—',
                        ],
                        subscription.trial_ends_at && [
                            'Trial ends',
                            formatDateTime(subscription.trial_ends_at),
                        ],
                        ['Cancel scheduled', subscription.cancel_at_period_end ? 'Yes' : 'No'],
                        subscription.canceled_at && [
                            'Canceled at',
                            formatDateTime(subscription.canceled_at),
                        ],
                    ]}
                />
            </FactCard>

            <ActivityPanel
                title="All recent invoices"
                rows={invoices}
                rowKey={(r) => r.id}
                columns={[
                    {
                        key: 'date',
                        header: 'Issued',
                        render: (r) => (
                            <span className="font-mono text-xs">
                                {r.issued_at ? formatDateTime(r.issued_at) : '—'}
                            </span>
                        ),
                    },
                    {
                        key: 'number',
                        header: 'Number',
                        render: (r) => <Mono>{r.number ?? `#${r.id}`}</Mono>,
                    },
                    { key: 'total', header: 'Total', render: (r) => formatMoney(r.total_cents, r.currency) },
                    { key: 'paid', header: 'Paid', render: (r) => formatMoney(r.amount_paid_cents, r.currency) },
                    { key: 'due', header: 'Due', render: (r) => formatMoney(r.amount_due_cents, r.currency) },
                    { key: 'status', header: 'Status', render: (r) => <Badge variant="outline">{r.status}</Badge> },
                ]}
                emptyMessage="No invoices."
            />

            <ActivityPanel
                title="All recent payments"
                rows={payments}
                rowKey={(r) => r.id}
                columns={[
                    {
                        key: 'date',
                        header: 'Date',
                        render: (r) => (
                            <span className="font-mono text-xs">
                                {formatDateTime(
                                    r.captured_at ?? r.failed_at ?? r.created_at ?? '',
                                )}
                            </span>
                        ),
                    },
                    {
                        key: 'amount',
                        header: 'Amount',
                        render: (r) => formatMoney(r.amount_cents, r.currency),
                    },
                    {
                        key: 'refunded',
                        header: 'Refunded',
                        render: (r) =>
                            r.refunded_cents > 0
                                ? formatMoney(r.refunded_cents, r.currency)
                                : '—',
                    },
                    {
                        key: 'gateway',
                        header: 'Gateway',
                        render: (r) => <Mono>{r.gateway ?? 'manual'}</Mono>,
                    },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (r) => (
                            <Badge
                                variant={
                                    r.status === 'succeeded'
                                        ? 'default'
                                        : r.status === 'failed'
                                          ? 'destructive'
                                          : 'outline'
                                }
                            >
                                {r.status}
                            </Badge>
                        ),
                    },
                ]}
                emptyMessage="No payments."
            />
        </div>
    );
}

function MembersTab({ tenant, members }: { tenant: TenantProp; members: Props['members'] }) {
    const [removing, setRemoving] = useState<MemberRow | null>(null);
    const [impersonating, setImpersonating] = useState<MemberRow | null>(null);

    return (
        <div className="flex flex-col gap-4">
            <ActivityPanel
                title={`Members (${members.meta.total})`}
                rows={members.data}
                rowKey={(r) => r.membership_id}
                columns={[
                    {
                        key: 'user',
                        header: 'User',
                        render: (r) => (
                            <div className="flex items-center gap-2">
                                <Link
                                    href={r.id ? adminUsersShow({ user: r.id }) : '#'}
                                    className="flex flex-col hover:underline"
                                >
                                    <span className="text-sm font-medium">{r.name ?? '—'}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {r.email ?? ''}
                                    </span>
                                </Link>
                                {r.is_owner && (
                                    <Badge variant="secondary" className="text-[10px]">
                                        Owner
                                    </Badge>
                                )}
                                {r.suspended_at && (
                                    <Badge variant="destructive" className="text-[10px]">
                                        Suspended
                                    </Badge>
                                )}
                            </div>
                        ),
                    },
                    {
                        key: 'joined',
                        header: 'Joined',
                        render: (r) => (
                            <span className="font-mono text-xs text-muted-foreground">
                                {r.joined_at ? formatDateTime(r.joined_at) : '—'}
                            </span>
                        ),
                    },
                    {
                        key: 'last_login',
                        header: 'Last login',
                        render: (r) => (
                            <span className="font-mono text-xs text-muted-foreground">
                                {r.last_login_at ? formatDateTime(r.last_login_at) : '—'}
                            </span>
                        ),
                    },
                    {
                        key: 'actions',
                        header: '',
                        className: 'w-px text-right',
                        render: (r) => (
                            <div className="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    title="Impersonate"
                                    disabled={!r.id}
                                    onClick={() => setImpersonating(r)}
                                    data-test={`member-impersonate-${r.id}`}
                                >
                                    <LogIn className="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 text-destructive hover:text-destructive"
                                    title="Remove from tenant"
                                    disabled={r.is_owner || !r.id}
                                    onClick={() => setRemoving(r)}
                                    data-test={`member-remove-${r.id}`}
                                >
                                    <UserMinus className="size-4" />
                                </Button>
                            </div>
                        ),
                    },
                ]}
                emptyMessage="No members yet."
            />

            <AlertDialog open={removing !== null} onOpenChange={(o) => !o && setRemoving(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Remove {removing?.email} from {tenant.name}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            They will lose access immediately. Their user account is not deleted.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {removing && removing.id !== null && (
                        <Form
                            action={`/admin/tenants/${tenant.id}/members/${removing.id}`}
                            method="delete"
                            onSuccess={() => setRemoving(null)}
                        >
                            {({ processing }) => (
                                <AlertDialogFooter>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setRemoving(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Remove
                                    </Button>
                                </AlertDialogFooter>
                            )}
                        </Form>
                    )}
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={impersonating !== null}
                onOpenChange={(o) => !o && setImpersonating(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Impersonate {impersonating?.email}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            You will be signed in as this user inside {tenant.name}. Return
                            via the impersonation banner.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {impersonating && impersonating.id !== null && (
                        <Form
                            action={`/admin/tenants/${tenant.id}/members/${impersonating.id}/impersonate`}
                            method="post"
                            onSuccess={() => setImpersonating(null)}
                        >
                            {({ processing }) => (
                                <AlertDialogFooter>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setImpersonating(null)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Impersonate
                                    </Button>
                                </AlertDialogFooter>
                            )}
                        </Form>
                    )}
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}

function ActivityTab({
    tenant,
    auditLog,
    webhookEvents,
    loginHistory,
    outboundDeliveries,
}: {
    tenant: TenantProp;
    auditLog: AuditRow[];
    webhookEvents: WebhookRow[];
    loginHistory: LoginRow[];
    outboundDeliveries: OutboundDeliveryRow[];
}) {
    const retryDelivery = (id: number) => {
        router.post(`/admin/tenants/${tenant.id}/webhook-deliveries/${id}/retry`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <ActivityPanel
                title="Audit log"
                viewAllHref={adminAuditIndex({ query: { tenant_id: tenant.id } }).url}
                rows={auditLog}
                rowKey={(r) => r.id}
                columns={[
                    {
                        key: 'date',
                        header: 'Date',
                        render: (r) => (
                            <span className="font-mono text-xs">
                                {r.created_at ? formatDateTime(r.created_at) : '—'}
                            </span>
                        ),
                    },
                    {
                        key: 'actor',
                        header: 'Actor',
                        render: (r) =>
                            r.user ? r.user.email : <span className="text-muted-foreground">system</span>,
                    },
                    { key: 'action', header: 'Action', render: (r) => <span className="font-mono text-xs">{r.action}</span> },
                ]}
                emptyMessage="No audit activity."
            />

            <ActivityPanel
                title="Login history"
                rows={loginHistory}
                rowKey={(r) => r.id}
                columns={[
                    {
                        key: 'date',
                        header: 'Date',
                        render: (r) => (
                            <span className="font-mono text-xs">
                                {r.created_at ? formatDateTime(r.created_at) : '—'}
                            </span>
                        ),
                    },
                    { key: 'user', header: 'User', render: (r) => r.user?.email ?? '—' },
                    { key: 'outcome', header: 'Outcome', render: (r) => <Badge variant={r.outcome === 'succeeded' ? 'default' : 'destructive'}>{r.outcome}</Badge> },
                ]}
                emptyMessage="No logins."
            />

            <ActivityPanel
                title="Webhook events"
                viewAllHref={adminWebhooksIndex({ query: { tenant_id: tenant.id } }).url}
                rows={webhookEvents}
                rowKey={(r) => r.id}
                columns={[
                    {
                        key: 'date',
                        header: 'Date',
                        render: (r) => (
                            <span className="font-mono text-xs">
                                {r.created_at ? formatDateTime(r.created_at) : '—'}
                            </span>
                        ),
                    },
                    { key: 'event', header: 'Event', render: (r) => <span className="font-mono text-xs">{r.event_type}</span> },
                    { key: 'status', header: 'Status', render: (r) => <Badge variant={r.status === 'processed' ? 'default' : 'outline'}>{r.status}</Badge> },
                ]}
                emptyMessage="No events."
            />

            <ActivityPanel
                title="Outbound deliveries"
                description="Recent outbound webhook deliveries — failed rows can be re-queued."
                rows={outboundDeliveries}
                rowKey={(r) => r.id}
                columns={[
                    {
                        key: 'date',
                        header: 'Date',
                        render: (r) => (
                            <span className="font-mono text-xs">
                                {r.created_at ? formatDateTime(r.created_at) : '—'}
                            </span>
                        ),
                    },
                    { key: 'event', header: 'Event', render: (r) => <span className="font-mono text-xs">{r.event_type}</span> },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (r) => (
                            <Badge
                                variant={
                                    r.status === 'succeeded'
                                        ? 'default'
                                        : r.status === 'failed' || r.status === 'abandoned'
                                          ? 'destructive'
                                          : 'outline'
                                }
                            >
                                {r.status}
                                {r.response_code ? ` · ${r.response_code}` : ''}
                            </Badge>
                        ),
                    },
                    {
                        key: 'action',
                        header: '',
                        className: 'w-px text-right',
                        render: (r) =>
                            r.retryable ? (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => retryDelivery(r.id)}
                                    data-test={`retry-delivery-${r.id}`}
                                >
                                    <RotateCcw className="size-3.5" />
                                    Retry
                                </Button>
                            ) : null,
                    },
                ]}
                emptyMessage="No outbound deliveries."
            />
        </div>
    );
}

function DangerTab({
    tenant,
    onSuspend,
    onRestore,
    onDelete,
    onForceDelete,
}: {
    tenant: TenantProp;
    onSuspend: () => void;
    onRestore: () => void;
    onDelete: () => void;
    onForceDelete: () => void;
}) {
    const isArchived = tenant.deleted_at !== null;

    return (
        <div className="flex flex-col gap-3">
            <DangerRow
                title={tenant.status === 'suspended' ? 'Reactivate tenant' : 'Suspend tenant'}
                description={
                    tenant.status === 'suspended'
                        ? 'Re-enable access for all members.'
                        : 'Block sign-in and tenant routes immediately. Reversible.'
                }
                buttonLabel={tenant.status === 'suspended' ? 'Reactivate' : 'Suspend'}
                onClick={tenant.status === 'suspended' ? onRestore : onSuspend}
                hidden={isArchived}
                destructive={tenant.status !== 'suspended'}
                testId={tenant.status === 'suspended' ? 'danger-reactivate' : 'danger-suspend'}
            />
            <DangerRow
                title="Soft-delete tenant"
                description="Hides the tenant from index lists; recoverable for 30 days."
                buttonLabel="Soft-delete"
                onClick={onDelete}
                hidden={isArchived}
                destructive
                testId="danger-delete"
            />
            <DangerRow
                title="Restore tenant"
                description="Bring this tenant back from soft-deleted state."
                buttonLabel="Restore"
                onClick={onRestore}
                hidden={!isArchived}
                testId="danger-restore"
            />
            <DangerRow
                title="Force-delete (GDPR purge)"
                description="Hard-delete the tenant row. Irreversible. Confirm by retyping the slug."
                buttonLabel="Force-delete"
                onClick={onForceDelete}
                hidden={!isArchived}
                destructive
                testId="danger-force-delete"
            />
        </div>
    );
}

function DangerRow({
    title,
    description,
    buttonLabel,
    onClick,
    hidden,
    destructive,
    testId,
}: {
    title: string;
    description: string;
    buttonLabel: string;
    onClick: () => void;
    hidden?: boolean;
    destructive?: boolean;
    testId: string;
}) {
    if (hidden) {
return null;
}

    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between',
                destructive && 'border-destructive/40 bg-destructive/5',
            )}
        >
            <div className="flex flex-col gap-1">
                <h3 className="text-sm font-medium">{title}</h3>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
            <Button
                variant={destructive ? 'destructive' : 'outline'}
                size="sm"
                onClick={onClick}
                data-test={testId}
            >
                {buttonLabel}
            </Button>
        </div>
    );
}

function ImpersonateDialog({
    open,
    onClose,
    tenant,
}: {
    open: boolean;
    onClose: () => void;
    tenant: TenantProp;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Impersonate tenant owner?</AlertDialogTitle>
                    <AlertDialogDescription>
                        You will be logged in as{' '}
                        <span className="font-medium">{tenant.owner?.email}</span>. Restore
                        your session from the impersonation banner.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...TenantsAdminController.impersonate.form({ tenant: tenant.id })}
                    onSuccess={onClose}
                >
                    {({ processing }) => (
                        <AlertDialogFooter>
                            <Button type="button" variant="secondary" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                <LogIn className="size-4" />
                                Impersonate
                            </Button>
                        </AlertDialogFooter>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function SuspendDialog({
    open,
    onClose,
    tenant,
}: {
    open: boolean;
    onClose: () => void;
    tenant: TenantProp;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Suspend tenant {tenant.name}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Members can sign in but tenant routes will return 403 with a
                        suspension notice. Reversible — use "Reactivate" to lift.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...TenantsAdminController.suspend.form({ tenant: tenant.id })}
                    onSuccess={onClose}
                >
                    {({ processing }) => (
                        <>
                            <Textarea
                                name="reason"
                                placeholder="Reason (optional, recorded in audit log)"
                                rows={3}
                            />
                            <AlertDialogFooter>
                                <Button type="button" variant="secondary" onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Suspend
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function RestoreDialog({
    open,
    onClose,
    tenant,
}: {
    open: boolean;
    onClose: () => void;
    tenant: TenantProp;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Restore tenant {tenant.name}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        {tenant.deleted_at
                            ? 'This will un-soft-delete the tenant row and put it back to active status.'
                            : 'Lifts the suspension flag and returns the tenant to active status.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...TenantsAdminController.restore.form({ tenantId: tenant.id })}
                    onSuccess={onClose}
                >
                    {({ processing }) => (
                        <AlertDialogFooter>
                            <Button type="button" variant="secondary" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Restore
                            </Button>
                        </AlertDialogFooter>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function SoftDeleteDialog({
    open,
    onClose,
    tenant,
}: {
    open: boolean;
    onClose: () => void;
    tenant: TenantProp;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Soft-delete {tenant.name}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        The tenant will be hidden from default listings and members lose
                        access. Use "Restore" to recover.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...TenantsAdminController.destroy.form({ tenant: tenant.id })}
                    onSuccess={onClose}
                >
                    {({ processing }) => (
                        <AlertDialogFooter>
                            <Button type="button" variant="secondary" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Soft-delete
                            </Button>
                        </AlertDialogFooter>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function ForceDeleteDialog({
    open,
    onClose,
    tenant,
}: {
    open: boolean;
    onClose: () => void;
    tenant: TenantProp;
}) {
    return (
        <AlertDialog open={open} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Force-delete {tenant.name}? (irreversible)
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Hard-deletes the tenant row and orphan records. Required for GDPR
                        purge. Type <Mono>{tenant.slug}</Mono> to confirm.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...TenantsAdminController.forceDelete.form({ tenantId: tenant.id })}
                    onSuccess={onClose}
                >
                    {({ processing }) => (
                        <>
                            <Input
                                name="confirm_slug"
                                placeholder="Retype slug to confirm"
                                autoComplete="off"
                            />
                            <AlertDialogFooter>
                                <Button type="button" variant="secondary" onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    <AlertOctagon className="size-4" />
                                    Force-delete
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function ChevronRightInline() {
    return <ArrowRight className="size-3" />;
}
