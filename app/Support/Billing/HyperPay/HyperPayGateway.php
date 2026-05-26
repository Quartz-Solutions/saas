<?php

namespace App\Support\Billing\HyperPay;

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
class HyperPayGateway implements PaymentGateway, SubscriptionGateway
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
        // field: PAYMENT, REGISTRATION, SCHEDULE, RISK. The wiring
        // task in Phase 3.3 will fan these out to per-type handlers
        // (e.g. PAYMENT → reconcile payment status, REGISTRATION →
        // persist a new card token). For now we just persist the
        // decoded payload + type on the WebhookEvent row.
        $type = (string) ($payload['type'] ?? '');

        $event->forceFill([
            'event_type' => $type !== '' ? $type : $event->event_type,
            'payload' => $payload,
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException($this->paymentStubMessage());
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

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException($this->paymentStubMessage());
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
