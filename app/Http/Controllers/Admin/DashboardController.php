<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly SubscriptionsAdminController $subs) {}

    public function __invoke(): Response
    {
        $stats = Cache::remember('admin.dashboard.stats', 300, fn () => array_merge(
            $this->subs->stats(),
            [
                'tenants' => Tenant::query()->count(),
                'users' => User::query()->count(),
                'plans_active' => Plan::query()->where('is_active', true)->count(),
                'subscriptions_total' => Subscription::query()->count(),
            ],
        ));

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
        ]);
    }
}
