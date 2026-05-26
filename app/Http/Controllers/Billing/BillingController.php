<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelSubscriptionRequest;
use App\Http\Requests\Billing\ResumeSubscriptionRequest;
use App\Http\Requests\Billing\SubscribeRequest;
use App\Models\Tenant;
use App\Support\Billing\BillingService;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Stripe\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly GatewayRegistry $registry,
    ) {}

    /**
     * Plan picker — lists config-driven plans + current subscription state.
     */
    public function plans(Request $request, string $tenantSlug): Response
    {
        $tenant = $this->currentTenant();

        $current = $this->billing->currentSubscription($tenant);

        $plans = collect((array) config('billing.plans', []))
            ->map(function (array $plan, string $slug) use ($current) {
                return [
                    'slug' => $slug,
                    'name' => $plan['name'] ?? $slug,
                    'description' => $plan['description'] ?? '',
                    'price_cents' => (int) ($plan['price_cents'] ?? 0),
                    'currency' => $plan['currency'] ?? config('billing.default_currency', 'USD'),
                    'interval' => $plan['interval'] ?? 'month',
                    'features' => (array) ($plan['features'] ?? []),
                    'cta' => $plan['cta'] ?? 'Choose plan',
                    'highlighted' => (bool) ($plan['highlighted'] ?? false),
                    'is_current' => $current && $current->plan && $current->plan->slug === $slug,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('billing/plans', [
            'plans' => $plans,
            'subscription' => $current === null ? null : [
                'id' => $current->id,
                'plan_slug' => $current->plan?->slug,
                'plan_name' => $current->plan?->name,
                'status' => $current->status,
                'gateway' => $current->gateway,
                'currency' => $current->currency,
                'unit_amount_cents' => (int) $current->unit_amount_cents,
                'current_period_end' => optional($current->current_period_end)->toIso8601String(),
                'trial_ends_at' => optional($current->trial_ends_at)->toIso8601String(),
                'cancel_at_period_end' => (bool) $current->cancel_at_period_end,
            ],
            'gateways' => collect($this->registry->all())->map(fn ($g) => [
                'id' => $g->id(),
                'name' => $g->displayName(),
            ])->all(),
            'default_gateway' => config('billing.default_gateway'),
        ]);
    }

    /**
     * Subscribe to / upgrade to a plan.
     */
    public function subscribe(SubscribeRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $plan = $this->billing->planForSlug($request->string('plan'));
        $gatewayId = $request->string('gateway')->toString() ?: (string) config('billing.default_gateway');

        $current = $this->billing->currentSubscription($tenant);

        if ($current !== null) {
            $this->billing->changePlan($current, $plan);
            $message = __('Plan updated.');
        } else {
            $this->billing->subscribeToPlan($tenant, $plan, $gatewayId);
            $message = __('Subscription started.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('tenants.billing.plans', ['tenantSlug' => $tenantSlug]);
    }

    /**
     * Cancel the current subscription with reason capture.
     */
    public function cancel(CancelSubscriptionRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $current = $this->billing->currentSubscription($tenant);

        if ($current === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No active subscription to cancel.')]);

            return back();
        }

        $this->billing->cancel(
            $current,
            $request->string('reason')->toString() ?: null,
            ['immediately' => (bool) $request->boolean('immediately')],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription cancellation scheduled.')]);

        return to_route('tenants.billing.plans', ['tenantSlug' => $tenantSlug]);
    }

    /**
     * Resume a cancel_at_period_end subscription.
     */
    public function resume(ResumeSubscriptionRequest $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $current = $this->billing->currentSubscription($tenant);

        if ($current === null || ! $current->cancel_at_period_end) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Nothing to resume.')]);

            return back();
        }

        $this->billing->resume($current);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription resumed.')]);

        return to_route('tenants.billing.plans', ['tenantSlug' => $tenantSlug]);
    }

    /**
     * Stripe customer portal redirect.
     */
    public function portal(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->currentTenant();
        $stripe = $this->registry->find('stripe');

        if (! $stripe instanceof StripeGateway) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Stripe is not configured.')]);

            return back();
        }

        $returnUrl = route('tenants.billing.plans', ['tenantSlug' => $tenantSlug]);
        $url = $stripe->customerPortalUrl($tenant, $returnUrl);

        return redirect()->away($url);
    }

    private function currentTenant(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        return $tenant;
    }
}
