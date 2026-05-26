import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    CreditCard,
    Flag,
    Receipt,
    Webhook,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as featureFlagsIndex } from '@/routes/admin/feature-flags';
import { index as plansIndex } from '@/routes/admin/plans';
import { index as subscriptionsIndex } from '@/routes/admin/subscriptions';
import { index as tenantsIndex } from '@/routes/admin/tenants';
import { index as webhooksIndex } from '@/routes/admin/webhooks';

type Stats = {
    mrr_cents: number;
    active: number;
    trialing: number;
    past_due: number;
    canceled_30d: number;
    tenants: number;
    users: number;
    plans_active: number;
    subscriptions_total: number;
};

type Props = {
    stats: Stats;
};

const cards = [
    {
        title: 'Tenants',
        description: 'Search, inspect, impersonate.',
        href: tenantsIndex(),
        icon: Building2,
    },
    {
        title: 'Plans',
        description: 'Pricing tiers — DB-owned + Stripe-synced.',
        href: plansIndex(),
        icon: CreditCard,
    },
    {
        title: 'Subscriptions',
        description: 'Every tenant sub, plus admin overrides.',
        href: subscriptionsIndex(),
        icon: Receipt,
    },
    {
        title: 'Webhook events',
        description: 'Inspect raw payloads and replay processing.',
        href: webhooksIndex(),
        icon: Webhook,
    },
    {
        title: 'Audit log',
        description: 'Who did what when.',
        href: auditIndex(),
        icon: ClipboardList,
    },
    {
        title: 'Feature flags',
        description: 'Global toggles + per-tenant overrides.',
        href: featureFlagsIndex(),
        icon: Flag,
    },
];

const formatMoney = (cents: number) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(
            cents / 100,
        );
    } catch {
        return `$${(cents / 100).toFixed(2)}`;
    }
};

export default function AdminDashboard({ stats }: Props) {
    return (
        <>
            <Head title="Admin" />
            <Heading title="Overview" description="Operational snapshot + nav to admin tools." />

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">
                <StatCard label="MRR" value={formatMoney(stats.mrr_cents)} />
                <StatCard label="Active subs" value={String(stats.active)} />
                <StatCard label="Trialing" value={String(stats.trialing)} />
                <StatCard
                    label="Past due"
                    value={String(stats.past_due)}
                    accent={stats.past_due > 0 ? 'warning' : undefined}
                />
                <StatCard
                    label="Canceled (30d)"
                    value={String(stats.canceled_30d)}
                    subtle
                />
            </div>

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                <StatCard label="Tenants" value={String(stats.tenants)} subtle />
                <StatCard label="Users" value={String(stats.users)} subtle />
                <StatCard label="Active plans" value={String(stats.plans_active)} subtle />
                <StatCard label="Total subs" value={String(stats.subscriptions_total)} subtle />
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {cards.map((card) => (
                    <Link key={card.title} href={card.href} className="block">
                        <Card className="transition-colors hover:bg-muted/40">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <card.icon className="size-4" />
                                    {card.title}
                                </CardTitle>
                                <CardDescription>{card.description}</CardDescription>
                            </CardHeader>
                            <CardContent />
                        </Card>
                    </Link>
                ))}
            </div>
        </>
    );
}

function StatCard({
    label,
    value,
    accent,
    subtle,
}: {
    label: string;
    value: string;
    accent?: 'warning';
    subtle?: boolean;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle
                    className={
                        subtle
                            ? 'text-xs font-normal text-muted-foreground'
                            : 'text-xs font-medium text-muted-foreground'
                    }
                >
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    className={
                        accent === 'warning'
                            ? 'font-mono text-2xl font-semibold text-amber-600'
                            : 'font-mono text-2xl font-semibold'
                    }
                >
                    {value}
                </div>
            </CardContent>
        </Card>
    );
}
