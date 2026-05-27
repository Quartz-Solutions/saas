<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\GetStartedRequest;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Tenancy\TenantService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Inertia\Response;

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
        private readonly CheckoutService $checkout,
        private readonly CreateNewUser $createUser,
    ) {}

    public function show(Request $request): Response
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

        // Honour ?plan=<slug> when the marketing pages link here with a
        // chosen plan (pricing card CTA, hero "Get started", etc.). Only
        // accept slugs that actually appear in the public catalog.
        $requestedSlug = $request->string('plan')->toString();
        $selectedPlanSlug = collect($plans)->firstWhere('slug', $requestedSlug)['slug'] ?? null;

        return Inertia::render('onboarding/get-started', [
            'plans' => $plans,
            'selectedPlanSlug' => $selectedPlanSlug,
        ]);
    }

    public function store(GetStartedRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $plan = Plan::query()->where('slug', $data['plan_slug'])->firstOrFail();

        // Create User + Tenant in one transaction, log the user in.
        [$tenant, $user] = $this->createUserAndTenant($data);

        // Delegate to the polymorphic checkout flow. CheckoutService::start
        // handles the free-plan fast-path internally (marks the session
        // completed + creates a free Subscription synchronously).
        $session = $this->checkout->start($user, $tenant, $plan);

        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Welcome to :app!', ['app' => config('app.name')]),
            ]);

            return redirect()->route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
        }

        // Paid plan → hand off to /checkout/{session} for gateway picking.
        return redirect()->route('checkout.show', ['session' => $session->public_id]);
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
}
