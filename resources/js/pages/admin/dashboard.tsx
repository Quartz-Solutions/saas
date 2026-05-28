import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Building2,
    ClipboardList,
    CreditCard,
    Flag,
    Receipt,
    TrendingUp,
    Users,
    Webhook,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    PolarAngleAxis,
    RadialBar,
    RadialBarChart,
    XAxis,
} from 'recharts';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent
} from '@/components/ui/chart';
import type {ChartConfig} from '@/components/ui/chart';
import { Progress } from '@/components/ui/progress';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as featureFlagsIndex } from '@/routes/admin/feature-flags';
import { index as plansIndex } from '@/routes/admin/plans';
import { index as subscriptionsIndex } from '@/routes/admin/subscriptions';
import { index as tenantsIndex } from '@/routes/admin/tenants';
import { index as webhooksIndex } from '@/routes/admin/webhooks';

// --- types -----------------------------------------------------------------

type Overview = {
    mrrCents: number;
    conversionPct: number;
    tenants: number;
    users: number;
    plansActive: number;
};
type Subscriptions = {
    active: number;
    trialing: number;
    pastDue: number;
    canceled30d: number;
    total: number;
    healthyPct: number;
};
type TrendPoint = { label: string; revenueCents?: number; count?: number };
type RevenueReports = {
    bars: { label: string; revenueCents: number }[];
    collectedCents: number;
    refundsCents: number;
};
type TopTenant = {
    slug: string;
    name: string;
    plan: string | null;
    mrrCents: number;
    members: number;
};
type Collection = {
    ratePct: number;
    paid: number;
    open: number;
    void: number;
    paidCents: number;
    openCents: number;
    voidCents: number;
};
type UserFunnel = {
    signups: number;
    verified: number;
    active30d: number;
    suspended: number;
};
type RecentTenant = {
    slug: string;
    name: string;
    owner: string | null;
    plan: string | null;
    status: string;
    mrrCents: number;
    members: number;
    createdAt: string | null;
};
type PlanSlice = { slug: string; name: string; count: number };
type Activity = {
    id: number;
    action: string;
    subject: string;
    user: string | null;
    createdAt: string | null;
};

type Props = {
    overview: Overview;
    revenueThisMonthCents: number;
    revenueLastMonthCents: number;
    revenueTrend: TrendPoint[];
    revenueSparkline: TrendPoint[];
    subscriptions: Subscriptions;
    revenueReports: RevenueReports;
    topTenants: TopTenant[];
    collection: Collection;
    userFunnel: UserFunnel;
    signupSources: { label: string; count: number }[];
    recentTenants: RecentTenant[];
    newUsersTrend: TrendPoint[];
    tenantsByPlan: PlanSlice[];
    recentActivity: Activity[];
};

// --- helpers ---------------------------------------------------------------

const money = (cents: number, compact = false) => {
    const v = cents / 100;

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            notation: compact ? 'compact' : 'standard',
            maximumFractionDigits: compact ? 1 : 0,
        }).format(v);
    } catch {
        return `$${v.toFixed(0)}`;
    }
};
const num = (n: number, compact = false) =>
    new Intl.NumberFormat('en-US', {
        notation: compact ? 'compact' : 'standard',
        maximumFractionDigits: 1,
    }).format(n);
const pct = (n: number) => `${n}%`;
const deltaPct = (curr: number, prev: number) => {
    if (prev <= 0) {
return curr > 0 ? 100 : 0;
}

    return Math.round(((curr - prev) / prev) * 100);
};
const initials = (name: string) =>
    name
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
const timeAgo = (iso: string | null) => {
    if (!iso) {
return '';
}

    const diff = Date.now() - new Date(iso).getTime();
    const m = Math.floor(diff / 60000);

    if (m < 60) {
return `${m}m ago`;
}

    const h = Math.floor(m / 60);

    if (h < 24) {
return `${h}h ago`;
}

    return `${Math.floor(h / 24)}d ago`;
};

const PLAN_COLORS: Record<string, string> = {
    free: 'var(--chart-3)',
    pro: 'var(--chart-1)',
    enterprise: 'var(--chart-4)',
};
const statusVariant = (s: string): 'default' | 'secondary' | 'outline' | 'destructive' =>
    s === 'active' ? 'default' : s === 'past_due' ? 'destructive' : 'secondary';

const navCards = [
    { title: 'Tenants', href: tenantsIndex(), icon: Building2 },
    { title: 'Plans', href: plansIndex(), icon: CreditCard },
    { title: 'Subscriptions', href: subscriptionsIndex(), icon: Receipt },
    { title: 'Webhooks', href: webhooksIndex(), icon: Webhook },
    { title: 'Audit log', href: auditIndex(), icon: ClipboardList },
    { title: 'Feature flags', href: featureFlagsIndex(), icon: Flag },
];

// --- page ------------------------------------------------------------------

export default function AdminDashboard(props: Props) {
    return (
        <>
            <Head title="Admin" />

            <div className="space-y-4">
                {/* Headline band — balanced heights. */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <PlatformOverview {...props} />
                    <RevenueThisMonth {...props} />
                    <SubscriptionsCard subs={props.subscriptions} />
                </div>

                {/* Revenue + subscription health. */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <RevenueReportsCard reports={props.revenueReports} mrrCents={props.overview.mrrCents} />
                    <SubscriptionHealthCard subs={props.subscriptions} />
                </div>

                {/* Masonry columns: cards keep their natural height and pack
                    tightly, so short cards don't stretch to a tall neighbour.
                    Cards are distributed to balance the three column heights. */}
                <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4">
                        <RecentActivityCard activity={props.recentActivity} />
                        <SignupSourcesCard sources={props.signupSources} />
                    </div>
                    <div className="flex flex-col gap-4">
                        <TopTenantsCard tenants={props.topTenants} />
                        <TenantsByPlanCard slices={props.tenantsByPlan} />
                    </div>
                    <div className="flex flex-col gap-4">
                        <UserFunnelCard funnel={props.userFunnel} />
                        <NewUsersCard trend={props.newUsersTrend} />
                        <CollectionCard collection={props.collection} />
                    </div>
                </div>

                {/* Full-width table. */}
                <RecentTenantsCard tenants={props.recentTenants} />

                {/* Quick nav. */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {navCards.map((c) => (
                        <Link key={c.title} href={c.href}>
                            <Card className="transition-colors hover:bg-muted/50">
                                <CardContent className="flex items-center gap-2 py-3 text-sm font-medium">
                                    <c.icon className="size-4 text-muted-foreground" />
                                    {c.title}
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

// --- widgets ---------------------------------------------------------------

function PlatformOverview({ overview, tenantsByPlan, revenueSparkline }: Props) {
    const cfg = { revenueCents: { label: 'Revenue', color: 'rgba(255,255,255,0.7)' } } satisfies ChartConfig;

    return (
        <Card className="overflow-hidden border-0 bg-gradient-to-br from-violet-600 to-indigo-600 text-white lg:col-span-6">
            <CardHeader>
                <CardTitle className="text-white">Platform Overview</CardTitle>
                <CardDescription className="text-white/70">
                    Monthly recurring revenue & plan mix
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="text-4xl font-semibold tracking-tight">
                            {money(overview.mrrCents)}
                            <span className="ml-1 text-base font-normal text-white/70">MRR</span>
                        </div>
                        <div className="mt-1 text-sm text-white/80">
                            {pct(overview.conversionPct)} of {num(overview.tenants)} tenants on a
                            paid plan · {num(overview.users, true)} users
                        </div>
                    </div>
                    <div className="h-14 w-32">
                        <ChartContainer config={cfg} className="aspect-auto h-full w-full">
                            <AreaChart data={revenueSparkline} margin={{ top: 4, bottom: 0, left: 0, right: 0 }}>
                                <Area
                                    dataKey="revenueCents"
                                    type="monotone"
                                    stroke="white"
                                    strokeWidth={2}
                                    fill="rgba(255,255,255,0.18)"
                                />
                            </AreaChart>
                        </ChartContainer>
                    </div>
                </div>

                <div className="mt-5 grid grid-cols-3 gap-2">
                    {tenantsByPlan.map((p) => (
                        <div key={p.slug} className="rounded-lg bg-white/10 px-3 py-2">
                            <div className="text-xl font-semibold">{num(p.count)}</div>
                            <div className="text-xs text-white/70">{p.name}</div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function RevenueThisMonth({ revenueThisMonthCents, revenueLastMonthCents, revenueSparkline }: Props) {
    const delta = deltaPct(revenueThisMonthCents, revenueLastMonthCents);
    const up = delta >= 0;
    const cfg = { revenueCents: { label: 'Revenue', color: 'var(--chart-2)' } } satisfies ChartConfig;

    return (
        <Card className="lg:col-span-3">
            <CardHeader className="pb-2">
                <CardDescription>Revenue this month</CardDescription>
                <CardTitle className="text-3xl">{money(revenueThisMonthCents)}</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    className={`flex items-center gap-1 text-xs ${up ? 'text-emerald-600' : 'text-red-600'}`}
                >
                    {up ? <ArrowUpRight className="size-3" /> : <ArrowDownRight className="size-3" />}
                    {Math.abs(delta)}% vs last month
                </div>
                <ChartContainer config={cfg} className="mt-3 aspect-auto h-16 w-full">
                    <AreaChart data={revenueSparkline} margin={{ top: 4, bottom: 0, left: 0, right: 0 }}>
                        <Area
                            dataKey="revenueCents"
                            type="monotone"
                            stroke="var(--color-revenueCents)"
                            strokeWidth={2}
                            fill="var(--color-revenueCents)"
                            fillOpacity={0.15}
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

function SubscriptionsCard({ subs }: { subs: Subscriptions }) {
    return (
        <Card className="lg:col-span-3">
            <CardHeader className="pb-2">
                <CardDescription>Subscriptions</CardDescription>
                <CardTitle className="text-3xl">
                    {num(subs.active)}
                    <span className="ml-1 text-base font-normal text-muted-foreground">
                        / {num(subs.total)}
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="flex items-center gap-1 text-xs text-emerald-600">
                    <TrendingUp className="size-3" /> {subs.healthyPct}% healthy
                </div>
                <Progress value={subs.healthyPct} />
                <div className="flex justify-between text-xs text-muted-foreground">
                    <span>{num(subs.trialing)} trialing</span>
                    <span className="text-amber-600">{num(subs.pastDue)} past due</span>
                </div>
            </CardContent>
        </Card>
    );
}

function RevenueReportsCard({
    reports,
    mrrCents,
}: {
    reports: RevenueReports;
    mrrCents: number;
}) {
    const cfg = { revenueCents: { label: 'Revenue', color: 'var(--chart-1)' } } satisfies ChartConfig;

    return (
        <Card className="lg:col-span-7">
            <CardHeader>
                <CardTitle>Revenue Reports</CardTitle>
                <CardDescription>Collected revenue, last 8 months</CardDescription>
            </CardHeader>
            <CardContent>
                <ChartContainer config={cfg} className="aspect-auto h-56 w-full">
                    <BarChart data={reports.bars} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" />
                        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                        <ChartTooltip
                            content={
                                <ChartTooltipContent
                                    formatter={(value) => money(Number(value))}
                                />
                            }
                        />
                        <Bar dataKey="revenueCents" fill="var(--color-revenueCents)" radius={[6, 6, 0, 0]} />
                    </BarChart>
                </ChartContainer>

                <div className="mt-4 grid grid-cols-3 gap-3">
                    <Metric label="MRR" value={money(mrrCents)} />
                    <Metric label="Collected (mo)" value={money(reports.collectedCents)} accent="emerald" />
                    <Metric label="Refunds (mo)" value={money(reports.refundsCents)} accent="red" />
                </div>
            </CardContent>
        </Card>
    );
}

function SubscriptionHealthCard({ subs }: { subs: Subscriptions }) {
    const data = [{ name: 'healthy', value: subs.healthyPct, fill: 'var(--chart-2)' }];
    const cfg = { value: { label: 'Healthy' } } satisfies ChartConfig;
    const rows = [
        { label: 'Active', value: subs.active, color: 'var(--chart-2)' },
        { label: 'Trialing', value: subs.trialing, color: 'var(--chart-1)' },
        { label: 'Past due', value: subs.pastDue, color: 'var(--chart-4)' },
        { label: 'Canceled (30d)', value: subs.canceled30d, color: 'var(--muted-foreground)' },
    ];

    return (
        <Card className="lg:col-span-5">
            <CardHeader>
                <CardTitle>Subscription Health</CardTitle>
                <CardDescription>{num(subs.total)} total subscriptions</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col items-center gap-4 sm:flex-row">
                <div className="relative">
                    <ChartContainer config={cfg} className="aspect-square h-40 w-40">
                        <RadialBarChart
                            data={data}
                            startAngle={90}
                            endAngle={90 - (subs.healthyPct / 100) * 360}
                            innerRadius={62}
                            outerRadius={84}
                        >
                            <PolarAngleAxis type="number" domain={[0, 100]} tick={false} />
                            <RadialBar dataKey="value" background cornerRadius={8} />
                        </RadialBarChart>
                    </ChartContainer>
                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-3xl font-semibold">{subs.healthyPct}%</span>
                        <span className="text-xs text-muted-foreground">healthy</span>
                    </div>
                </div>
                <div className="flex-1 space-y-2">
                    {rows.map((r) => (
                        <div key={r.label} className="flex items-center justify-between text-sm">
                            <span className="flex items-center gap-2">
                                <span
                                    className="size-2.5 rounded-full"
                                    style={{ backgroundColor: r.color }}
                                />
                                {r.label}
                            </span>
                            <span className="font-mono font-medium">{num(r.value)}</span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function TopTenantsCard({ tenants }: { tenants: TopTenant[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Top Tenants</CardTitle>
                <CardDescription>By monthly subscription value</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {tenants.map((t) => (
                    <div key={t.slug} className="flex items-center gap-3">
                        <Avatar className="size-8">
                            <AvatarFallback className="text-xs">{initials(t.name)}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-sm font-medium">{t.name}</div>
                            <div className="text-xs text-muted-foreground">
                                {t.plan ?? '—'} · {num(t.members)} members
                            </div>
                        </div>
                        <div className="font-mono text-sm font-medium">{money(t.mrrCents)}</div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function CollectionCard({ collection }: { collection: Collection }) {
    const total = collection.paidCents + collection.openCents + collection.voidCents || 1;
    const seg = (cents: number) => `${(cents / total) * 100}%`;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Collection Rate</CardTitle>
                <CardDescription>Paid vs outstanding invoices</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="text-4xl font-semibold">{collection.ratePct}%</div>
                <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
                    <div style={{ width: seg(collection.paidCents), backgroundColor: 'var(--chart-2)' }} />
                    <div style={{ width: seg(collection.openCents), backgroundColor: 'var(--chart-4)' }} />
                    <div style={{ width: seg(collection.voidCents), backgroundColor: 'var(--muted-foreground)' }} />
                </div>
                <div className="grid grid-cols-3 gap-2 text-center text-xs">
                    <LegendStat color="var(--chart-2)" label="Paid" value={collection.paid} />
                    <LegendStat color="var(--chart-4)" label="Open" value={collection.open} />
                    <LegendStat color="var(--muted-foreground)" label="Void" value={collection.void} />
                </div>
            </CardContent>
        </Card>
    );
}

function UserFunnelCard({ funnel }: { funnel: UserFunnel }) {
    const rows = [
        { label: 'Signups', value: funnel.signups },
        { label: 'Verified', value: funnel.verified },
        { label: 'Active (30d)', value: funnel.active30d },
        { label: 'Suspended', value: funnel.suspended },
    ];
    const max = Math.max(...rows.map((r) => r.value), 1);

    return (
        <Card>
            <CardHeader>
                <CardTitle>User Funnel</CardTitle>
                <CardDescription>{num(funnel.signups)} total accounts</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {rows.map((r) => (
                    <div key={r.label} className="space-y-1">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">{r.label}</span>
                            <span className="font-mono font-medium">
                                {num(r.value)}{' '}
                                <span className="text-xs text-muted-foreground">
                                    ({Math.round((r.value / funnel.signups) * 100)}%)
                                </span>
                            </span>
                        </div>
                        <Progress value={(r.value / max) * 100} />
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function NewUsersCard({ trend }: { trend: TrendPoint[] }) {
    const cfg = { count: { label: 'New users', color: 'var(--chart-1)' } } satisfies ChartConfig;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Users className="size-4" /> New Users
                </CardTitle>
                <CardDescription>Sign-ups per month, last 12 months</CardDescription>
            </CardHeader>
            <CardContent>
                <ChartContainer config={cfg} className="aspect-auto h-44 w-full">
                    <AreaChart data={trend} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                        <CartesianGrid vertical={false} strokeDasharray="3 3" />
                        <XAxis dataKey="label" tickLine={false} axisLine={false} tickMargin={8} />
                        <ChartTooltip content={<ChartTooltipContent />} />
                        <Area
                            dataKey="count"
                            type="monotone"
                            stroke="var(--color-count)"
                            strokeWidth={2}
                            fill="var(--color-count)"
                            fillOpacity={0.15}
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}

function TenantsByPlanCard({ slices }: { slices: PlanSlice[] }) {
    const cfg = slices.reduce<ChartConfig>((acc, s) => {
        acc[s.slug] = { label: s.name, color: PLAN_COLORS[s.slug] ?? 'var(--chart-5)' };

        return acc;
    }, {});
    const total = slices.reduce((sum, s) => sum + s.count, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tenants by Plan</CardTitle>
                <CardDescription>{num(total)} tenants with a subscription</CardDescription>
            </CardHeader>
            <CardContent className="flex items-center gap-4">
                <ChartContainer config={cfg} className="aspect-square h-40">
                    <PieChart>
                        <ChartTooltip content={<ChartTooltipContent nameKey="slug" hideLabel />} />
                        <Pie data={slices} dataKey="count" nameKey="slug" innerRadius={42} strokeWidth={3}>
                            {slices.map((s) => (
                                <Cell key={s.slug} fill={PLAN_COLORS[s.slug] ?? 'var(--chart-5)'} />
                            ))}
                        </Pie>
                    </PieChart>
                </ChartContainer>
                <div className="flex-1 space-y-2">
                    {slices.map((s) => (
                        <div key={s.slug} className="flex items-center justify-between text-sm">
                            <span className="flex items-center gap-2">
                                <span
                                    className="size-2.5 rounded-full"
                                    style={{ backgroundColor: PLAN_COLORS[s.slug] ?? 'var(--chart-5)' }}
                                />
                                {s.name}
                            </span>
                            <span className="font-mono font-medium">{num(s.count)}</span>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function RecentActivityCard({ activity }: { activity: Activity[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Recent Activity</CardTitle>
                <CardDescription>Latest audited changes</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {activity.length === 0 && (
                    <p className="text-sm text-muted-foreground">No recent activity.</p>
                )}
                {activity.map((a) => (
                    <div key={a.id} className="flex items-start gap-3 text-sm">
                        <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />
                        <div className="min-w-0 flex-1">
                            <span className="font-medium">{a.action}</span>{' '}
                            <span className="text-muted-foreground">{a.subject}</span>
                            <div className="text-xs text-muted-foreground">
                                {a.user ?? 'system'} · {timeAgo(a.createdAt)}
                            </div>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function SignupSourcesCard({ sources }: { sources: { label: string; count: number }[] }) {
    const total = sources.reduce((s, x) => s + x.count, 0) || 1;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Sign-up Sources</CardTitle>
                <CardDescription>How accounts were created</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {sources.map((s) => (
                    <div key={s.label} className="space-y-1">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">{s.label}</span>
                            <span className="font-mono font-medium">{num(s.count)}</span>
                        </div>
                        <Progress value={(s.count / total) * 100} />
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function RecentTenantsCard({ tenants }: { tenants: RecentTenant[] }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle>Recent Tenants</CardTitle>
                    <CardDescription>Newest workspaces</CardDescription>
                </div>
                <Link href={tenantsIndex()} className="text-sm text-primary hover:underline">
                    View all
                </Link>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-xs text-muted-foreground">
                                <th className="pb-2 font-medium">Tenant</th>
                                <th className="pb-2 font-medium">Owner</th>
                                <th className="pb-2 font-medium">Plan</th>
                                <th className="pb-2 text-right font-medium">MRR</th>
                                <th className="pb-2 text-right font-medium">Members</th>
                                <th className="pb-2 text-right font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.map((t) => (
                                <tr key={t.slug} className="border-b last:border-0">
                                    <td className="py-2.5">
                                        <div className="flex items-center gap-2">
                                            <Avatar className="size-7">
                                                <AvatarFallback className="text-[10px]">
                                                    {initials(t.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span className="font-medium">{t.name}</span>
                                        </div>
                                    </td>
                                    <td className="py-2.5 text-muted-foreground">{t.owner ?? '—'}</td>
                                    <td className="py-2.5">{t.plan ?? 'Free'}</td>
                                    <td className="py-2.5 text-right font-mono">{money(t.mrrCents)}</td>
                                    <td className="py-2.5 text-right font-mono">{num(t.members)}</td>
                                    <td className="py-2.5 text-right">
                                        <Badge variant={statusVariant(t.status)}>{t.status}</Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}

// --- small shared bits ------------------------------------------------------

function Metric({
    label,
    value,
    accent,
}: {
    label: string;
    value: string;
    accent?: 'emerald' | 'red';
}) {
    return (
        <div className="rounded-lg border p-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={`mt-0.5 font-mono text-lg font-semibold ${
                    accent === 'emerald'
                        ? 'text-emerald-600'
                        : accent === 'red'
                          ? 'text-red-600'
                          : ''
                }`}
            >
                {value}
            </div>
        </div>
    );
}

function LegendStat({ color, label, value }: { color: string; label: string; value: number }) {
    return (
        <div>
            <div className="flex items-center justify-center gap-1.5">
                <span className="size-2 rounded-full" style={{ backgroundColor: color }} />
                <span className="text-muted-foreground">{label}</span>
            </div>
            <div className="mt-0.5 font-mono font-semibold">{num(value)}</div>
        </div>
    );
}
