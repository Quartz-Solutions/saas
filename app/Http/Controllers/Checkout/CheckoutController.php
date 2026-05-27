<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PayCheckoutRequest;
use App\Http\Requests\Checkout\StartCheckoutRequest;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Billing\CheckoutGateway;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\PayPal\PayPalGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Polymorphic-checkout controller. See agent-os/product/checkout.md.
 *
 * - start():   create CheckoutSession; if plan is free, fast-path to dashboard
 * - show():    render gateway picker
 * - pay():     dispatch to driver → render next-step result_kind
 * - return():  gateway redirected the customer back to us
 * - status():  JSON poll endpoint (return page hits this when webhook hasn't landed)
 * - cancel():  user clicked "cancel" on /checkout
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly GatewayRegistry $registry,
    ) {}

    public function start(StartCheckoutRequest $request): RedirectResponse
    {
        $user = $request->user();
        $plan = Plan::query()->where('slug', $request->string('plan_slug'))->firstOrFail();

        $tenant = $this->resolveTenant($request, $user);

        $session = $this->checkout->start($user, $tenant, $plan);

        // Free plan fast-path: CheckoutService already created the Subscription
        // and marked the session completed. Skip the picker entirely.
        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('You are now on the :plan plan.', ['plan' => $plan->name]),
            ]);

            return redirect()->route('tenants.dashboard', ['tenantSlug' => $tenant->slug]);
        }

        return redirect()->route('checkout.show', ['session' => $session->public_id]);
    }

    public function show(string $session, Request $request): Response|RedirectResponse
    {
        $session = $this->loadSession($session);

        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            return redirect()->route('tenants.dashboard', ['tenantSlug' => $session->tenant->slug]);
        }

        // Gateway sent the customer back with ?canceled=1 (Stripe's
        // cancel_url, PayPal cancel, etc.). Revert the session to PENDING +
        // clear the stored gateway state so the picker re-renders. Without
        // this, React's CheckoutNextStep re-reads the stale result_kind +
        // result_payload and immediately bounces the user back to the
        // gateway they tried to escape from.
        if (
            $request->boolean('canceled')
            && $session->status === CheckoutSession::STATUS_AWAITING_PAYMENT
        ) {
            $session->forceFill([
                'status' => CheckoutSession::STATUS_PENDING,
                'gateway' => null,
                'gateway_session_id' => null,
                'result_kind' => null,
                'result_payload' => null,
            ])->save();
            $session = $session->fresh();

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Checkout canceled. Pick a different option to try again.'),
            ]);

            return redirect()->route('checkout.show', ['session' => $session->public_id]);
        }

        if ($session->isTerminal()) {
            // Failed/canceled/expired: show a retry banner.
            return Inertia::render('checkout/terminal', [
                'session' => $this->serialize($session),
            ]);
        }

        // If we're already awaiting_payment, jump straight to the rendered
        // next-step rather than the picker (user came back via back button).
        if ($session->status === CheckoutSession::STATUS_AWAITING_PAYMENT) {
            return Inertia::render('checkout/show', [
                'session' => $this->serialize($session),
                'gateways' => [],
            ]);
        }

        return Inertia::render('checkout/show', [
            'session' => $this->serialize($session),
            'gateways' => $this->availableGateways($session),
        ]);
    }

    public function pay(PayCheckoutRequest $request, string $session): RedirectResponse|Response
    {
        $session = $this->loadSession($session);

        if ($session->status !== CheckoutSession::STATUS_PENDING) {
            // Already awaiting_payment or terminal — bounce back to /show.
            return redirect()->route('checkout.show', ['session' => $session->public_id]);
        }

        $gatewayId = $request->string('gateway')->toString();
        $gateway = $this->registry->find($gatewayId);

        if (! $gateway instanceof CheckoutGateway) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('That payment method is not available right now.'),
            ]);

            return redirect()->route('checkout.show', ['session' => $session->public_id]);
        }

        // Validate currency / subscription support
        if (! in_array($session->currency, $gateway->supportedCurrencies(), true)) {
            return $this->bail($session, __(':gateway does not accept :currency.', [
                'gateway' => $gateway->displayName(),
                'currency' => $session->currency,
            ]));
        }

        if ($session->intent === CheckoutSession::INTENT_SUBSCRIPTION && ! $gateway->supportsSubscriptions()) {
            return $this->bail($session, __(':gateway does not support subscriptions yet.', [
                'gateway' => $gateway->displayName(),
            ]));
        }

        try {
            $gateway->initiateCheckout($session);
        } catch (Throwable $e) {
            report($e);

            // Surface the driver's own message — it's almost always a config
            // problem (missing credentials, missing per-plan gateway id, etc.)
            // that the admin needs to see to know what to fix. Falls back to
            // a generic line if the exception has no message.
            $detail = trim($e->getMessage());

            return $this->bail($session, __(':gateway could not start checkout: :detail', [
                'gateway' => $gateway->displayName(),
                'detail' => $detail !== '' ? $detail : __('please try a different option.'),
            ]));
        }

        // Always redirect back to /checkout/{session}. The React side reads
        // result_kind + result_payload from the session and renders the
        // appropriate next step — CheckoutNextStep handles `redirect` via
        // window.location.href, iframe/widget in-page, form_post as a
        // self-submitting form, kiosk_ref as a static card.
        //
        // We intentionally do NOT `redirect()->away($url)` here: Inertia's
        // client doesn't follow external redirects from a POST response.
        // Routing every result through CheckoutNextStep also keeps the
        // controller polymorphic — adding a new result_kind requires no
        // controller change.
        return redirect()->route('checkout.show', ['session' => $session->public_id]);
    }

    public function return(string $session): RedirectResponse|Response
    {
        $session = $this->loadSession($session);

        // Webhook-less fallback: some gateways (PayPal in particular) don't
        // reliably fire a webhook to a localhost dev install, and a real
        // production install can still miss a delivery. If the driver can
        // verify completion synchronously when the customer returns, ask
        // it to. The driver short-circuits if the gateway-side state isn't
        // actually approved/completed, so it's safe to call on every visit.
        if ($session->status === CheckoutSession::STATUS_AWAITING_PAYMENT) {
            $gateway = filled($session->gateway) ? $this->registry->find($session->gateway) : null;
            if ($gateway instanceof PayPalGateway) {
                $gateway->reconcileOnReturn($session);
                $session = $session->fresh();
            }
        }

        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Your subscription is active.'),
            ]);

            return redirect()->route('tenants.dashboard', ['tenantSlug' => $session->tenant->slug]);
        }

        // Webhook hasn't landed yet — show the polling page.
        return Inertia::render('checkout/processing', [
            'session' => $this->serialize($session),
            'pollUrl' => route('checkout.status', ['session' => $session->public_id]),
        ]);
    }

    public function status(string $session): JsonResponse
    {
        $session = $this->loadSession($session);

        // Same fallback as return(): if the gateway can verify itself,
        // give it a chance to reconcile before we report status to the
        // polling client.
        if ($session->status === CheckoutSession::STATUS_AWAITING_PAYMENT) {
            $gateway = filled($session->gateway) ? $this->registry->find($session->gateway) : null;
            if ($gateway instanceof PayPalGateway) {
                $gateway->reconcileOnReturn($session);
                $session = $session->fresh();
            }
        }

        return response()->json([
            'status' => $session->status,
            'subscription_id' => $session->subscription_id,
            'tenant_slug' => $session->tenant?->slug,
        ]);
    }

    public function cancel(string $session): RedirectResponse
    {
        $session = $this->loadSession($session);
        $this->checkout->cancel($session, 'user_canceled');

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Checkout canceled.')]);

        return redirect()->route('tenants.billing.plans', ['tenantSlug' => $session->tenant->slug]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function loadSession(string $publicId): CheckoutSession
    {
        $session = CheckoutSession::query()
            ->with(['plan', 'tenant'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        if ($session->user_id !== request()->user()?->id) {
            abort(403);
        }

        return $session;
    }

    protected function resolveTenant(StartCheckoutRequest $request, $user): Tenant
    {
        if ($request->filled('tenant_id')) {
            $tenant = Tenant::query()->whereKey($request->integer('tenant_id'))->firstOrFail();
            if ($tenant->owner_id !== $user->id && ! $tenant->members()->whereKey($user->id)->exists()) {
                abort(403);
            }

            return $tenant;
        }

        $tenant = $user->currentTenant ?? $user->tenants()->first();
        if ($tenant === null) {
            abort(404, 'No tenant found for this user. Create one first via /account/tenants.');
        }

        return $tenant;
    }

    /**
     * Gateways the user can pick for this session, in display order.
     *
     * Filters:
     *   - implements CheckoutGateway
     *   - declares support for the session currency
     *   - supports subscriptions when the session intent is subscription
     *
     * Order: tenant's `preferred_gateway` first → global default → rest by
     * display name. The first row is what the picker pre-selects.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availableGateways(CheckoutSession $session): array
    {
        $tenantPreferred = $session->tenant?->preferred_gateway;
        $globalDefault = (string) config('billing.default_gateway', 'stripe');

        $rank = static function (string $id) use ($tenantPreferred, $globalDefault): int {
            if ($id === $tenantPreferred) {
                return 0;
            }
            if ($id === $globalDefault) {
                return 1;
            }

            return 2;
        };

        return collect($this->registry->all())
            ->filter(fn ($gw) => $gw instanceof CheckoutGateway)
            ->filter(fn (CheckoutGateway $gw) => in_array($session->currency, $gw->supportedCurrencies(), true))
            ->filter(fn (CheckoutGateway $gw) => $session->intent !== CheckoutSession::INTENT_SUBSCRIPTION || $gw->supportsSubscriptions())
            ->sortBy(fn (CheckoutGateway $gw) => $rank($gw->id()).':'.$gw->displayName())
            ->map(fn ($gw) => [
                'id' => $gw->id(),
                'name' => $gw->displayName(),
                'preferred' => $tenantPreferred === $gw->id(),
                'meta' => (array) config("billing.gateways.{$gw->id()}"),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(CheckoutSession $session): array
    {
        return [
            'public_id' => $session->public_id,
            'intent' => $session->intent,
            'status' => $session->status,
            'gateway' => $session->gateway,
            'currency' => $session->currency,
            'amount_cents' => (int) $session->amount_cents,
            'result_kind' => $session->result_kind,
            'result_payload' => $session->result_payload,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'plan' => $session->plan ? [
                'slug' => $session->plan->slug,
                'name' => $session->plan->name,
                'description' => $session->plan->description,
                'price_cents' => (int) $session->plan->price_cents,
                'currency' => $session->plan->currency,
                'billing_period' => $session->plan->billing_period,
                'trial_days' => (int) $session->plan->trial_days,
            ] : null,
            'tenant' => $session->tenant ? [
                'id' => $session->tenant->id,
                'slug' => $session->tenant->slug,
                'name' => $session->tenant->name,
            ] : null,
        ];
    }

    protected function bail(CheckoutSession $session, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return redirect()->route('checkout.show', ['session' => $session->public_id]);
    }
}
