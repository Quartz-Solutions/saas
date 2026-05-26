<?php

namespace App\Support\Billing\Aps;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\Billing\PaymentGateway;
use App\Support\Billing\SubscriptionGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Amazon Payment Services (Payfort) driver — Phase 3.3 scaffold.
 *
 * APS is the rebrand of Payfort and is the dominant card acquirer across
 * MENA. It is the right pick when a tenant needs broad regional method
 * coverage in a single integration:
 *
 *   - Cards: Visa / Mastercard / Amex
 *   - Mada (Saudi Arabia domestic debit network)
 *   - Meeza (Egypt domestic debit network)
 *   - KNET (Kuwait domestic network)
 *   - NAPS (Qatar domestic network)
 *   - valU (BNPL, Egypt)
 *   - Apple Pay
 *
 * Quirks worth keeping in mind:
 *  - `merchant_reference` MUST be unique per transaction — APS uses it as
 *    the idempotency key. Reusing one returns the original response.
 *  - `language` is required on every request ('en' or 'ar').
 *  - APS has no native subscription object. Recurring is implemented
 *    merchant-side: an initial CIT (customer-initiated) charge captures a
 *    `token_name`, then subsequent MIT charges use command=RECURRING with
 *    that same token. We persist token_name on GatewayCustomer.
 *  - 3-decimal currencies (KWD, OMR, BHD) use minor units × 1000.
 *    Everything else uses × 100. Our boilerplate stores integer cents
 *    (assumed × 100) so we re-multiply by 10 for the 3-dp set.
 *  - Webhook deliveries retry up to 10× at 10s intervals until the
 *    endpoint replies 2xx. Verifier MUST be deterministic on the payload.
 *
 * Signature scheme is NOT HMAC. It is:
 *   phrase + sortedConcat(key=val) + phrase, hashed with sha256 or sha512.
 *
 * HTTP is done through Illuminate\Support\Facades\Http — there is no
 * Composer SDK we want to take a dep on (payfort/payfort-php-sdk and
 * amazonpaymentservices/aps-php-sdk are both unmaintained), so we sign
 * and verify by hand.
 */
class ApsGateway implements PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'aps';
    }

    public function displayName(): string
    {
        return 'Amazon Payment Services';
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs
    // ------------------------------------------------------------------

    /**
     * Build an APS Hosted Checkout (Redirection) form bundle.
     *
     * SANDBOX VERIFICATION REQUIRED — built from APS docs, untested.
     *
     * APS hosted checkout is NOT a JSON API — there is no server-to-server
     * call here. We construct a signed form parameter bag; the front-end
     * renders a self-submitting <form method="POST" action={form_action}>
     * containing every key in metadata.form_params (signature included).
     * The customer's browser POSTs to APS, completes payment, and APS
     * redirects back to context.return_url with a signed response that the
     * webhook / return handler will verify via verifyResponse().
     *
     * $context keys honoured:
     *   - tenant_id (required) — bound to the new Payment row
     *   - invoice_id (optional)
     *   - idempotency_key (optional) — also used as merchant_reference
     *   - language (optional, 'en'|'ar', default 'en')
     *   - return_url (optional) — APS redirects the customer here
     *   - customer.email (optional) — defaults to na@example.com
     *
     * @param  array<string, mixed>  $context
     *
     * @see https://paymentservices.amazon.com/docs/getting-started
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from APS docs, untested.
        $merchantReference = (string) ($context['idempotency_key'] ?? Str::uuid());

        $customer = (array) ($context['customer'] ?? []);

        $params = [
            'service_command' => 'PURCHASE',
            'access_code' => (string) config('billing.gateways.aps.access_code'),
            'merchant_identifier' => (string) config('billing.gateways.aps.merchant_identifier'),
            'merchant_reference' => $merchantReference,
            'amount' => (string) $this->toMinorUnits($amountCents, $currency),
            'currency' => strtoupper($currency),
            'language' => (string) ($context['language'] ?? 'en'),
            'customer_email' => (string) ($customer['email'] ?? 'na@example.com'),
            'return_url' => (string) ($context['return_url'] ?? ''),
        ];

        $params = $this->withSignature($params);

        $formAction = $this->baseUrl().'/FortAPI/paymentPage';

        $attrs = [
            'gateway' => 'aps',
            'gateway_payment_id' => $merchantReference,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'metadata' => [
                'form_action' => $formAction,
                'form_params' => $params,
            ],
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

        $existing = Payment::query()
            ->where('gateway', 'aps')
            ->where('gateway_payment_id', $merchantReference)
            ->first();

        if ($existing !== null) {
            $existing->forceFill($attrs)->save();

            return $existing->fresh();
        }

        if (! isset($attrs['tenant_id'])) {
            throw new RuntimeException('ApsGateway::charge: tenant_id is required for new payments. Pass via $context.');
        }

        $payment = new Payment;
        $payment->forceFill($attrs)->save();

        return $payment->fresh();
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('APS: charge/refund flow not yet wired — Phase 3.3. Implement via Hosted Checkout (form POST to baseUrl + /FortAPI/paymentPage). Recurring uses RECURRING command + saved token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('APS: charge/refund flow not yet wired — Phase 3.3. Implement via Hosted Checkout (form POST to baseUrl + /FortAPI/paymentPage). Recurring uses RECURRING command + saved token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    /**
     * Refund an APS purchase (full or partial).
     *
     * SANDBOX VERIFICATION REQUIRED — built from APS docs, untested.
     *
     * Unlike charge(), refunds ARE server-to-server JSON. We POST a signed
     * payload to {baseUrl}/FortAPI/paymentApi with command=REFUND. The
     * webhook handler is responsible for populating metadata.fort_id off
     * the original purchase notification — without it APS cannot locate
     * the transaction.
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from APS docs, untested.
        $metadata = (array) ($payment->metadata ?? []);
        $fortId = (string) ($metadata['fort_id'] ?? '');

        if ($fortId === '') {
            throw new RuntimeException('ApsGateway::refund: no fort_id on Payment row. Webhook must populate metadata.fort_id before refund.');
        }

        $refundAmount = $amountCents ?? (int) $payment->amount_cents;
        $currency = strtoupper((string) $payment->currency);

        $params = [
            'command' => 'REFUND',
            'access_code' => (string) config('billing.gateways.aps.access_code'),
            'merchant_identifier' => (string) config('billing.gateways.aps.merchant_identifier'),
            'merchant_reference' => (string) $payment->gateway_payment_id,
            'fort_id' => $fortId,
            'amount' => (string) $this->toMinorUnits($refundAmount, $currency),
            'currency' => $currency,
            'language' => (string) ($metadata['language'] ?? 'en'),
        ];

        $params = $this->withSignature($params);

        $response = $this->http()->post('/FortAPI/paymentApi', $params);

        if (! $response->successful()) {
            throw new RuntimeException('APS refund HTTP failure: '.$response->status().' '.$response->body());
        }

        $body = (array) $response->json();
        $responseCode = (string) ($body['response_code'] ?? '');
        // APS success codes for REFUND are in the 06xxx family (e.g. 06000).
        $isSuccess = str_starts_with($responseCode, '06') && (str_ends_with($responseCode, '000') || str_ends_with($responseCode, '064'));

        return DB::transaction(function () use ($payment, $refundAmount, $body, $isSuccess) {
            $newRefunded = (int) $payment->refunded_cents + ($isSuccess ? $refundAmount : 0);

            $payment->forceFill([
                'refunded_cents' => $newRefunded,
                'status' => $isSuccess
                    ? ($newRefunded >= (int) $payment->amount_cents ? 'refunded' : 'partially_refunded')
                    : $payment->status,
                'refunded_at' => $isSuccess ? now() : $payment->refunded_at,
                'metadata' => array_merge((array) ($payment->metadata ?? []), [
                    'refund_response' => $body,
                ]),
            ])->save();

            return $payment->fresh();
        });
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException('APS: charge/refund flow not yet wired — Phase 3.3. Implement via Hosted Checkout (form POST to baseUrl + /FortAPI/paymentPage). Recurring uses RECURRING command + saved token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('APS: charge/refund flow not yet wired — Phase 3.3. Implement via Hosted Checkout (form POST to baseUrl + /FortAPI/paymentPage). Recurring uses RECURRING command + saved token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    // ------------------------------------------------------------------
    // Webhook handler — REAL
    // ------------------------------------------------------------------

    /**
     * APS direct-transaction-feedback / notification callback.
     *
     * The payload is form-encoded (application/x-www-form-urlencoded) and
     * carries a `signature` field hashed exactly like the request signature
     * but using the SHA RESPONSE PHRASE. We verify the full payload,
     * including everything except `signature` itself.
     *
     * APS retries up to 10× at ~10s intervals until the endpoint replies
     * 2xx — the wrapping route must always 200 on processed events.
     */
    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $payload = $request->all();

        if (! $this->verifyResponse($payload)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'APS signature verification failed',
            ])->save();

            throw new RuntimeException('APS signature verification failed');
        }

        $event->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway — stubs
    // ------------------------------------------------------------------

    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        throw new RuntimeException('APS: subscriptions not yet wired — Phase 3.3. APS has no native subscription object; implement merchant-side as recurring MIT charges with stored token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        throw new RuntimeException('APS: subscriptions not yet wired — Phase 3.3. APS has no native subscription object; implement merchant-side as recurring MIT charges with stored token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        throw new RuntimeException('APS: subscriptions not yet wired — Phase 3.3. APS has no native subscription object; implement merchant-side as recurring MIT charges with stored token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    public function resume(Subscription $subscription): Subscription
    {
        throw new RuntimeException('APS: subscriptions not yet wired — Phase 3.3. APS has no native subscription object; implement merchant-side as recurring MIT charges with stored token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        throw new RuntimeException('APS: subscriptions not yet wired — Phase 3.3. APS has no native subscription object; implement merchant-side as recurring MIT charges with stored token_name. Docs: https://paymentservices.amazon.com/docs/getting-started');
    }

    // ------------------------------------------------------------------
    // Signing + verification — REAL
    // ------------------------------------------------------------------

    /**
     * Build an APS request signature.
     *
     * Construction (per APS Integration Guide, "Signature Calculation"):
     *   1. Take every request parameter EXCEPT `signature` itself.
     *   2. Sort the keys alphabetically (raw ASCII on the parameter name).
     *   3. Concatenate as `key=value` with NO separator between pairs.
     *   4. Wrap with the SHA REQUEST PHRASE on both ends:
     *        PHRASE + key1=val1key2=val2...keyN=valN + PHRASE
     *   5. Hash with the configured sha_type (sha256 or sha512), hex-encoded
     *      lowercase.
     *
     * Notes:
     *  - Values are NOT URL-encoded before hashing.
     *  - APS guidance on empty values is inconsistent across docs. We take
     *    the safer reading: only include keys whose values are non-null
     *    AND non-empty-string. (Both omitting empties and including them
     *    are seen in the wild; merchants who see mismatch failures should
     *    flip this rule, but omission matches the current APS dashboard
     *    Signature Calculator.)
     *  - Sort is raw ASCII (PHP default ksort), not locale-aware.
     *
     * @param  array<string, mixed>  $params  request params, already excluding `signature`
     */
    protected function signRequest(array $params): string
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($key === 'signature') {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[(string) $key] = $this->stringifySignatureValue($value);
        }

        ksort($filtered, SORT_STRING);

        $concat = '';
        foreach ($filtered as $key => $value) {
            $concat .= $key.'='.$value;
        }

        $phrase = (string) config('billing.gateways.aps.sha_request_phrase');
        $algo = $this->shaAlgo();

        return hash($algo, $phrase.$concat.$phrase);
    }

    /**
     * Return the given params bag with a `signature` key appended. Useful
     * for building the hosted-checkout form fields.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function withSignature(array $params): array
    {
        $params['signature'] = $this->signRequest($params);

        return $params;
    }

    /**
     * Verify an APS response / notification signature.
     *
     * Same algorithm as request signing, but uses the SHA RESPONSE PHRASE
     * and reads the `signature` field off the incoming payload.
     *
     * @param  array<string, mixed>  $params
     */
    protected function verifyResponse(array $params): bool
    {
        $provided = (string) ($params['signature'] ?? '');

        if ($provided === '') {
            return false;
        }

        $filtered = [];
        foreach ($params as $key => $value) {
            if ($key === 'signature') {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[(string) $key] = $this->stringifySignatureValue($value);
        }

        ksort($filtered, SORT_STRING);

        $concat = '';
        foreach ($filtered as $key => $value) {
            $concat .= $key.'='.$value;
        }

        $phrase = (string) config('billing.gateways.aps.sha_response_phrase');
        $algo = $this->shaAlgo();

        $expected = hash($algo, $phrase.$concat.$phrase);

        return hash_equals($expected, strtolower($provided));
    }

    /**
     * Stringify a signature input value. APS treats numbers and scalars as
     * their string form (e.g. amount 10000 → "10000"). Arrays and objects
     * never appear in APS request bodies, but we guard against them anyway.
     */
    protected function stringifySignatureValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        // Defensive — APS request bodies are flat scalar maps.
        return (string) json_encode($value);
    }

    /**
     * Configured SHA algorithm, normalised. Defaults to sha256.
     */
    protected function shaAlgo(): string
    {
        $configured = strtolower((string) config('billing.gateways.aps.sha_type', 'sha256'));

        return match ($configured) {
            'sha512' => 'sha512',
            default => 'sha256',
        };
    }

    // ------------------------------------------------------------------
    // Money formatting — REAL
    // ------------------------------------------------------------------

    /**
     * Translate the boilerplate's integer-cents (× 100) convention into the
     * minor-unit value APS expects on the wire.
     *
     * APS uses ISO 4217 minor units:
     *   - 2-decimal currencies (USD, EUR, EGP, SAR, AED, QAR, JOD…)  → × 100
     *   - 3-decimal currencies (KWD, OMR, BHD, TND, IQD, LYD, JOD*)  → × 1000
     *
     * Our cents column is always × 100. For 3-decimal currencies we
     * therefore re-scale by ×10 to land on × 1000.
     *
     * Note: JOD is technically 3-decimal in ISO 4217 but APS historically
     * processes it as 2-decimal. Add it to the 3-decimal set only if your
     * acquirer confirms 3-dp pricing.
     */
    protected function toMinorUnits(int $cents, string $currency): int
    {
        $threeDecimal = ['KWD', 'OMR', 'BHD'];

        if (in_array(strtoupper($currency), $threeDecimal, true)) {
            return $cents * 10;
        }

        return $cents;
    }

    // ------------------------------------------------------------------
    // Environment / base URL — REAL
    // ------------------------------------------------------------------

    /**
     * Base URL for APS hosted checkout + REST endpoints, switched by env.
     *
     *  - sandbox    → https://sbcheckout.payfort.com
     *  - production → https://checkout.payfort.com
     *
     * Hosted Checkout form action is baseUrl + /FortAPI/paymentPage.
     * Server-to-server (notification, refund, capture) hits
     * baseUrl + /FortAPI/paymentApi.
     */
    protected function baseUrl(): string
    {
        $env = (string) config('billing.gateways.aps.environment', 'sandbox');

        return match ($env) {
            'production' => 'https://checkout.payfort.com',
            default => 'https://sbcheckout.payfort.com',
        };
    }

    /**
     * Pre-configured Http client pointed at the right APS host. Use this
     * once the real charge/refund flows are wired in Phase 3.3.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson();
    }
}
