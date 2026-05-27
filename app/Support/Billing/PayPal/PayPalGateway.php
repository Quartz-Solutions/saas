<?php

namespace App\Support\Billing\PayPal;

use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\Billing\Checkout\CheckoutResult;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Billing\Checkout\RedirectCheckout;
use App\Support\Billing\CheckoutGateway;
use App\Support\Billing\PaymentGateway;
use App\Support\Billing\SubscriptionGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * PayPal driver — Phase 3.1 scaffold.
 *
 * Implements PaymentGateway + SubscriptionGateway against the PayPal REST
 * API directly via Laravel's HTTP client (no composer SDK dependency).
 *
 * What is REAL in this scaffold:
 *   - OAuth2 client-credentials token exchange (with cache).
 *   - Webhook signature verification via /v1/notifications/verify-webhook-signature.
 *   - handleWebhook() — verify + persist event status.
 *
 * What is STUBBED (throws RuntimeException with doc links):
 *   - One-off charge / authorize / capture / refund / void / status.
 *   - Subscription create / changePlan / cancel / resume / sync.
 *
 * The stubs let the GatewayRegistry resolve 'paypal' so the rest of the
 * billing pipeline (BillingService, /webhooks/{gateway} dispatch) can be
 * wired and tested while the gateway-specific calls are implemented in
 * follow-up tickets.
 */
class PayPalGateway implements CheckoutGateway, PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'paypal';
    }

    public function displayName(): string
    {
        return 'PayPal';
    }

    // ------------------------------------------------------------------
    // CheckoutGateway
    // ------------------------------------------------------------------

    /**
     * PayPal's published settlement currencies.
     *
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        return [
            'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'SGD', 'HKD', 'NZD',
            'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'MXN', 'BRL',
            'ILS', 'PHP', 'THB', 'TWD', 'MYR',
        ];
    }

    public function supportsSubscriptions(): bool
    {
        return true;
    }

    /**
     * Create (or re-resolve) the PayPal order/subscription and return the
     * approve-URL redirect. Idempotent: if the local session already has a
     * gateway_session_id, refetch the existing PayPal resource instead of
     * duplicating it.
     */
    public function initiateCheckout(CheckoutSession $session): CheckoutResult
    {
        $plan = $session->plan;
        if ($plan === null) {
            throw new RuntimeException("Checkout session {$session->public_id} has no plan.");
        }

        $isSubscription = $session->intent === CheckoutSession::INTENT_SUBSCRIPTION;

        if ($session->gateway_session_id !== null && $session->gateway_session_id !== '') {
            $existing = $this->fetchExistingResource($session->gateway_session_id, $isSubscription);
            if ($existing !== null) {
                $approveUrl = $this->extractApproveUrl((array) ($existing['links'] ?? []));
                if ($approveUrl !== null) {
                    $result = new RedirectCheckout(
                        gatewaySessionId: (string) ($existing['id'] ?? $session->gateway_session_id),
                        url: $approveUrl,
                        expiresAt: null,
                    );
                    $this->persistResult($session, $result);

                    return $result;
                }
            }
            // Fall through and create a fresh resource if refetch failed.
        }

        if ($isSubscription) {
            $paypalPlanId = $this->syncPlanForPayPal($plan);

            $payload = [
                'plan_id' => $paypalPlanId,
                'custom_id' => $session->public_id,
            ];
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl().'/v1/billing/subscriptions', $payload);

            if (! $response->successful()) {
                throw new RuntimeException('PayPal create-subscription failed: '.$response->status().' '.$response->body());
            }

            $body = (array) $response->json();
        } else {
            $payload = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $session->public_id,
                    'amount' => [
                        'currency_code' => strtoupper((string) $session->currency),
                        'value' => $this->formatAmount((int) $session->amount_cents),
                    ],
                ]],
            ];
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl().'/v2/checkout/orders', $payload);

            if (! $response->successful()) {
                throw new RuntimeException('PayPal create-order failed: '.$response->status().' '.$response->body());
            }

            $body = (array) $response->json();
        }

        $resourceId = (string) ($body['id'] ?? '');
        $approveUrl = $this->extractApproveUrl((array) ($body['links'] ?? []));

        if ($resourceId === '' || $approveUrl === null) {
            throw new RuntimeException('PayPal response missing id or approve link.');
        }

        $result = new RedirectCheckout(
            gatewaySessionId: $resourceId,
            url: $approveUrl,
            expiresAt: null,
        );

        $this->persistResult($session, $result);

        return $result;
    }

    /**
     * Ensure a PayPal Catalog Product + Billing Plan exist for the local
     * Plan and return the PayPal Billing Plan id. Mirrors the pattern in
     * StripeGateway::syncPriceForPlan — fingerprints price/currency/interval/
     * trial, reuses an existing PayPal plan when the fingerprint matches,
     * otherwise creates a fresh one (PayPal plans are immutable on those
     * fields, so a change here mints a new plan; existing subscriptions
     * stay on the old plan until they migrate).
     */
    public function syncPlanForPayPal(Plan $plan): string
    {
        if ((int) $plan->price_cents === 0) {
            throw new RuntimeException("Plan [{$plan->slug}] is free — free plans skip checkout entirely.");
        }

        $existingIds = (array) ($plan->gateway_ids ?? []);
        $existingPlanId = (string) ($existingIds['paypal'] ?? '');
        $existingFingerprint = (string) ($existingIds['paypal_fingerprint'] ?? '');
        $fingerprint = $this->paypalFingerprint($plan);

        if ($existingPlanId !== '' && $existingFingerprint === $fingerprint) {
            return $existingPlanId;
        }

        $productId = $this->ensureProductForPlan($plan);

        $billingCycles = [];
        if ((int) $plan->trial_days > 0) {
            $billingCycles[] = [
                'frequency' => ['interval_unit' => 'DAY', 'interval_count' => (int) $plan->trial_days],
                'tenure_type' => 'TRIAL',
                'sequence' => 1,
                'total_cycles' => 1,
                'pricing_scheme' => [
                    'fixed_price' => ['value' => '0', 'currency_code' => strtoupper((string) $plan->currency)],
                ],
            ];
        }
        $billingCycles[] = [
            'frequency' => [
                'interval_unit' => $this->paypalIntervalUnit((string) $plan->billing_period),
                'interval_count' => max(1, (int) $plan->billing_interval),
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => count($billingCycles) + 1,
            'total_cycles' => 0,
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => $this->formatAmount((int) $plan->price_cents),
                    'currency_code' => strtoupper((string) $plan->currency),
                ],
            ],
        ];

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/v1/billing/plans', [
                'product_id' => $productId,
                'name' => $plan->name,
                'description' => $plan->description ?: $plan->name,
                'status' => 'ACTIVE',
                'billing_cycles' => $billingCycles,
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3,
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal create-plan failed: '.$response->status().' '.$response->body());
        }

        $planId = (string) ($response->json('id') ?? '');
        if ($planId === '') {
            throw new RuntimeException('PayPal create-plan response missing id: '.$response->body());
        }

        $plan->forceFill([
            'gateway_ids' => array_merge($existingIds, [
                'paypal' => $planId,
                'paypal_product' => $productId,
                'paypal_fingerprint' => $fingerprint,
            ]),
        ])->save();

        return $planId;
    }

    /**
     * Lazy-create (or reuse) a PayPal Catalog Product for the local Plan.
     * Stored on Plan::gateway_ids.paypal_product. Idempotent.
     */
    protected function ensureProductForPlan(Plan $plan): string
    {
        $existingIds = (array) ($plan->gateway_ids ?? []);
        $existingProductId = (string) ($existingIds['paypal_product'] ?? '');
        if ($existingProductId !== '') {
            try {
                $response = Http::withToken($this->accessToken())
                    ->acceptJson()
                    ->get($this->baseUrl().'/v1/catalogs/products/'.$existingProductId);
                if ($response->successful()) {
                    return $existingProductId;
                }
            } catch (Throwable) {
                // Fall through and recreate.
            }
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/v1/catalogs/products', [
                'name' => $plan->name,
                'description' => $plan->description ?: $plan->name,
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal create-product failed: '.$response->status().' '.$response->body());
        }

        $productId = (string) ($response->json('id') ?? '');
        if ($productId === '') {
            throw new RuntimeException('PayPal create-product response missing id: '.$response->body());
        }

        return $productId;
    }

    protected function paypalIntervalUnit(string $period): string
    {
        return match ($period) {
            'day' => 'DAY',
            'week' => 'WEEK',
            'year' => 'YEAR',
            default => 'MONTH',
        };
    }

    /**
     * Fingerprint over the PayPal-immutable plan attributes so we know when
     * to mint a new PayPal Plan instead of reusing the cached one.
     */
    protected function paypalFingerprint(Plan $plan): string
    {
        return hash('sha256', json_encode([
            (int) $plan->price_cents,
            strtoupper((string) $plan->currency),
            (string) $plan->billing_period,
            max(1, (int) $plan->billing_interval),
            (int) $plan->trial_days,
            $plan->name,
        ]));
    }

    /**
     * Re-fetch an existing PayPal order or subscription by id for idempotency.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchExistingResource(string $id, bool $isSubscription): ?array
    {
        $url = $isSubscription
            ? $this->baseUrl().'/v1/billing/subscriptions/'.$id
            : $this->baseUrl().'/v2/checkout/orders/'.$id;

        try {
            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->get($url);
            if (! $response->successful()) {
                return null;
            }

            return (array) $response->json();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    private function extractApproveUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if (($link['rel'] ?? null) === 'approve' && isset($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }

    private function persistResult(CheckoutSession $session, RedirectCheckout $result): void
    {
        $session->forceFill([
            'gateway' => 'paypal',
            'gateway_session_id' => $result->gatewaySessionId,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => $result->kind,
            'result_payload' => $result->toPayload(),
        ])->save();
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from PayPal docs, untested
        // Docs: https://developer.paypal.com/docs/api/orders/v2/

        $payload = array_merge([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => $this->formatAmount($amountCents),
                ],
            ]],
        ], $context['paypal'] ?? []);

        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson();

        if (isset($context['idempotency_key'])) {
            $request = $request->withHeaders(['PayPal-Request-Id' => (string) $context['idempotency_key']]);
        }

        $response = $request->post($this->baseUrl().'/v2/checkout/orders', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal create-order failed: '.$response->status().' '.$response->body());
        }

        $order = (array) $response->json();
        $orderId = (string) ($order['id'] ?? '');
        $approveUrl = null;
        foreach ((array) ($order['links'] ?? []) as $link) {
            if (($link['rel'] ?? null) === 'approve') {
                $approveUrl = (string) ($link['href'] ?? '');
                break;
            }
        }

        $attrs = [
            'gateway' => 'paypal',
            'gateway_payment_id' => $orderId,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'metadata' => array_merge(
                (array) ($order['metadata'] ?? []),
                ['approve_url' => $approveUrl, 'paypal_order' => $order],
            ),
        ];

        if (isset($context['idempotency_key'])) {
            $attrs['idempotency_key'] = (string) $context['idempotency_key'];
        }

        if (isset($context['tenant_id'])) {
            $attrs['tenant_id'] = $context['tenant_id'];
        }

        if (isset($context['invoice_id'])) {
            $attrs['invoice_id'] = $context['invoice_id'];
        }

        if (! isset($attrs['tenant_id'])) {
            throw new RuntimeException('PayPal charge(): tenant_id is required. Pass via $context.');
        }

        $payment = new Payment;
        $payment->forceFill($attrs)->save();

        return $payment->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from PayPal docs, untested
        // Docs: https://developer.paypal.com/docs/api/payments/v2/#captures_refund

        $metadata = (array) ($payment->metadata ?? []);
        $captureId = (string) ($metadata['capture_id'] ?? '');

        if ($captureId === '') {
            throw new RuntimeException('PayPal refund(): metadata.capture_id is required on Payment; capture must complete before refund.');
        }

        $body = [];
        if ($amountCents !== null) {
            $body['amount'] = [
                'value' => $this->formatAmount($amountCents),
                'currency_code' => strtoupper((string) $payment->currency),
            ];
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl()."/v2/payments/captures/{$captureId}/refund", $body);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal refund failed: '.$response->status().' '.$response->body());
        }

        $refund = (array) $response->json();
        $refundedDelta = $amountCents
            ?? (int) round(((float) ($refund['amount']['value'] ?? 0)) * 100)
            ?: (int) $payment->amount_cents;

        $newRefunded = (int) $payment->refunded_cents + (int) $refundedDelta;

        $payment->forceFill([
            'refunded_cents' => $newRefunded,
            'status' => $newRefunded >= (int) $payment->amount_cents ? 'refunded' : 'partially_refunded',
            'refunded_at' => now(),
            'metadata' => array_merge($metadata, [
                'last_refund' => $refund,
                'last_refund_id' => (string) ($refund['id'] ?? ''),
            ]),
        ])->save();

        return $payment->fresh();
    }

    public function void(Payment $payment): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    public function status(Payment $payment): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway — stubs
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        // SANDBOX VERIFICATION REQUIRED — built from PayPal docs, untested
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/#subscriptions_create

        $gatewayIds = (array) ($plan->gateway_ids ?? []);
        $paypalPlanId = (string) ($gatewayIds['paypal'] ?? '');

        if ($paypalPlanId === '') {
            throw new RuntimeException("Plan [{$plan->slug}] has no PayPal plan id (gateway_ids.paypal).");
        }

        $payload = array_merge([
            'plan_id' => $paypalPlanId,
            'custom_id' => (string) $tenant->id,
        ], $context['paypal'] ?? []);

        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson();

        if (isset($context['idempotency_key'])) {
            $request = $request->withHeaders(['PayPal-Request-Id' => (string) $context['idempotency_key']]);
        }

        $response = $request->post($this->baseUrl().'/v1/billing/subscriptions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal create-subscription failed: '.$response->status().' '.$response->body());
        }

        $sub = (array) $response->json();
        $subId = (string) ($sub['id'] ?? '');
        $approveUrl = null;
        foreach ((array) ($sub['links'] ?? []) as $link) {
            if (($link['rel'] ?? null) === 'approve') {
                $approveUrl = (string) ($link['href'] ?? '');
                break;
            }
        }

        $existing = Subscription::query()
            ->where('gateway', 'paypal')
            ->where('gateway_subscription_id', $subId)
            ->first();

        $attrs = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => 'paypal',
            'gateway_subscription_id' => $subId,
            'status' => 'approval_pending',
            'currency' => strtoupper((string) $plan->currency),
            'unit_amount_cents' => (int) $plan->price_cents,
            'quantity' => 1,
            'metadata' => array_merge(
                (array) ($sub['metadata'] ?? []),
                ['approve_url' => $approveUrl, 'paypal_subscription' => $sub],
            ),
        ];

        if ($existing !== null) {
            $existing->forceFill($attrs)->save();

            return $existing->fresh();
        }

        $subscription = new Subscription;
        $subscription->forceFill($attrs)->save();

        return $subscription->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        // SANDBOX VERIFICATION REQUIRED — built from PayPal docs, untested
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/#subscriptions_cancel

        $reason = (string) ($context['reason'] ?? 'Cancelled by user');

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->post(
                $this->baseUrl()."/v1/billing/subscriptions/{$subscription->gateway_subscription_id}/cancel",
                ['reason' => $reason],
            );

        // PayPal returns 204 No Content on success.
        if (! $response->successful() && $response->status() !== 204) {
            throw new RuntimeException('PayPal cancel-subscription failed: '.$response->status().' '.$response->body());
        }

        $subscription->forceFill([
            'status' => 'canceled',
            'cancel_at_period_end' => false,
            'canceled_at' => now(),
            'cancellation_reason' => $reason,
            'ends_at' => now(),
        ])->save();

        return $subscription->fresh();
    }

    public function resume(Subscription $subscription): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    // ------------------------------------------------------------------
    // Webhook handling — real
    // ------------------------------------------------------------------

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        try {
            $verified = $this->verifyWebhookSignature($request);
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'PayPal signature verification error: '.$e->getMessage(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            Log::warning('PayPal webhook verification threw', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('PayPal webhook signature verification failed', 0, $e);
        }

        if (! $verified) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'PayPal signature verification failed',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('PayPal webhook signature verification failed');
        }

        try {
            $payload = $request->json()->all();
            $eventType = (string) ($payload['event_type'] ?? '');

            $this->dispatchEvent($eventType, $payload);

            $event->forceFill([
                'event_type' => $eventType !== '' ? $eventType : $event->event_type,
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            Log::warning('PayPal webhook processing failed', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchEvent(string $eventType, array $payload): void
    {
        match (true) {
            $eventType === 'BILLING.SUBSCRIPTION.ACTIVATED',
            $eventType === 'PAYMENT.CAPTURE.COMPLETED',
            $eventType === 'CHECKOUT.ORDER.APPROVED' => $this->onCheckoutCompleted($eventType, $payload),
            default => null,
        };
    }

    /**
     * Reconcile a completion-style PayPal webhook with the local CheckoutSession.
     *
     * @param  array<string, mixed>  $payload
     */
    private function onCheckoutCompleted(string $eventType, array $payload): void
    {
        $resource = (array) ($payload['resource'] ?? []);
        $resourceId = (string) ($resource['id'] ?? '');
        if ($resourceId === '') {
            return;
        }

        // For PAYMENT.CAPTURE.COMPLETED the capture id is resource.id; the
        // owning order id lives in supplementary_data.related_ids.order_id.
        $orderOrSubId = $resourceId;
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $orderOrSubId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? $resourceId);
        }

        $session = CheckoutSession::query()
            ->where('gateway', 'paypal')
            ->where(function ($q) use ($orderOrSubId, $resourceId) {
                $q->where('gateway_session_id', $orderOrSubId);
                if ($resourceId !== $orderOrSubId) {
                    $q->orWhere('gateway_session_id', $resourceId);
                }
            })
            ->first();

        if ($session === null) {
            Log::info('PayPal webhook: no matching CheckoutSession', [
                'event_type' => $eventType,
                'resource_id' => $resourceId,
                'order_or_sub_id' => $orderOrSubId,
            ]);

            return;
        }

        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            return;
        }

        $isSubscription = $eventType === 'BILLING.SUBSCRIPTION.ACTIVATED';
        $amountValue = (float) ($resource['amount']['value']
            ?? $resource['billing_info']['last_payment']['amount']['value']
            ?? 0);
        $amountCents = (int) round($amountValue * 100);
        $currency = strtoupper((string) (
            $resource['amount']['currency_code']
            ?? $resource['billing_info']['last_payment']['amount']['currency_code']
            ?? $session->currency
        ));

        $checkout = app(CheckoutService::class);
        $checkout->complete($session, [
            'gateway_subscription_id' => $isSubscription ? $resourceId : null,
            'gateway_invoice_id' => null,
            'gateway_payment_id' => $eventType === 'PAYMENT.CAPTURE.COMPLETED' ? $resourceId : null,
            'amount_paid_cents' => $amountCents > 0 ? $amountCents : (int) $session->amount_cents,
            'paid_amount_cents' => $amountCents > 0 ? $amountCents : (int) $session->amount_cents,
            'currency' => $currency,
        ]);
    }

    /**
     * Verify a PayPal webhook by asking PayPal's verify endpoint.
     *
     * This is the canonical "easy" approach per PayPal's docs — we hand
     * the headers + raw body + our configured webhook_id back to PayPal
     * and they tell us if it's authentic.
     *
     * Docs: https://developer.paypal.com/api/rest/webhooks/rest/#link-verifywebhooksignature
     */
    protected function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = (string) config('billing.gateways.paypal.webhook_id', '');

        if ($webhookId === '') {
            throw new RuntimeException('PayPal webhook_id is not configured (billing.gateways.paypal.webhook_id).');
        }

        // PayPal sends the body as JSON — re-decode the raw body so we
        // pass the *exact* object PayPal hashed, not a re-serialised one.
        $rawBody = $request->getContent();
        $webhookEvent = json_decode($rawBody, true);

        if (! is_array($webhookEvent)) {
            return false;
        }

        $payload = [
            'auth_algo' => (string) $request->header('paypal-auth-algo', ''),
            'cert_url' => (string) $request->header('paypal-cert-url', ''),
            'transmission_id' => (string) $request->header('paypal-transmission-id', ''),
            'transmission_sig' => (string) $request->header('paypal-transmission-sig', ''),
            'transmission_time' => (string) $request->header('paypal-transmission-time', ''),
            'webhook_id' => $webhookId,
            'webhook_event' => $webhookEvent,
        ];

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', $payload);

        if (! $response->successful()) {
            Log::warning('PayPal verify-webhook-signature returned non-2xx', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return (string) $response->json('verification_status') === 'SUCCESS';
    }

    // ------------------------------------------------------------------
    // OAuth2 token + base URL — real
    // ------------------------------------------------------------------

    /**
     * Fetch (or reuse) a PayPal OAuth2 client_credentials access token.
     *
     * Tokens are valid for ~9 hours (`expires_in` ≈ 31668s). We cache for
     * `expires_in - 60` to give a one-minute safety margin against clock
     * skew between this host and PayPal.
     *
     * Docs: https://developer.paypal.com/api/rest/authentication/
     */
    protected function accessToken(): string
    {
        $mode = $this->mode();
        $cacheKey = "paypal:access_token:{$mode}";

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) config('billing.gateways.paypal.client_id', '');
        $clientSecret = (string) config('billing.gateways.paypal.client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal client_id / client_secret are not configured.');
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->acceptJson()
            ->asForm()
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal OAuth2 token request failed: '.$response->status().' '.$response->body());
        }

        $token = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 0);

        if ($token === '') {
            throw new RuntimeException('PayPal OAuth2 response did not include an access_token.');
        }

        $ttl = max(60, $expiresIn - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Resolve the PayPal REST API base URL for the configured mode.
     */
    protected function baseUrl(): string
    {
        return $this->mode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function mode(): string
    {
        $mode = (string) config('billing.gateways.paypal.mode', 'sandbox');

        return $mode === 'live' ? 'live' : 'sandbox';
    }

    /**
     * Convert integer minor units (cents) to PayPal's decimal string format
     * (e.g. 1234 -> "12.34"). PayPal's REST API expects amounts as strings
     * with two decimal places for most currencies.
     */
    private function formatAmount(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }
}
