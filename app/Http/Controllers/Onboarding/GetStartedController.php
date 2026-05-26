<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\GetStartedRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingService;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Stripe\StripeGateway;
use App\Support\Tenancy\TenantService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * One-shot sign-up: creates the User + Tenant + (free or pending paid)
 * Subscription in a single transaction, logs the user in, and either lands
 * them on the dashboard (free plans) or redirects to the gateway's hosted
 * checkout (paid plans).
 *
 * Stripe is the only gateway with a fully wired checkout-session flow
 * today; non-Stripe gateways' driver scaffolds expose a
 * `metadata.redirect_url` on the returned Subscription/Payment which we
 * pass through unchanged.
 */
class GetStartedController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly BillingService $billing,
        private readonly GatewayRegistry $registry,
        private readonly CreateNewUser $createUser,
    ) {}

    public function show(): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get()
            ->map(fn (Plan $p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'description' => $p->description ?? '',
                'price_cents' => (int) $p->price_cents,
                'currency' => $p->currency,
                'interval' => $p->billing_period,
                'trial_days' => (int) $p->trial_days,
                'features' => array_map(
                    fn (array $f) => $f['name'],
                    $p->featuresWithMetadata(),
                ),
            ])
            ->all();

        return Inertia::render('onboarding/get-started', [
            'plans' => $plans,
            'gateways' => collect($this->registry->all())
                ->map(fn ($g) => ['id' => $g->id(), 'name' => $g->displayName()])
                ->values()
                ->all(),
            'defaultGateway' => (string) config('billing.default_gateway', 'stripe'),
        ]);
    }

    public function store(GetStartedRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $plan = Plan::query()->where('slug', $data['plan_slug'])->firstOrFail();
        $gatewayId = $data['gateway'] ?? (string) config('billing.default_gateway', 'stripe');

        // Free plan path: create user + tenant + free Subscription in one tx,
        // log in, land on dashboard. No gateway round-trip.
        if ((int) $plan->price_cents === 0) {
            [$tenant] = $this->createUserAndTenant($data);
            $this->billing->subscribeToPlan($tenant, $plan, $gatewayId);

            return redirect()->route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
        }

        // Paid plan path. Stripe gets a Checkout Session redirect; other
        // gateways fall back to their own hosted page (returned via the
        // driver's createSubscription metadata) where wired.
        [$tenant, $user] = $this->createUserAndTenant($data);

        if ($gatewayId === 'stripe') {
            $stripe = $this->registry->find('stripe');
            if (! $stripe instanceof StripeGateway) {
                return $this->bailToBilling($tenant, __('Stripe is not configured. Pick a plan from your billing page.'));
            }

            try {
                $url = $stripe->checkoutSessionUrl(
                    $tenant,
                    $plan,
                    successUrl: route('onboarding.return', ['tenantSlug' => $tenant->slug]),
                    cancelUrl: route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]),
                );
            } catch (Throwable $e) {
                Log::warning('Stripe Checkout session failed during sign-up', ['error' => $e->getMessage()]);

                return $this->bailToBilling($tenant, __('Could not start checkout. Try again from the billing page.'));
            }

            return redirect()->away($url);
        }

        // Non-Stripe gateway: call the driver's createSubscription and follow
        // metadata.redirect_url if present, otherwise land on the billing page.
        try {
            $sub = $this->billing->subscribeToPlan($tenant, $plan, $gatewayId);
            $redirect = $sub->metadata['redirect_url'] ?? $sub->metadata['approve_url'] ?? null;
            if ($redirect !== null) {
                return redirect()->away((string) $redirect);
            }
        } catch (Throwable $e) {
            Log::warning('Non-Stripe gateway subscribe failed during sign-up', [
                'gateway' => $gatewayId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->bailToBilling($tenant, __('Continue from your billing page to finish checkout.'));
    }

    /**
     * Stripe Checkout Session redirect target. Stripe handles the actual
     * subscription create + webhook fan-out; this just confirms the session
     * and lands the user on /t/{slug}/dashboard.
     */
    public function return(string $tenantSlug): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Welcome! Your subscription is being activated.'),
        ]);

        return redirect()->route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Tenant, 1: User}
     */
    protected function createUserAndTenant(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = $this->createUser->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
            ]);

            // Mark email verified at sign-up — Stripe will collect a second
            // proof (payment method) anyway, and this lets the user reach
            // their dashboard without bouncing through the verify-email gate.
            // Comment out if you'd rather force the verify step.
            $user->forceFill(['email_verified_at' => now()])->save();

            Auth::login($user);
            Event::dispatch(new Registered($user));

            $tenant = $this->tenants->create($user, [
                'name' => $data['tenant_name'],
                'slug' => $data['tenant_slug'] ?? null,
            ]);

            $user->forceFill(['current_tenant_id' => $tenant->id])->save();

            return [$tenant, $user];
        });
    }

    protected function bailToBilling(Tenant $tenant, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'info', 'message' => $message]);

        return redirect()->route('tenants.billing.plans', ['tenantSlug' => $tenant->slug]);
    }
}
