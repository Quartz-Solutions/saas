<?php

namespace App\Support\Billing\Paymob;

use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Support\Billing\Checkout\CheckoutResult;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Billing\Checkout\IframeCheckout;
use App\Support\Billing\CheckoutGateway;
use App\Support\Billing\PaymentGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Paymob driver — Phase 3.2 scaffold.
 *
 * Implements PaymentGateway only. Paymob has no native subscription primitive;
 * recurring billing is performed merchant-side via tokenized cards (a one-off
 * card payment returns a token that can be re-charged headlessly). Therefore
 * SubscriptionGateway is intentionally NOT implemented here.
 *
 * Real implementations in this scaffold:
 *   - Webhook HMAC-SHA512 verification (verifyTransactionHmac)
 *   - handleWebhook() driven by that verification
 *   - baseUrl() switch on Paymob region
 *
 * Charge / refund / status flows are stubbed and throw until Phase 3.2 wires
 * the Unified Intentions API (preferred) or the legacy 3-step Accept flow.
 *
 * Regions supported by Paymob:
 *   eg → https://accept.paymob.com   (Egypt — original)
 *   ae → https://uae.paymob.com      (United Arab Emirates)
 *   sa → https://ksa.paymob.com      (Saudi Arabia)
 *   om → https://oman.paymob.com     (Oman)
 *   pk → https://pakistan.paymob.com (Pakistan)
 *
 * HTTP calls use Illuminate\Support\Facades\Http only; no Paymob composer SDK
 * is required.
 */
class PaymobGateway implements CheckoutGateway, PaymentGateway
{
    /**
     * Ordered field list used to build the HMAC-SHA512 signed string for
     * Paymob TRANSACTION callbacks (the standard pay / refund / void / capture
     * webhook).
     *
     * Order is fixed by Paymob — do NOT sort or rearrange. Each entry is a
     * dot-path resolved against the `obj` object in the callback payload.
     *
     * Card-token callbacks use a DIFFERENT field list (token, masked_pan,
     * merchant_id, card_subtype, created_at, email, order_id, card_token_id,
     * card_token_value, etc.). That path is not handled here; add a
     * verifyCardTokenHmac() method when card-vaulting is wired.
     *
     * @var list<string>
     */
    protected const TRANSACTION_HMAC_FIELDS = [
        'amount_cents',
        'created_at',
        'currency',
        'error_occured',
        'has_parent_transaction',
        'id',
        'integration_id',
        'is_3d_secure',
        'is_auth',
        'is_capture',
        'is_refunded',
        'is_standalone_payment',
        'is_voided',
        'order.id',
        'owner',
        'pending',
        'source_data.pan',
        'source_data.sub_type',
        'source_data.type',
        'success',
    ];

    public function id(): string
    {
        return 'paymob';
    }

    public function displayName(): string
    {
        return 'Paymob';
    }

    // ------------------------------------------------------------------
    // CheckoutGateway
    // ------------------------------------------------------------------

    /**
     * Paymob settlement currencies across the supported regions.
     *
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        return ['EGP', 'SAR', 'AED', 'USD'];
    }

    public function supportsSubscriptions(): bool
    {
        return false;
    }

    /**
     * Create a Paymob Unified Intention and return an IframeCheckout. Idempotent
     * via the persisted gateway_session_id + result_payload — re-call short-circuits.
     */
    public function initiateCheckout(CheckoutSession $session): CheckoutResult
    {
        if ($session->intent === CheckoutSession::INTENT_SUBSCRIPTION) {
            throw new RuntimeException('Paymob does not support subscriptions natively; use card-token merchant-side recurring in Phase 3+.');
        }

        if ($session->gateway_session_id !== null && is_array($session->result_payload)
            && isset($session->result_payload['iframe_url'])) {
            return new IframeCheckout(
                gatewaySessionId: (string) $session->gateway_session_id,
                iframeUrl: (string) $session->result_payload['iframe_url'],
                iframeAttributes: (array) ($session->result_payload['iframe_attributes'] ?? ['height' => '700']),
            );
        }

        $plan = $session->plan;
        if ($plan === null) {
            throw new RuntimeException("Checkout session {$session->public_id} has no plan.");
        }

        $secret = $this->secretKey();
        $publicKey = (string) config('billing.gateways.paymob.public_key', '');

        $paymentMethods = $this->configuredPaymentMethods();
        if ($paymentMethods === []) {
            throw new RuntimeException('Paymob: no integration_id_card / integration_id_wallet configured. Set PAYMOB_INTEGRATION_ID_CARD.');
        }

        $tenant = $session->tenant;
        $user = $session->user;

        $payload = [
            'amount' => (int) $session->amount_cents,
            'currency' => strtoupper((string) $session->currency),
            'payment_methods' => array_values(array_map(static fn ($id) => (int) $id, $paymentMethods)),
            'items' => [[
                'name' => (string) $plan->name,
                'amount' => (int) $session->amount_cents,
                'description' => (string) ($plan->description ?? $plan->name),
                'quantity' => 1,
            ]],
            'billing_data' => [
                'first_name' => (string) ($user?->name ?? $tenant?->name ?? 'NA'),
                'last_name' => 'NA',
                'email' => (string) ($user?->email ?? 'NA'),
                'phone_number' => 'NA',
            ],
            'special_reference' => $session->public_id,
            'notification_url' => url('/webhooks/paymob'),
            'redirection_url' => route('checkout.return', ['session' => $session->public_id]),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$secret,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl().'/v1/intention', $payload);

        $this->assertOk($response, 'create intention');

        $body = (array) $response->json();
        $intentionId = (string) ($body['id'] ?? $body['intention_id'] ?? '');
        $clientSecret = (string) ($body['client_secret'] ?? '');

        if ($intentionId === '' || $clientSecret === '') {
            throw new RuntimeException('Paymob: /v1/intention returned no id/client_secret. Body: '.$response->body());
        }

        $iframeUrl = $this->baseUrl().'/unifiedcheckout/?publicKey='.rawurlencode($publicKey).'&clientSecret='.rawurlencode($clientSecret);

        $result = new IframeCheckout(
            gatewaySessionId: $intentionId,
            iframeUrl: $iframeUrl,
            iframeAttributes: ['height' => '700'],
        );

        $session->forceFill([
            'gateway' => 'paymob',
            'gateway_session_id' => $result->gatewaySessionId,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => $result->kind,
            'result_payload' => $result->toPayload(),
        ])->save();

        return $result;
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs until Phase 3.2
    // ------------------------------------------------------------------

    /**
     * Create a Paymob Unified Intention and persist a pending Payment row.
     *
     * SANDBOX VERIFICATION REQUIRED — built from Paymob Unified Intentions docs, untested.
     *
     * @param  array<string, mixed>  $context  Expected keys:
     *                                         - tenant_id (required) — used to bind the new Payment row
     *                                         - invoice_id (optional)
     *                                         - idempotency_key (optional)
     *                                         - billing (optional array): first_name, last_name, email, phone_number
     *                                         - payment_methods (optional list<int>) — overrides config integration IDs
     *
     * @see https://developers.paymob.com/paymob-docs/integration-paths/apis
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        $secret = $this->secretKey();
        $publicKey = (string) config('billing.gateways.paymob.public_key', '');

        $paymentMethods = $context['payment_methods'] ?? $this->configuredPaymentMethods();
        if ($paymentMethods === []) {
            throw new RuntimeException('Paymob: no integration_id_card / integration_id_wallet configured. Set PAYMOB_INTEGRATION_ID_CARD (or pass payment_methods in $context).');
        }

        $billing = (array) ($context['billing'] ?? []);

        $payload = [
            'amount' => $amountCents,
            'currency' => strtoupper($currency),
            'payment_methods' => array_values(array_map(static fn ($id) => (int) $id, $paymentMethods)),
            'billing_data' => [
                'first_name' => (string) ($billing['first_name'] ?? 'NA'),
                'last_name' => (string) ($billing['last_name'] ?? 'NA'),
                'email' => (string) ($billing['email'] ?? 'NA'),
                'phone_number' => (string) ($billing['phone_number'] ?? 'NA'),
            ],
        ];

        if (isset($context['items']) && is_array($context['items'])) {
            $payload['items'] = $context['items'];
        }

        if (isset($context['extras']) && is_array($context['extras'])) {
            $payload['extras'] = $context['extras'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Token '.$secret,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl().'/v1/intention', $payload);

        $this->assertOk($response, 'create intention');

        $body = (array) $response->json();
        $intentionId = (string) ($body['id'] ?? $body['intention_id'] ?? '');
        $clientSecret = (string) ($body['client_secret'] ?? '');

        if ($intentionId === '' || $clientSecret === '') {
            throw new RuntimeException('Paymob: /v1/intention returned no id/client_secret. Body: '.$response->body());
        }

        $redirectUrl = $this->baseUrl().'/unifiedcheckout/?publicKey='.rawurlencode($publicKey).'&clientSecret='.rawurlencode($clientSecret);

        $metadata = array_merge((array) ($body['metadata'] ?? []), [
            'redirect_url' => $redirectUrl,
            'client_secret' => $clientSecret,
            'intention_id' => $intentionId,
            'payment_methods' => $payload['payment_methods'],
        ]);

        $attrs = [
            'gateway' => 'paymob',
            'gateway_payment_id' => $intentionId,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'metadata' => $metadata,
        ];

        if (isset($context['idempotency_key'])) {
            $attrs['idempotency_key'] = $context['idempotency_key'];
        }
        if (isset($context['tenant_id'])) {
            $attrs['tenant_id'] = $context['tenant_id'];
        }
        if (isset($context['invoice_id'])) {
            $attrs['invoice_id'] = $context['invoice_id'];
        }

        $existing = Payment::query()
            ->where('gateway', 'paymob')
            ->where('gateway_payment_id', $intentionId)
            ->first();

        if ($existing !== null) {
            $existing->forceFill($attrs)->save();

            return $existing->fresh();
        }

        if (! isset($attrs['tenant_id'])) {
            throw new RuntimeException('PaymobGateway::charge: tenant_id is required for new payments. Pass via $context.');
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
        throw new RuntimeException($this->stubMessage());
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    /**
     * Refund a Paymob transaction (full or partial).
     *
     * SANDBOX VERIFICATION REQUIRED — built from Paymob Unified Intentions docs, untested.
     *
     * NOTE: $payment->gateway_payment_id holds the INTENTION id from charge().
     * Paymob's refund endpoint wants the TRANSACTION id, which lands on the
     * Payment row's metadata via the webhook handler when the customer completes
     * the unified checkout. We prefer metadata.transaction_id, then fall back to
     * gateway_payment_id for the case where the caller has already overwritten it.
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        $secret = $this->secretKey();

        $metadata = (array) ($payment->metadata ?? []);
        $transactionId = (string) ($metadata['transaction_id'] ?? $payment->gateway_payment_id);

        if ($transactionId === '') {
            throw new RuntimeException('PaymobGateway::refund: no transaction_id on Payment row. Webhook must populate metadata.transaction_id before refund.');
        }

        $refundAmount = $amountCents ?? (int) $payment->amount_cents;

        $response = Http::withHeaders([
            'Authorization' => 'Token '.$secret,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl().'/api/acceptance/void_refund/refund', [
            'transaction_id' => $transactionId,
            'amount_cents' => $refundAmount,
        ]);

        $this->assertOk($response, 'refund');

        return DB::transaction(function () use ($payment, $refundAmount, $response) {
            $newRefunded = (int) $payment->refunded_cents + $refundAmount;
            $payment->forceFill([
                'refunded_cents' => $newRefunded,
                'status' => $newRefunded >= (int) $payment->amount_cents ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
                'metadata' => array_merge((array) ($payment->metadata ?? []), [
                    'refund_response' => $response->json(),
                ]),
            ])->save();

            return $payment->fresh();
        });
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    // ------------------------------------------------------------------
    // Webhook handling
    // ------------------------------------------------------------------

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        if (! $this->verifyTransactionHmac($request)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'HMAC mismatch',
            ])->save();

            throw new RuntimeException('Paymob HMAC verification failed');
        }

        $payload = $request->json()->all();
        $obj = (array) ($payload['obj'] ?? []);
        $transactionId = $obj['id'] ?? null;

        try {
            $this->dispatchTransactionEvent($obj);

            $event->forceFill([
                'status' => 'processed',
                'gateway_event_id' => $transactionId !== null ? (string) $transactionId : $event->gateway_event_id,
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            Log::warning('Paymob webhook processing failed', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event->fresh();
    }

    /**
     * Route a verified TRANSACTION callback to CheckoutService::complete when
     * it represents a successful, non-voided, non-refunded payment.
     *
     * @param  array<string, mixed>  $obj  The `obj` block from the callback payload.
     */
    private function dispatchTransactionEvent(array $obj): void
    {
        $success = (bool) ($obj['success'] ?? false);
        $voided = (bool) ($obj['is_voided'] ?? false);
        $refunded = (bool) ($obj['is_refunded'] ?? false);

        if (! $success || $voided || $refunded) {
            return;
        }

        $intentionId = (string) (
            $obj['payment_key_claims']['extra']['intention_id']
            ?? $obj['order']['shipping_data']['intention_id']
            ?? $obj['intention_id']
            ?? ''
        );

        if ($intentionId === '') {
            return;
        }

        $session = CheckoutSession::query()
            ->where('gateway', 'paymob')
            ->where('gateway_session_id', $intentionId)
            ->first();

        if ($session === null) {
            Log::warning('Paymob webhook: no matching CheckoutSession', [
                'intention_id' => $intentionId,
                'transaction_id' => $obj['id'] ?? null,
            ]);

            return;
        }

        app(CheckoutService::class)->complete($session, [
            'gateway_payment_id' => (string) ($obj['id'] ?? ''),
            'amount_paid_cents' => (int) ($obj['amount_cents'] ?? $session->amount_cents),
            'paid_amount_cents' => (int) ($obj['amount_cents'] ?? $session->amount_cents),
            'currency' => strtoupper((string) ($obj['currency'] ?? $session->currency)),
        ]);
    }

    /**
     * Public helper so the webhook controller can verify ahead of persisting
     * the WebhookEvent row if it prefers. Delegates to the protected impl.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        return $this->verifyTransactionHmac($request);
    }

    /**
     * Verify a Paymob TRANSACTION callback using HMAC-SHA512.
     *
     * Paymob delivers the signature as the `hmac` QUERY-STRING parameter on
     * the callback URL (NOT a header). The signed string is the ordered
     * concatenation of TRANSACTION_HMAC_FIELDS pulled from `payload.obj`.
     * Booleans are lowercased to 'true' / 'false'; nulls become ''.
     *
     * @see https://developers.paymob.com/paymob-docs/integration-paths/apis
     */
    protected function verifyTransactionHmac(Request $request): bool
    {
        $secret = (string) config('billing.gateways.paymob.hmac_secret');
        if ($secret === '') {
            return false;
        }

        $provided = (string) $request->query('hmac', '');
        if ($provided === '') {
            return false;
        }

        $obj = (array) ($request->json('obj') ?? []);
        if ($obj === []) {
            return false;
        }

        $concat = '';
        foreach (self::TRANSACTION_HMAC_FIELDS as $path) {
            $concat .= $this->normalizeHmacValue($this->dotGet($obj, $path));
        }

        $computed = hash_hmac('sha512', $concat, $secret);

        return hash_equals($computed, strtolower($provided));
    }

    /**
     * Normalize a value into the string form Paymob signs.
     *
     * Rules:
     *   - true  → 'true'
     *   - false → 'false'
     *   - null  → ''
     *   - other → (string) $value
     */
    protected function normalizeHmacValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Read a dot-path from a nested array. Returns null when any segment is
     * missing rather than throwing — matches Paymob behavior where optional
     * source_data.* fields can be absent on non-card transactions.
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

    // ------------------------------------------------------------------
    // Region routing
    // ------------------------------------------------------------------

    /**
     * Pick the regional Paymob API host. Each Paymob region runs its own
     * Accept stack on a distinct domain; all of them share the same path
     * surface (/api/auth, /api/ecommerce, /api/acceptance, /v1/intention).
     */
    protected function baseUrl(): string
    {
        $region = (string) config('billing.gateways.paymob.region', 'eg');

        return match ($region) {
            'eg' => 'https://accept.paymob.com',
            'ae' => 'https://uae.paymob.com',
            'sa' => 'https://ksa.paymob.com',
            'om' => 'https://oman.paymob.com',
            'pk' => 'https://pakistan.paymob.com',
            default => throw new RuntimeException("Paymob: unsupported region [{$region}]. Supported: eg, ae, sa, om, pk."),
        };
    }

    /**
     * Resolve the Paymob secret key used for the Unified Intentions API.
     * Sent as `Authorization: Token {secret_key}`.
     */
    protected function secretKey(): string
    {
        $secret = (string) config('billing.gateways.paymob.secret_key', '');

        if ($secret === '') {
            throw new RuntimeException('Paymob: secret_key is not configured. Set PAYMOB_SECRET_KEY in .env.');
        }

        return $secret;
    }

    /**
     * Build the payment_methods list from configured integration IDs.
     * Card first, then wallet — both optional but at least one is required.
     *
     * @return list<int>
     */
    protected function configuredPaymentMethods(): array
    {
        $methods = [];

        $card = config('billing.gateways.paymob.integration_id_card');
        if ($card !== null && $card !== '') {
            $methods[] = (int) $card;
        }

        $wallet = config('billing.gateways.paymob.integration_id_wallet');
        if ($wallet !== null && $wallet !== '') {
            $methods[] = (int) $wallet;
        }

        return $methods;
    }

    /**
     * Throw a descriptive RuntimeException when Paymob returns a non-2xx
     * response. Body is included so the caller can see the validation error.
     */
    protected function assertOk(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Paymob %s failed: HTTP %d — %s',
            $operation,
            $response->status(),
            $response->body(),
        ));
    }

    private function stubMessage(): string
    {
        return 'Paymob: charge/refund flow not yet wired — Phase 3.2. Implement via Unified Intentions API (preferred: POST /v1/intention with secret_key Bearer) OR legacy 3-step Accept (auth-token → order register → payment_key → iframe). Docs: https://developers.paymob.com/paymob-docs/integration-paths/apis';
    }
}
