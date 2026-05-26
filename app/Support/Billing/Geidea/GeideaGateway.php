<?php

namespace App\Support\Billing\Geidea;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\Billing\PaymentGateway;
use App\Support\Billing\SubscriptionGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Geidea driver — Phase 3.2 scaffold.
 *
 * Geidea is an Egypt/KSA/UAE acquirer that ships both a hosted Checkout / HPP
 * flow (POST /payment-intent/api/v2/direct/session) and a Direct API. It also
 * exposes /create-session-subscription for recurring billing — so this driver
 * implements BOTH PaymentGateway AND SubscriptionGateway.
 *
 * Notable capabilities (for the Phase 3.2 wiring task):
 *   - Apple Pay via a dedicated endpoint
 *   - Google Pay
 *   - Card tokenization / saved cards
 *   - BNPL via ValU, Souhoola, Tamara, Tabby
 *
 * No PHP SDK is published by Geidea — all calls go through Laravel's Http
 * facade with Basic auth (public_key as username, api_password as password).
 *
 * Real implementations in this scaffold:
 *   - baseUrl() on environment (sandbox vs production)
 *   - authPair() — Basic-auth credential tuple
 *   - verifySignature() — HMAC-SHA256 with API password as key, base64 output
 *   - handleWebhook() driven by verifySignature()
 *
 * Charge / refund / subscription flows are stubbed and throw until Phase 3.2.
 */
class GeideaGateway implements PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'geidea';
    }

    public function displayName(): string
    {
        return 'Geidea';
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs until Phase 3.2
    // ------------------------------------------------------------------

    /**
     * Create a Geidea Checkout / Hosted Payment Page session.
     *
     * Geidea expects the amount as a DECIMAL (e.g. 50.00 for 5000 cents) — we
     * divide $amountCents by 100 and cast to float in the JSON body. Response
     * shape (per docs): { session: { id, redirectUrl } }.
     *
     * @param  array<string, mixed>  $context
     *
     * // SANDBOX VERIFICATION REQUIRED — built from Geidea docs, untested
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        $merchantRef = (string) ($context['idempotency_key'] ?? Str::uuid());

        $body = [
            'amount' => $amountCents / 100,
            'currency' => strtoupper($currency),
            'merchantReferenceId' => $merchantRef,
            'callbackUrl' => $context['callback_url'] ?? '',
            'returnUrl' => $context['return_url'] ?? '',
            'language' => $context['language'] ?? 'en',
            'customer' => $context['customer'] ?? null,
        ];

        $response = $this->http()
            ->post('/payment-intent/api/v2/direct/session', $body)
            ->throw()
            ->json();

        $sessionId = (string) ($response['session']['id'] ?? '');
        $redirectUrl = (string) ($response['session']['redirectUrl'] ?? '');

        if ($sessionId === '') {
            throw new RuntimeException('Geidea: charge response missing session.id');
        }

        return Payment::create([
            'tenant_id' => $context['tenant_id'] ?? null,
            'invoice_id' => $context['invoice_id'] ?? null,
            'gateway' => $this->id(),
            'gateway_payment_id' => $sessionId,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'idempotency_key' => $merchantRef,
            'metadata' => [
                'redirect_url' => $redirectUrl,
                'merchant_reference_id' => $merchantRef,
                'raw_response' => $response,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException($this->paymentStubMessage());
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException($this->paymentStubMessage());
    }

    /**
     * Refund a Geidea order (full or partial).
     *
     * Geidea identifies the source by `orderId` — we prefer
     * $payment->metadata.order_id (populated from the success callback) and
     * fall back to gateway_payment_id (the original session id) when absent.
     *
     * // SANDBOX VERIFICATION REQUIRED — built from Geidea docs, untested
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        $orderId = (string) ($payment->metadata['order_id'] ?? $payment->gateway_payment_id ?? '');
        if ($orderId === '') {
            throw new RuntimeException('Geidea: cannot refund — missing orderId on Payment#'.$payment->id);
        }

        $refundCents = $amountCents ?? ($payment->amount_cents - (int) ($payment->refunded_cents ?? 0));

        $body = [
            'orderId' => $orderId,
            'amount' => $refundCents / 100,
            'currency' => strtoupper((string) $payment->currency),
        ];

        $response = $this->http()
            ->post('/payment-intent/api/v1/direct/refund', $body)
            ->throw()
            ->json();

        $payment->forceFill([
            'refunded_cents' => (int) ($payment->refunded_cents ?? 0) + $refundCents,
            'refunded_at' => now(),
            'status' => ($refundCents >= ($payment->amount_cents - (int) ($payment->refunded_cents ?? 0)))
                ? 'refunded'
                : 'partially_refunded',
            'metadata' => array_merge((array) ($payment->metadata ?? []), [
                'last_refund_response' => $response,
            ]),
        ])->save();

        return $payment->fresh();
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException($this->paymentStubMessage());
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException($this->paymentStubMessage());
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway — stubs until Phase 3.2
    // ------------------------------------------------------------------

    /**
     * Create a Geidea recurring-subscription session.
     *
     * Geidea's recurring endpoint mirrors the one-off session shape with an
     * extra `recurring` config block (interval + count). The plan's
     * billing_period maps to Geidea's recurring.frequency (daily/weekly/
     * monthly/yearly). When trial_days is set we forward it as
     * recurring.startDate (today + trial_days, ISO-8601).
     *
     * @param  array<string, mixed>  $context
     *
     * // SANDBOX VERIFICATION REQUIRED — built from Geidea docs, untested
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        $merchantRef = (string) ($context['idempotency_key'] ?? Str::uuid());

        $frequency = match ((string) $plan->billing_period) {
            'day', 'daily' => 'daily',
            'week', 'weekly' => 'weekly',
            'year', 'yearly' => 'yearly',
            default => 'monthly',
        };

        $recurring = [
            'frequency' => $frequency,
            'interval' => (int) ($plan->billing_interval ?? 1),
        ];

        if ((int) ($plan->trial_days ?? 0) > 0) {
            $recurring['startDate'] = now()->addDays((int) $plan->trial_days)->toIso8601String();
        }

        $body = [
            'amount' => ((int) $plan->price_cents) / 100,
            'currency' => strtoupper((string) $plan->currency),
            'merchantReferenceId' => $merchantRef,
            'callbackUrl' => $context['callback_url'] ?? '',
            'returnUrl' => $context['return_url'] ?? '',
            'language' => $context['language'] ?? 'en',
            'customer' => $context['customer'] ?? null,
            'recurring' => $recurring,
        ];

        $response = $this->http()
            ->post('/payment-intent/api/v2/direct/session/subscription', $body)
            ->throw()
            ->json();

        $sessionId = (string) ($response['session']['id'] ?? '');
        $redirectUrl = (string) ($response['session']['redirectUrl'] ?? '');

        if ($sessionId === '') {
            throw new RuntimeException('Geidea: createSubscription response missing session.id');
        }

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => $this->id(),
            'gateway_subscription_id' => $sessionId,
            'status' => 'pending',
            'currency' => strtoupper((string) $plan->currency),
            'unit_amount_cents' => (int) $plan->price_cents,
            'quantity' => 1,
            'trial_ends_at' => (int) ($plan->trial_days ?? 0) > 0
                ? now()->addDays((int) $plan->trial_days)
                : null,
            'metadata' => [
                'redirect_url' => $redirectUrl,
                'merchant_reference_id' => $merchantRef,
                'raw_response' => $response,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        throw new RuntimeException($this->subscriptionStubMessage());
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        throw new RuntimeException($this->subscriptionStubMessage());
    }

    public function resume(Subscription $subscription): Subscription
    {
        throw new RuntimeException($this->subscriptionStubMessage());
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        throw new RuntimeException($this->subscriptionStubMessage());
    }

    // ------------------------------------------------------------------
    // Webhook handling
    // ------------------------------------------------------------------

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $payload = $request->json()->all();

        if (! $this->verifySignature($payload)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'Geidea signature verification failed',
            ])->save();

            throw new RuntimeException('Geidea signature verification failed');
        }

        $event->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    /**
     * Public helper so the webhook controller can verify ahead of persisting
     * the WebhookEvent row if it prefers. Delegates to the protected impl.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        return $this->verifySignature($payload);
    }

    // ------------------------------------------------------------------
    // Signature verification
    // ------------------------------------------------------------------

    /**
     * Verify a Geidea response/callback signature.
     *
     * UNUSUAL: Geidea delivers the signature INSIDE the JSON payload (the
     * `signature` field), NOT as a dedicated HTTP header — so this method
     * takes the parsed payload array rather than the Request object.
     *
     * Algorithm:
     *   - HMAC-SHA256
     *   - HMAC key is the API PASSWORD (NOT the public_key — this is the
     *     opposite of most gateways)
     *   - Output is BASE64-encoded (NOT hex)
     *
     * The signed string is the concatenation, in this exact fixed order
     * (do NOT sort or rearrange):
     *
     *   MerchantPublicKey + OrderAmount + OrderCurrency + OrderId
     *     + Status + MerchantReferenceId + timeStamp
     *
     * NOTE: This construction is the one Geidea documents for synchronous
     * callback RESPONSES. Async webhook callbacks may share the scheme but
     * the docs aren't fully explicit; if the signature ever mismatches in
     * production, double-check the field set against the live payload and
     * adjust the order here.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function verifySignature(array $payload): bool
    {
        $apiPassword = (string) config('billing.gateways.geidea.api_password');
        if ($apiPassword === '') {
            return false;
        }

        $provided = $this->extract($payload, ['signature', 'Signature']);
        if (! is_string($provided) || $provided === '') {
            return false;
        }

        $publicKey = (string) ($this->extract($payload, [
            'merchantPublicKey',
            'MerchantPublicKey',
            'publicKey',
        ]) ?? config('billing.gateways.geidea.public_key'));

        $amount = $this->stringify($this->extract($payload, [
            'orderAmount',
            'OrderAmount',
            'order.amount',
            'amount',
        ]));

        $currency = $this->stringify($this->extract($payload, [
            'orderCurrency',
            'OrderCurrency',
            'order.currency',
            'currency',
        ]));

        $orderId = $this->stringify($this->extract($payload, [
            'orderId',
            'OrderId',
            'order.orderId',
            'order.id',
        ]));

        $status = $this->stringify($this->extract($payload, [
            'status',
            'Status',
            'order.status',
        ]));

        $merchantRef = $this->stringify($this->extract($payload, [
            'merchantReferenceId',
            'MerchantReferenceId',
            'order.merchantReferenceId',
        ]));

        $timestamp = $this->stringify($this->extract($payload, [
            'timeStamp',
            'timestamp',
            'TimeStamp',
            'order.timeStamp',
        ]));

        // Fixed concat order: publicKey + amount + currency + orderId
        //                   + status + merchantRef + timestamp
        $signedString = $publicKey.$amount.$currency.$orderId.$status.$merchantRef.$timestamp;

        $computed = base64_encode(hash_hmac('sha256', $signedString, $apiPassword, true));

        return hash_equals($computed, $provided);
    }

    // ------------------------------------------------------------------
    // HTTP helpers
    // ------------------------------------------------------------------

    /**
     * Basic-auth credentials tuple for Geidea API calls.
     *
     *   Http::withBasicAuth(...$this->authPair())->post($this->baseUrl().'/...');
     *
     * The public_key is the username; the api_password is the password.
     * The api_password is ALSO used as the HMAC key in verifySignature().
     *
     * @return array{0: string, 1: string}
     */
    protected function authPair(): array
    {
        return [
            (string) config('billing.gateways.geidea.public_key'),
            (string) config('billing.gateways.geidea.api_password'),
        ];
    }

    /**
     * Pick the Geidea API host based on environment.
     *
     *   sandbox    → https://api.merchant.staging.geidea.net
     *   production → https://api.merchant.geidea.net
     */
    protected function baseUrl(): string
    {
        $environment = (string) config('billing.gateways.geidea.environment', 'sandbox');

        return match ($environment) {
            'sandbox' => 'https://api.merchant.staging.geidea.net',
            'production' => 'https://api.merchant.geidea.net',
            default => throw new RuntimeException("Geidea: unsupported environment [{$environment}]. Supported: sandbox, production."),
        };
    }

    /**
     * Returns the Http client preconfigured with Basic auth + JSON. Kept here
     * so the Phase 3.2 charge/refund/subscription methods can build on it
     * without re-wiring the auth tuple in every call site.
     */
    protected function http(): PendingRequest
    {
        return Http::withBasicAuth(...$this->authPair())
            ->acceptJson()
            ->asJson()
            ->baseUrl($this->baseUrl());
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Try a list of keys (dot-paths allowed) against the payload and return
     * the first non-null match. Geidea is inconsistent about casing between
     * checkout-session, hosted callback, and webhook payloads — supplying a
     * few candidate keys is cheaper than maintaining per-event mapping.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    protected function extract(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $this->dotGet($payload, $key);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Read a dot-path from a nested array. Returns null when any segment is
     * missing rather than throwing.
     *
     * @param  array<string, mixed>  $data
     */
    protected function dotGet(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $cursor = $data;

        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Coerce a scalar payload value into the string form Geidea signs.
     * Nulls become '', booleans become 'true'/'false', everything else is
     * cast straight to string. Amounts arrive as numerics (e.g. 50.00) and
     * MUST be signed verbatim — do not reformat.
     */
    protected function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return '';
        }

        return (string) $value;
    }

    private function paymentStubMessage(): string
    {
        return 'Geidea: charge/refund flow not yet wired — Phase 3.2. Implement via /payment-intent/api/v2/direct/session (Checkout / HPP) or Direct API. Docs: https://docs.geidea.net/docs/overview';
    }

    private function subscriptionStubMessage(): string
    {
        return 'Geidea: subscription flow not yet wired — Phase 3.2. Implement via /create-session-subscription. Docs: https://docs.geidea.net/';
    }
}
