<?php

namespace App\Support\Billing\HyperPay;

use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\Billing\Checkout\CheckoutResult;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Billing\Checkout\WidgetCheckout;
use App\Support\Billing\CheckoutGateway;
use App\Support\Billing\PaymentGateway;
use App\Support\Billing\SubscriptionGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * HyperPay (OPPWA) driver — Phase 3.3 scaffold.
 *
 * HyperPay sits on top of OPPWA (Open Payment Platform / Wirecard AG legacy
 * stack). It supports both one-off charges and recurring billing via the
 * /subscriptions API on top of a stored Registration Token, so this driver
 * implements BOTH PaymentGateway AND SubscriptionGateway.
 *
 * Auth model:
 *   - Bearer access_token in the Authorization header
 *   - entityId is a FORM BODY param on every transaction call, NOT a header
 *   - Each payment channel has its OWN entityId — Cards, Mada, Apple Pay,
 *     STC_PAY, VISA, MASTER, AMEX are all distinct channels. Pick the right
 *     one per request via entityId($method).
 *
 * Webhook model:
 *   - The ENTIRE webhook body is AES-256-GCM encrypted (no padding).
 *   - The decryption key is a DISTINCT 64-char hex string — NOT the API
 *     access_token. It is configured separately in BackOffice → Webhooks.
 *   - Two headers carry the GCM parameters: X-Initialization-Vector (hex IV)
 *     and X-Authentication-Tag (hex auth tag).
 *   - Because GCM is an AEAD mode, openssl_decrypt() returning false means
 *     the auth tag failed to validate — i.e. the payload was tampered with
 *     or the wrong key was used. There is NO separate signature header.
 *
 * Tokenization:
 *   - Pass createRegistration=true on the initial Prepare Checkout call.
 *   - The completed checkout returns a registrationId (UUID) that can be
 *     re-used for one-click charges and as the seed for /subscriptions.
 *
 * Real implementations in this scaffold:
 *   - id() / displayName()
 *   - baseUrl() per environment
 *   - authHeaders() — Bearer access_token
 *   - entityId($method) — channel-aware lookup
 *   - decryptWebhookPayload() — AES-256-GCM decrypt + JSON decode
 *   - handleWebhook() driven by decryptWebhookPayload()
 *   - http() — pre-baked PendingRequest for the Phase 3.3 wiring
 *
 * Charge / refund / subscription flows are stubs that throw RuntimeException
 * until Phase 3.3 wires them up via Prepare Checkout (POST /v1/checkouts) +
 * the COPYandPAY widget. NOTE: a fully server-to-server raw-card flow
 * requires the merchant to be PCI DSS Level 1 certified — default to the
 * COPYandPAY widget and only enable raw-card after a compliance review.
 */
class HyperPayGateway implements CheckoutGateway, PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'hyperpay';
    }

    public function displayName(): string
    {
        return 'HyperPay';
    }

    // ------------------------------------------------------------------
    // CheckoutGateway
    // ------------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        return ['SAR', 'AED', 'QAR', 'USD', 'EUR', 'GBP', 'JOD', 'EGP', 'BHD', 'KWD', 'OMR'];
    }

    public function supportsSubscriptions(): bool
    {
        return true;
    }

    /**
     * Prepare an OPPWA COPYandPAY checkout for the given session. HyperPay
     * checkout ids expire after ~30 minutes — if the stored one is still
     * within its window, reuse it; otherwise create a fresh one.
     */
    public function initiateCheckout(CheckoutSession $session): CheckoutResult
    {
        $plan = $session->plan;
        if ($plan === null) {
            throw new RuntimeException("Checkout session {$session->public_id} has no plan.");
        }

        $method = (string) (($session->metadata['payment_method'] ?? null) ?: 'card');
        $entityId = $this->entityId($method);
        if ($entityId === null) {
            throw new RuntimeException("HyperPay: no entityId configured for channel [{$method}].");
        }

        $brands = (string) (($session->metadata['brands'] ?? null) ?: 'VISA MASTER MADA');
        $returnUrl = route('checkout.return', ['session' => $session->public_id]);

        // Idempotent re-call: if a non-expired checkout id is already on
        // the session, return the same widget result without re-hitting
        // /v1/checkouts. HyperPay checkout ids expire after ~30 min.
        if (filled($session->gateway_session_id)
            && $session->status === CheckoutSession::STATUS_AWAITING_PAYMENT
            && $session->expires_at !== null
            && $session->expires_at->isFuture()
        ) {
            return new WidgetCheckout(
                gatewaySessionId: (string) $session->gateway_session_id,
                scriptUrl: $this->baseUrl().'/v1/paymentWidgets.js?checkoutId='.$session->gateway_session_id,
                widgetConfig: [
                    'brands' => $brands,
                    'shopperResultUrl' => $returnUrl,
                ],
                expiresAt: $session->expires_at->getTimestamp(),
            );
        }

        $body = [
            'entityId' => $entityId,
            'amount' => number_format($session->amount_cents / 100, 2, '.', ''),
            'currency' => strtoupper((string) $session->currency),
            'paymentType' => 'DB',
            'merchantTransactionId' => $session->public_id,
            'shopperResultUrl' => $returnUrl,
        ];

        if ($session->intent === CheckoutSession::INTENT_SUBSCRIPTION) {
            // Tokenize on first charge so MIT renewals can reuse the registration id.
            $body['createRegistration'] = 'true';
        }

        $response = $this->http()
            ->post('/v1/checkouts', $body)
            ->throw()
            ->json();

        $checkoutId = (string) ($response['id'] ?? '');
        if ($checkoutId === '') {
            throw new RuntimeException('HyperPay: Prepare Checkout response missing id');
        }

        $scriptUrl = $this->baseUrl().'/v1/paymentWidgets.js?checkoutId='.$checkoutId;
        $widgetConfig = [
            'brands' => $brands,
            'shopperResultUrl' => $returnUrl,
        ];

        $expiresAt = now()->addMinutes(30);

        $result = new WidgetCheckout(
            gatewaySessionId: $checkoutId,
            scriptUrl: $scriptUrl,
            widgetConfig: $widgetConfig,
            expiresAt: $expiresAt->getTimestamp(),
        );

        $session->forceFill([
            'gateway' => $this->id(),
            'gateway_session_id' => $checkoutId,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => $result->kind,
            'result_payload' => $result->toPayload(),
            'expires_at' => $expiresAt,
        ])->save();

        return $result;
    }

    // ------------------------------------------------------------------
    // Auth + base URL helpers
    // ------------------------------------------------------------------

    /**
     * Bearer-token auth header. The entityId is NOT included here — it
     * travels as a form body parameter on each transaction call (so a
     * single Bearer token can drive multiple channels by swapping the
     * entityId per request).
     *
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.config('billing.gateways.hyperpay.access_token'),
        ];
    }

    /**
     * Pick the OPPWA host based on environment.
     *
     *   test → https://eu-test.oppwa.com
     *   live → https://eu-prod.oppwa.com
     *
     * OPPWA does NOT publish region-specific hosts beyond the EU pair —
     * the same hosts serve all HyperPay markets (KSA / UAE / JOR / EGY).
     */
    protected function baseUrl(): string
    {
        $environment = (string) config('billing.gateways.hyperpay.environment', 'test');

        return match ($environment) {
            'test' => 'https://eu-test.oppwa.com',
            'live' => 'https://eu-prod.oppwa.com',
            default => throw new RuntimeException("HyperPay: unsupported environment [{$environment}]. Supported: test, live."),
        };
    }

    /**
     * Resolve the correct entityId for a given payment channel.
     *
     * HyperPay issues a DIFFERENT entityId per channel — Cards, Mada,
     * Apple Pay, STC_PAY, VISA, MASTER, AMEX are each distinct. The
     * boilerplate ships config for the three most common (card / mada /
     * applepay); extend the match arm + add a config key when adding a
     * new channel (e.g. STC_PAY).
     *
     * Returns null when the requested channel isn't configured so the
     * caller can decide whether to fall back or throw.
     */
    protected function entityId(string $method = 'card'): ?string
    {
        $value = match (strtolower($method)) {
            'card', 'cards', 'visa', 'master', 'mastercard', 'amex' => config('billing.gateways.hyperpay.entity_id_card'),
            'mada' => config('billing.gateways.hyperpay.entity_id_mada'),
            'applepay', 'apple_pay', 'apple-pay' => config('billing.gateways.hyperpay.entity_id_applepay'),
            default => null,
        };

        $value = is_string($value) ? trim($value) : null;

        return ($value === null || $value === '') ? null : $value;
    }

    // ------------------------------------------------------------------
    // Webhook decryption (AES-256-GCM, no padding)
    // ------------------------------------------------------------------

    /**
     * Decrypt a HyperPay webhook payload.
     *
     * HyperPay does not deliver a signature header — instead the entire
     * request body is AES-256-GCM encrypted. Successful decryption IS
     * the signature check: GCM is an AEAD mode and the auth tag is
     * validated as part of openssl_decrypt(). If the tag doesn't match,
     * openssl_decrypt() returns false (i.e. the payload was tampered
     * with, the wrong key was used, or headers were swapped).
     *
     * Layout:
     *   - Body:    hex-encoded ciphertext (Request::getContent())
     *   - Header:  X-Initialization-Vector — hex-encoded IV (typically 12 bytes / 24 hex chars)
     *   - Header:  X-Authentication-Tag    — hex-encoded GCM auth tag (16 bytes / 32 hex chars)
     *   - Key:     32 bytes / 64 hex chars, from config('billing.gateways.hyperpay.webhook_secret').
     *              DISTINCT from the API access_token — different rotation, different scope.
     *
     * Returns the decoded JSON payload as an array, or null on any
     * failure (missing headers, bad hex, auth-tag mismatch, invalid JSON).
     */
    protected function decryptWebhookPayload(Request $request): ?array
    {
        $secretHex = (string) config('billing.gateways.hyperpay.webhook_secret');
        $ivHex = (string) ($request->header('X-Initialization-Vector') ?? '');
        $tagHex = (string) ($request->header('X-Authentication-Tag') ?? '');
        $cipherHex = $request->getContent();

        if ($secretHex === '' || $ivHex === '' || $tagHex === '' || $cipherHex === '') {
            return null;
        }

        $key = @hex2bin($secretHex);
        $iv = @hex2bin($ivHex);
        $tag = @hex2bin($tagHex);
        $ciphertext = @hex2bin($cipherHex);

        if ($key === false || $iv === false || $tag === false || $ciphertext === false) {
            return null;
        }

        // AES-256-GCM requires a 32-byte key. Anything else is a config
        // misconfiguration — fail closed rather than letting openssl
        // silently truncate / pad.
        if (strlen($key) !== 32) {
            return null;
        }

        // openssl_decrypt() with 'aes-256-gcm' performs auth-tag
        // verification as part of decryption. A false return value means
        // the tag failed to validate — i.e. signature verification
        // failed. There is no padding mode for GCM (it is a stream
        // cipher under the hood), so no OPENSSL_ZERO_PADDING flag is
        // needed or supported here.
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            return null;
        }

        $decoded = json_decode($plaintext, true);

        return is_array($decoded) ? $decoded : null;
    }

    // ------------------------------------------------------------------
    // PaymentGateway — webhook handler is real; transactions are stubs
    // ------------------------------------------------------------------

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $payload = $this->decryptWebhookPayload($request);

        if ($payload === null) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'HyperPay webhook decryption / auth-tag verification failed',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('HyperPay webhook decryption / auth-tag verification failed');
        }

        // HyperPay categorises webhook events via a top-level `type`
        // field: PAYMENT, REGISTRATION, SCHEDULE, RISK. PAYMENT events
        // with a success result code reconcile the CheckoutSession.
        $type = (string) ($payload['type'] ?? '');

        $event->forceFill([
            'event_type' => $type !== '' ? $type : $event->event_type,
            'payload' => $payload,
        ])->save();

        try {
            if ($type === 'PAYMENT') {
                $this->onPaymentEvent($payload);
            }

            $event->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            Log::warning('HyperPay webhook processing failed', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event->fresh();
    }

    /**
     * Reconcile a successful PAYMENT webhook with the local CheckoutSession.
     *
     * HyperPay/OPPWA success codes per docs:
     *   - 000.000.*       — transaction succeeded
     *   - 000.100.1*      — succeeded (manual review / honoured)
     *   - 000.[36]*       — succeeded (chargeback warning / review)
     *
     * @param  array<string, mixed>  $payload
     */
    private function onPaymentEvent(array $payload): void
    {
        $body = is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload;

        $resultCode = (string) ($body['result']['code'] ?? '');
        if (! preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $resultCode)) {
            return;
        }

        // OPPWA puts the original checkout id back on the transaction
        // notification as either `ndc` (network data carrier — same value)
        // or `id`. Prefer ndc; fall back to id.
        $checkoutId = (string) ($body['ndc'] ?? $body['id'] ?? '');
        if ($checkoutId === '') {
            Log::warning('HyperPay PAYMENT success received with no checkout id', [
                'payload_keys' => array_keys($body),
            ]);

            return;
        }

        $session = CheckoutSession::query()
            ->where('gateway', $this->id())
            ->where('gateway_session_id', $checkoutId)
            ->first();

        if ($session === null) {
            Log::warning('HyperPay PAYMENT success: no matching CheckoutSession', [
                'checkout_id' => $checkoutId,
            ]);

            return;
        }

        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            return;
        }

        $paymentId = (string) ($body['id'] ?? $checkoutId);
        $amount = $body['amount'] ?? null;
        $currency = strtoupper((string) ($body['currency'] ?? $session->currency));
        $amountCents = $amount !== null ? (int) round(((float) $amount) * 100) : (int) $session->amount_cents;

        $completePayload = [
            'gateway_payment_id' => $paymentId,
            'amount_paid_cents' => $amountCents,
            'paid_amount_cents' => $amountCents,
            'currency' => $currency,
            'invoice_number' => 'INV-'.$session->public_id,
        ];

        $registrationId = (string) ($body['registrationId'] ?? '');
        if ($registrationId !== '' && $session->intent === CheckoutSession::INTENT_SUBSCRIPTION) {
            $completePayload['gateway_subscription_id'] = $registrationId;
        }

        app(CheckoutService::class)->complete($session, $completePayload);
    }

    /**
     * Prepare an OPPWA COPYandPAY checkout (one-shot DEBIT).
     *
     * OPPWA's transaction endpoints require application/x-www-form-urlencoded
     * (NOT JSON) — the pre-baked http() client already calls asForm(). The
     * `entityId` is a body param, channel-aware via entityId(); `paymentType`
     * is DB for immediate capture (use PA from a future authorize() for
     * pre-auth). The response includes the checkout `id`, which the front
     * end feeds into paymentWidgets.js to render the COPYandPAY widget.
     *
     * To opt-in to tokenization for later one-click / recurring charges,
     * pass $context['create_registration'] = true — OPPWA will return a
     * registrationId on the completed checkout.
     *
     * Caller is responsible for providing `tenant_id` in $context (Payment
     * is tenant-scoped). Optional context keys: invoice_id, idempotency_key,
     * payment_method ('card'|'mada'|'applepay'), create_registration (bool),
     * extra (array of extra OPPWA form fields, e.g. customer.email).
     *
     * @param  array<string, mixed>  $context
     *
     * // SANDBOX VERIFICATION REQUIRED — built from HyperPay/OPPWA docs, untested
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        $method = (string) ($context['payment_method'] ?? 'card');
        $entityId = $this->entityId($method);

        if ($entityId === null) {
            throw new RuntimeException("HyperPay: no entityId configured for channel [{$method}].");
        }

        $merchantRef = (string) ($context['idempotency_key'] ?? Str::uuid());

        $body = [
            'entityId' => $entityId,
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'currency' => strtoupper($currency),
            'paymentType' => 'DB',
            'merchantTransactionId' => $merchantRef,
        ];

        if (! empty($context['create_registration'])) {
            $body['createRegistration'] = 'true';
        }

        if (isset($context['extra']) && is_array($context['extra'])) {
            $body = array_merge($body, $context['extra']);
        }

        $response = $this->http()
            ->post('/v1/checkouts', $body)
            ->throw()
            ->json();

        $checkoutId = (string) ($response['id'] ?? '');

        if ($checkoutId === '') {
            throw new RuntimeException('HyperPay: Prepare Checkout response missing id');
        }

        $widgetScriptUrl = $this->baseUrl().'/v1/paymentWidgets.js?checkoutId='.$checkoutId;

        return Payment::create([
            'tenant_id' => $context['tenant_id'] ?? null,
            'invoice_id' => $context['invoice_id'] ?? null,
            'gateway' => $this->id(),
            'gateway_payment_id' => $checkoutId,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'idempotency_key' => $merchantRef,
            'metadata' => [
                'checkout_id' => $checkoutId,
                'widget_script_url' => $widgetScriptUrl,
                'payment_method' => $method,
                'entity_id' => $entityId,
                'merchant_transaction_id' => $merchantRef,
                'create_registration' => (bool) ($context['create_registration'] ?? false),
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
     * Refund an OPPWA payment (full or partial).
     *
     * OPPWA chains transactions via the source payment id — the `paymentType`
     * RF (refund) is POSTed to /v1/payments/{paymentId}. The paymentId is the
     * id of the successful charge transaction (NOT the checkout id) — we
     * prefer $payment->metadata.payment_id (populated from the success
     * webhook / verification) and fall back to gateway_payment_id when the
     * caller stored the payment id directly there.
     *
     * // SANDBOX VERIFICATION REQUIRED — built from HyperPay/OPPWA docs, untested
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        $sourcePaymentId = (string) ($payment->metadata['payment_id'] ?? $payment->gateway_payment_id ?? '');
        if ($sourcePaymentId === '') {
            throw new RuntimeException('HyperPay: cannot refund — missing source payment id on Payment#'.$payment->id);
        }

        $method = (string) ($payment->metadata['payment_method'] ?? 'card');
        $entityId = (string) ($payment->metadata['entity_id'] ?? $this->entityId($method) ?? '');
        if ($entityId === '') {
            throw new RuntimeException("HyperPay: no entityId available to refund Payment#{$payment->id}.");
        }

        $alreadyRefunded = (int) ($payment->refunded_cents ?? 0);
        $remaining = (int) $payment->amount_cents - $alreadyRefunded;
        $refundCents = $amountCents ?? $remaining;

        $body = [
            'entityId' => $entityId,
            'amount' => number_format($refundCents / 100, 2, '.', ''),
            'currency' => strtoupper((string) $payment->currency),
            'paymentType' => 'RF',
        ];

        $response = $this->http()
            ->post('/v1/payments/'.$sourcePaymentId, $body)
            ->throw()
            ->json();

        $newRefunded = $alreadyRefunded + $refundCents;

        $payment->forceFill([
            'refunded_cents' => $newRefunded,
            'refunded_at' => now(),
            'status' => $newRefunded >= (int) $payment->amount_cents ? 'refunded' : 'partially_refunded',
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
    // SubscriptionGateway — stubs until Phase 3.3
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        throw new RuntimeException($this->subscriptionStubMessage());
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
    // HTTP helper reserved for Phase 3.3 wiring
    // ------------------------------------------------------------------

    /**
     * Pre-baked HTTP client for the resolved environment.
     *
     * Transaction calls (POST /v1/checkouts, GET /v1/checkouts/{id}/payment,
     * POST /v1/payments/{id}, POST /v1/registrations/{id}/payments) all use
     * application/x-www-form-urlencoded with `entityId` carried in the body
     * — so this client defaults to asForm() rather than asJson().
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders($this->authHeaders())
            ->acceptJson()
            ->asForm();
    }

    // ------------------------------------------------------------------
    // Stub messages
    // ------------------------------------------------------------------

    private function paymentStubMessage(): string
    {
        return 'HyperPay: charge/refund flow not yet wired — Phase 3.3. Implement via Prepare Checkout (POST /v1/checkouts) → render paymentWidgets.js (COPYandPAY) → verify via GET /v1/checkouts/{id}/payment. entityId is form param per channel. Docs: https://hyperpay.docs.oppwa.com/';
    }

    private function subscriptionStubMessage(): string
    {
        return 'HyperPay: subscription flow not yet wired — Phase 3.3. Implement via /subscriptions API + registrationId from Registration Token. Docs: same';
    }
}
