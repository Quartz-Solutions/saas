<?php

namespace App\Support\Billing\Ipay88;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Support\Billing\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * iPay88 driver — Phase 3.4 scaffold.
 *
 * Implements PaymentGateway ONLY. iPay88 has no native subscription object;
 * recurring use cases are served by Auto-Debit (tokenized cards) flows in
 * Malaysia. When/if Auto-Debit lands here, this class can additionally
 * implement SubscriptionGateway — until then, it stays single-seam.
 *
 * Notes worth keeping near the code:
 *   - Per-country amount format requirements vary, but the universal rule
 *     for signing is: format the amount with TWO decimals (e.g. "10.50",
 *     "1,000.00"), then strip every "." and "," before concatenation.
 *   - BackendURL (server-to-server callback) MUST receive a plain-text
 *     response body of EXACTLY "RECEIVEOK" — anything else makes iPay88
 *     retry the callback. The controller that wraps handleWebhook() is
 *     responsible for emitting that string; we surface the expected
 *     value via the WebhookEvent's metadata so the controller knows.
 *   - Country-specific endpoint hostnames here are confirmed from
 *     third-party integrators (PortOne, community SDKs); the official
 *     iPay88 dev portal returned 502 during research. Verify against
 *     the country-specific merchant onboarding PDF before going live.
 *   - Auto-Debit (Malaysia) supports tokenized recurring; documented at
 *     https://www.ipay88.com/developer/ — out of scope for this scaffold.
 *
 * Signature algorithm (per iPay88 2025-01-31 update):
 *   - HMAC-SHA512 is the current standard (base64-encoded)
 *   - SHA-256 is the deprecated fallback (uppercase hex). Because the
 *     MerchantKey is part of the signed concatenation, SHA-256 mode is
 *     effectively a keyed hash even without HMAC.
 */
class Ipay88Gateway implements PaymentGateway
{
    public function __construct(
        protected readonly string $merchantCode = '',
        protected readonly string $merchantKey = '',
        protected readonly string $country = 'MY',
        protected readonly string $environment = 'sandbox',
        protected readonly string $signatureType = 'HMACSHA512',
    ) {}

    public function id(): string
    {
        return 'ipay88';
    }

    public function displayName(): string
    {
        return 'iPay88';
    }

    // ------------------------------------------------------------------
    // Configuration helpers
    // ------------------------------------------------------------------

    protected function merchantCode(): string
    {
        return (string) (config('billing.gateways.ipay88.merchant_code') ?: $this->merchantCode);
    }

    protected function merchantKey(): string
    {
        return (string) (config('billing.gateways.ipay88.merchant_key') ?: $this->merchantKey);
    }

    protected function country(): string
    {
        return strtoupper((string) (config('billing.gateways.ipay88.country') ?: $this->country));
    }

    protected function environment(): string
    {
        return strtolower((string) (config('billing.gateways.ipay88.environment') ?: $this->environment));
    }

    /**
     * Returns the configured signature algorithm — 'HMACSHA512' (current
     * standard) or 'SHA256' (deprecated fallback, kept for legacy
     * merchant accounts).
     */
    protected function signatureType(): string
    {
        $configured = strtoupper((string) (config('billing.gateways.ipay88.signature_type') ?: $this->signatureType));

        return $configured === 'SHA256' ? 'SHA256' : 'HMACSHA512';
    }

    /**
     * Country + environment-aware API base URL.
     *
     * Production hosts confirmed from third-party integrators (PortOne,
     * community SDKs). Sandbox replaces the leading `payment.` with
     * `sandbox.`. Verify against the merchant onboarding PDF before
     * going live — iPay88 occasionally rotates regional subdomains.
     */
    protected function baseUrl(): string
    {
        $host = match ($this->country()) {
            'MY' => 'payment.ipay88.com.my',
            'SG' => 'payment.ipay88.com.sg',
            'ID' => 'payment.ipay88.co.id',
            'PH' => 'payment.ipay88.com.ph',
            'TH' => 'payment.ipay88.co.th',
            'VN' => 'payment.ipay88.vn',
            default => 'payment.ipay88.com.my',
        };

        if ($this->environment() !== 'production') {
            $host = preg_replace('/^payment\./', 'sandbox.', $host);
        }

        return 'https://'.$host;
    }

    // ------------------------------------------------------------------
    // Signature helpers
    // ------------------------------------------------------------------

    /**
     * Normalize an amount for inclusion in the signed string.
     *
     * iPay88 requires the amount to be formatted with two decimals
     * (e.g. "10.50", "1,000.00") on the wire, but the signed
     * representation has every "." and "," stripped out.
     *
     *   "10.50"     → "1050"
     *   "1,000.00"  → "100000"
     *   10.5        → "1050"   (cast + format-then-strip)
     */
    protected function stripAmount(string|float $amount): string
    {
        if (is_float($amount) || is_int($amount)) {
            $amount = number_format((float) $amount, 2, '.', '');
        }

        return str_replace(['.', ','], '', (string) $amount);
    }

    /**
     * Compute the Signature field for an outbound payment request.
     *
     * Concatenation order (per iPay88 spec):
     *   MerchantKey + MerchantCode + RefNo + Amount + Currency
     *
     * Amount is the stripped-for-signing form (no `.` or `,`).
     */
    protected function signRequest(string $refNo, string|float $amount, string $currency): string
    {
        $concat = $this->merchantKey()
            .$this->merchantCode()
            .$refNo
            .$this->stripAmount($amount)
            .strtoupper($currency);

        return $this->hash($concat);
    }

    /**
     * Verify the Signature on an inbound BackendURL callback.
     *
     * Concatenation order (per iPay88 spec):
     *   MerchantKey + MerchantCode + PaymentId + RefNo + Amount + Currency + Status
     */
    protected function verifyCallback(array $payload): bool
    {
        $received = (string) ($payload['Signature'] ?? '');
        if ($received === '') {
            return false;
        }

        $concat = $this->merchantKey()
            .$this->merchantCode()
            .(string) ($payload['PaymentId'] ?? '')
            .(string) ($payload['RefNo'] ?? '')
            .$this->stripAmount((string) ($payload['Amount'] ?? ''))
            .strtoupper((string) ($payload['Currency'] ?? ''))
            .(string) ($payload['Status'] ?? '');

        $expected = $this->hash($concat);

        return hash_equals($expected, $received);
    }

    /**
     * Apply the configured signature algorithm to an already-built
     * concatenation string. Output encoding matches the form-field
     * format iPay88 expects:
     *   - HMACSHA512 → base64
     *   - SHA256     → uppercase hex (legacy)
     */
    protected function hash(string $concat): string
    {
        if ($this->signatureType() === 'SHA256') {
            // Legacy mode: plain SHA-256, uppercase hex. MerchantKey is
            // already inside $concat, so this is effectively keyed.
            return strtoupper(hash('sha256', $concat));
        }

        // Current standard: HMAC-SHA512, base64-encoded.
        return base64_encode(hash_hmac('sha512', $concat, $this->merchantKey(), true));
    }

    // ------------------------------------------------------------------
    // PaymentGateway
    // ------------------------------------------------------------------

    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('iPay88: charge/refund flow not yet wired — Phase 3.4. Implement as form POST to /ePayment/WebService/PaymentAPI/Checkout with MerchantCode + RefNo + Amount + Currency + Signature + SignatureType + ResponseURL + BackendURL. Country/environment selects host. Refunds via Requery API. Docs: https://www.ipay88.com/developer/');
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('iPay88: charge/refund flow not yet wired — Phase 3.4. Implement as form POST to /ePayment/WebService/PaymentAPI/Checkout with MerchantCode + RefNo + Amount + Currency + Signature + SignatureType + ResponseURL + BackendURL. Country/environment selects host. Refunds via Requery API. Docs: https://www.ipay88.com/developer/');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('iPay88: charge/refund flow not yet wired — Phase 3.4. Implement as form POST to /ePayment/WebService/PaymentAPI/Checkout with MerchantCode + RefNo + Amount + Currency + Signature + SignatureType + ResponseURL + BackendURL. Country/environment selects host. Refunds via Requery API. Docs: https://www.ipay88.com/developer/');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('iPay88: charge/refund flow not yet wired — Phase 3.4. Implement as form POST to /ePayment/WebService/PaymentAPI/Checkout with MerchantCode + RefNo + Amount + Currency + Signature + SignatureType + ResponseURL + BackendURL. Country/environment selects host. Refunds via Requery API. Docs: https://www.ipay88.com/developer/');
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException('iPay88: charge/refund flow not yet wired — Phase 3.4. Implement as form POST to /ePayment/WebService/PaymentAPI/Checkout with MerchantCode + RefNo + Amount + Currency + Signature + SignatureType + ResponseURL + BackendURL. Country/environment selects host. Refunds via Requery API. Docs: https://www.ipay88.com/developer/');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('iPay88: charge/refund flow not yet wired — Phase 3.4. Implement as form POST to /ePayment/WebService/PaymentAPI/Checkout with MerchantCode + RefNo + Amount + Currency + Signature + SignatureType + ResponseURL + BackendURL. Country/environment selects host. Refunds via Requery API. Docs: https://www.ipay88.com/developer/');
    }

    /**
     * Handle an inbound BackendURL POST from iPay88.
     *
     * iPay88 delivers callbacks as application/x-www-form-urlencoded —
     * NOT JSON — so we read everything off $request->all().
     *
     * CRITICAL: the HTTP response body to iPay88 must be the plain
     * string "RECEIVEOK" (no whitespace, no JSON, no HTML). Anything
     * else causes iPay88 to retry. We stash the expected reply on the
     * WebhookEvent's payload under `_response_body` so the wrapping
     * controller can emit it verbatim.
     */
    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $payload = $request->all();

        if (! $this->verifyCallback($payload)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'iPay88 backend signature verification failed.',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('iPay88 backend signature verification failed');
        }

        // Stash the mandatory plain-text reply on the event payload so
        // the wrapping controller can `return response('RECEIVEOK')`.
        $payloadWithReply = array_merge(
            (array) ($event->payload ?? []),
            $payload,
            ['_response_body' => 'RECEIVEOK'],
        );

        $event->forceFill([
            'payload' => $payloadWithReply,
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    // ------------------------------------------------------------------
    // Internals reserved for Phase 3.4 wiring
    // ------------------------------------------------------------------

    /**
     * Pre-baked HTTP client pointing at the resolved country/environment
     * host. The Phase 3.4 implementer can layer asForm()/post() calls on
     * top of this without re-deriving the base URL.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())->acceptJson();
    }
}
