<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin operational overview: a high-density analytics dashboard.
 *
 * All metrics are assembled from grouped queries and cached for 5 minutes
 * (keyed `admin.dashboard.metrics`). Money is always returned in integer
 * cents; the front-end formats it.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SubscriptionsAdminController $subs) {}

    public function __invoke(): Response
    {
        $metrics = Cache::remember(
            'admin.dashboard.metrics',
            now()->addMinutes(5),
            fn (): array => $this->buildMetrics(),
        );

        return Inertia::render('admin/dashboard', $metrics);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetrics(): array
    {
        $subStats = $this->subs->stats();

        return [
            'overview' => $this->overview($subStats),
            'revenueThisMonthCents' => $this->revenueBetween(now()->startOfMonth(), now()),
            'revenueLastMonthCents' => $this->revenueBetween(
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ),
            'revenueTrend' => $this->revenueTrend(12),
            'revenueSparkline' => $this->revenueTrend(12, sparkline: true),
            'subscriptions' => $this->subscriptionHealth($subStats),
            'revenueReports' => $this->revenueReports(),
            'topTenants' => $this->topTenants(),
            'collection' => $this->collection(),
            'userFunnel' => $this->userFunnel(),
            'signupSources' => $this->signupSources(),
            'recentTenants' => $this->recentTenants(),
            'newUsersTrend' => $this->newUsersTrend(12),
            'tenantsByPlan' => $this->tenantsByPlan(),
            'recentActivity' => $this->recentActivity(),
        ];
    }

    /**
     * @param  array<string, mixed>  $subStats
     * @return array<string, mixed>
     */
    private function overview(array $subStats): array
    {
        $tenants = Tenant::query()->count();

        // Conversion = tenants on a paid, non-terminal plan / all tenants.
        $payingTenants = Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', ['active', 'past_due'])
            ->where('plans.price_cents', '>', 0)
            ->distinct('subscriptions.tenant_id')
            ->count('subscriptions.tenant_id');

        return [
            'mrrCents' => (int) $subStats['mrr_cents'],
            'conversionPct' => $tenants > 0 ? round($payingTenants / $tenants * 100, 1) : 0.0,
            'tenants' => $tenants,
            'users' => User::query()->count(),
            'plansActive' => Plan::query()->where('is_active', true)->count(),
        ];
    }

    private function revenueBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return (int) Invoice::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_paid_cents');
    }

    /**
     * Collected revenue per month for the last $months months.
     *
     * @return array<int, array{month: string, label: string, revenueCents: int}>
     */
    private function revenueTrend(int $months, bool $sparkline = false): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Invoice::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $start)
            ->selectRaw($this->monthExpr('paid_at').' as ym')
            ->selectRaw('SUM(amount_paid_cents) as cents')
            ->groupBy('ym')
            ->pluck('cents', 'ym');

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $cents = (int) ($rows[$key] ?? 0);
            $out[] = $sparkline
                ? ['label' => $m->format('M'), 'revenueCents' => $cents]
                : ['month' => $key, 'label' => $m->format('M'), 'revenueCents' => $cents];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $subStats
     * @return array<string, mixed>
     */
    private function subscriptionHealth(array $subStats): array
    {
        $active = (int) $subStats['active'];
        $trialing = (int) $subStats['trialing'];
        $pastDue = (int) $subStats['past_due'];
        $canceled30 = (int) $subStats['canceled_30d'];
        $total = Subscription::query()->count();

        $nonTerminal = max(1, $active + $trialing + $pastDue);

        return [
            'active' => $active,
            'trialing' => $trialing,
            'pastDue' => $pastDue,
            'canceled30d' => $canceled30,
            'total' => $total,
            'healthyPct' => (int) round($active / $nonTerminal * 100),
        ];
    }

    /**
     * Monthly revenue bars + the earnings / collected / refunds breakdown.
     *
     * @return array<string, mixed>
     */
    private function revenueReports(): array
    {
        $bars = $this->revenueTrend(8);

        $collectedThisMonth = $this->revenueBetween(now()->startOfMonth(), now());
        $refundsThisMonth = (int) Payment::query()
            ->whereBetween('refunded_at', [now()->startOfMonth(), now()])
            ->sum('refunded_cents');

        return [
            'bars' => $bars,
            'collectedCents' => $collectedThisMonth,
            'refundsCents' => $refundsThisMonth,
        ];
    }

    /**
     * Highest-value tenants by monthly subscription amount, with member count.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topTenants(): array
    {
        return Subscription::query()
            ->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', ['active', 'past_due', 'trialing'])
            ->whereNull('tenants.deleted_at')
            ->select([
                'tenants.slug',
                'tenants.name',
                'plans.name as plan',
                DB::raw('subscriptions.unit_amount_cents * subscriptions.quantity as mrr_cents'),
                DB::raw('(select count(*) from tenant_memberships tm where tm.tenant_id = tenants.id) as members'),
            ])
            ->orderByDesc('mrr_cents')
            ->orderByDesc('members')
            ->limit(6)
            ->get()
            ->map(fn ($r) => [
                'slug' => $r->slug,
                'name' => $r->name,
                'plan' => $r->plan,
                'mrrCents' => (int) $r->mrr_cents,
                'members' => (int) $r->members,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function collection(): array
    {
        $byStatus = Invoice::query()
            ->selectRaw('status, COUNT(*) as c, SUM(total_cents) as cents')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $paidCents = (int) ($byStatus['paid']->cents ?? 0);
        $openCents = (int) ($byStatus['open']->cents ?? 0);
        $voidCents = (int) ($byStatus['void']->cents ?? 0);
        $billable = max(1, $paidCents + $openCents);

        return [
            'ratePct' => (int) round($paidCents / $billable * 100),
            'paid' => (int) ($byStatus['paid']->c ?? 0),
            'open' => (int) ($byStatus['open']->c ?? 0),
            'void' => (int) ($byStatus['void']->c ?? 0),
            'paidCents' => $paidCents,
            'openCents' => $openCents,
            'voidCents' => $voidCents,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function userFunnel(): array
    {
        return [
            'signups' => User::query()->count(),
            'verified' => User::query()->whereNotNull('email_verified_at')->count(),
            'active30d' => User::query()->where('last_login_at', '>=', now()->subDays(30))->count(),
            'suspended' => User::query()->whereNotNull('suspended_at')->count(),
        ];
    }

    /**
     * Best-effort attribution of how users arrived. OAuth + invitation are
     * exact; the rest is bucketed as Direct.
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function signupSources(): array
    {
        $total = User::query()->count();

        $byProvider = SocialAccount::query()
            ->selectRaw('provider, COUNT(DISTINCT user_id) as c')
            ->groupBy('provider')
            ->pluck('c', 'provider');

        $google = (int) ($byProvider['google'] ?? 0);
        $github = (int) ($byProvider['github'] ?? 0);
        $invited = (int) TenantInvitation::query()
            ->whereNotNull('accepted_at')
            ->distinct('accepted_by_id')
            ->count('accepted_by_id');

        $direct = max(0, $total - $google - $github - $invited);

        return [
            ['label' => 'Direct sign-up', 'count' => $direct],
            ['label' => 'Google OAuth', 'count' => $google],
            ['label' => 'GitHub OAuth', 'count' => $github],
            ['label' => 'Invitation', 'count' => $invited],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentTenants(): array
    {
        $tenants = Tenant::query()
            ->with(['owner:id,name', 'memberships'])
            ->withCount('memberships')
            ->latest('id')
            ->limit(8)
            ->get();

        $subs = Subscription::query()
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->whereIn('status', ['active', 'past_due', 'trialing'])
            ->with('plan:id,name,slug')
            ->get()
            ->keyBy('tenant_id');

        return $tenants->map(function (Tenant $t) use ($subs) {
            $sub = $subs->get($t->id);

            return [
                'slug' => $t->slug,
                'name' => $t->name,
                'owner' => $t->owner?->name,
                'plan' => $sub?->plan?->name,
                'status' => $sub?->status ?? $t->status,
                'mrrCents' => $sub ? (int) ($sub->unit_amount_cents * $sub->quantity) : 0,
                'members' => (int) $t->memberships_count,
                'createdAt' => $t->created_at?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * New users per month for the last $months months.
     *
     * @return array<int, array{label: string, count: int}>
     */
    private function newUsersTrend(int $months): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw($this->monthExpr('created_at').' as ym')
            ->selectRaw('COUNT(*) as c')
            ->groupBy('ym')
            ->pluck('c', 'ym');

        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $out[] = [
                'label' => $m->format('M'),
                'count' => (int) ($rows[$m->format('Y-m')] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{slug: string, name: string, count: int}>
     */
    private function tenantsByPlan(): array
    {
        $rows = Subscription::query()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw('plans.slug, plans.name, COUNT(DISTINCT subscriptions.tenant_id) as c')
            ->groupBy('plans.slug', 'plans.name')
            ->orderByDesc('c')
            ->get();

        return $rows->map(fn ($r) => [
            'slug' => (string) $r->slug,
            'name' => (string) $r->name,
            'count' => (int) $r->c,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'user_id', 'action', 'auditable_type', 'created_at'])
            ->map(fn (AuditLog $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'subject' => class_basename((string) $a->auditable_type),
                'user' => $a->user?->name,
                'createdAt' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Driver-aware "year-month" grouping expression. Postgres in prod/dev,
     * SQLite in tests.
     */
    private function monthExpr(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "to_char({$column}, 'YYYY-MM')";
    }
}
