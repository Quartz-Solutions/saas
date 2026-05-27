<?php

namespace App\Support\Billing\PayTabs;

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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * PayTabs driver — Phase 3.2 scaffold.
 *
 * Implements PaymentGateway and SubscriptionGateway. PayTabs supports
 * recurring charges via "Agreement" / "Repeat Billing" on a previously
 * tokenized card — so this driver claims the SubscriptionGateway seam
 * even though the lifecycle methods are still stubs.
 *
 * Notes from PayTabs ops experience worth keeping near the code:
 *   - A profile_id / server_key pair is bound to ONE region. Switching
 *     region requires a NEW merchant profile + NEW credentials — the
 *     same pair will NOT authenticate against another region's host.
 *   - STC Pay is KSA-only and only works on the SAU endpoint.
 *   - IPN is HTTPS-only. If the configured IPN URL is plain HTTP,
 *     PayTabs delivers an EMPTY body (and signature won't match) —
 *     surface this loudly during onboarding.
 *   - PayTabs markets "160+ currencies" but does not publish the
 *     exact list; treat unknown ISO 4217 codes as "probably fine but
 *     test in sandbox first".
 *
 * Auth: the `authorization` header is the RAW server_key — there is
 * NO `Bearer ` prefix. Confirmed against PayTabs API reference.
 */
class PayTabsGateway implements CheckoutGateway, PaymentGateway, SubscriptionGateway
{
    public function __construct(
        protected readonly string $profileId = '',
        protected readonly string $serverKey = '',
        protected readonly string $clientKey = '',
        protected readonly string $region = 'SAU',
    ) {}

    public function id(): string
    {
        return 'paytabs';
    }

    public function displayName(): string
    {
        return 'PayTabs';
    }

    // ------------------------------------------------------------------
    // CheckoutGateway
    // ------------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        return ['SAR', 'AED', 'KWD', 'BHD', 'QAR', 'OMR', 'JOD', 'EGP', 'USD', 'EUR', 'GBP'];
    }

    public function supportsSubscriptions(): bool
    {
        return true;
    }

    public function initiateCheckout(CheckoutSession $session): CheckoutResult
    {
        // Idempotent re-call: if a PayPage was already created for this session
        // and is still usable, return the existing redirect rather than POSTing
        // a duplicate /payment/request.
        if ($session->gateway_session_id !== null) {
            $existing = $this->retrievePayPage($session->gateway_session_id);
            $existingUrl = (string) ($existing['redirect_url'] ?? ($session->result_payload['url'] ?? ''));
            if ($existingUrl !== '') {
                return new RedirectCheckout(
                    gatewaySessionId: (string) $session->gateway_session_id,
                    url: $existingUrl,
                );
            }
        }

        $plan = $session->plan;
        if ($plan === null) {
            throw new RuntimeException("Checkout session {$session->public_id} has no plan.");
        }

        $profileId = (int) (config('billing.gateways.paytabs.profile_id') ?: $this->profileId);
        $isSubscription = $session->intent === CheckoutSession::INTENT_SUBSCRIPTION;

        $payload = [
            'profile_id' => $profileId,
            'tran_type' => 'sale',
            'tran_class' => $isSubscription ? 'recurring' : 'ecom',
            'cart_id' => $session->public_id,
            'cart_description' => $plan->name,
            'cart_currency' => strtoupper((string) $session->currency),
            'cart_amount' => number_format((int) $session->amount_cents / 100, 2, '.', ''),
            'return' => route('checkout.return', ['session' => $session->public_id]),
            'callback' => route('checkout.return', ['session' => $session->public_id]),
        ];

        if ($isSubscription) {
            // PayTabs treats `is_recurring` on the first PayPage as a flag to
            // tokenize the card for future MIT charges via tran_ref.
            $payload['is_recurring'] = true;
        }

        $response = $this->http()->post('/payment/request', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('PayTabs /payment/request failed: '.$response->status().' '.$response->body());
        }

        $body = (array) $response->json();
        $tranRef = (string) ($body['tran_ref'] ?? '');
        $redirectUrl = (string) ($body['redirect_url'] ?? '');

        if ($tranRef === '' || $redirectUrl === '') {
            throw new RuntimeException('PayTabs /payment/request returned no tran_ref or redirect_url: '.$response->body());
        }

        $result = new RedirectCheckout(
            gatewaySessionId: $tranRef,
            url: $redirectUrl,
        );

        $session->forceFill([
            'gateway' => 'paytabs',
            'gateway_session_id' => $result->gatewaySessionId,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => $result->kind,
            'result_payload' => $result->toPayload(),
        ])->save();

        return $result;
    }

    /**
     * Best-effort re-fetch of a previously created PayPage. PayTabs exposes
     * `/payment/query` which echoes back tran_ref + (when still open) the
     * redirect_url. Returns an empty array on any failure so the caller can
     * fall through to creating a fresh PayPage.
     *
     * @return array<string, mixed>
     */
    protected function retrievePayPage(string $tranRef): array
    {
        try {
            $profileId = (int) (config('billing.gateways.paytabs.profile_id') ?: $this->profileId);
            $response = $this->http()->post('/payment/query', [
                'profile_id' => $profileId,
                'tran_ref' => $tranRef,
            ]);

            if (! $response->successful()) {
                return [];
            }

            return (array) $response->json();
        } catch (Throwable) {
            return [];
        }
    }

    // ------------------------------------------------------------------
    // Region + auth helpers
    // ------------------------------------------------------------------

    /**
     * Region-aware API base URL. The PayTabs dashboard's
     * "What is my region/endpoint URL" page is authoritative — if you
     * see 401s against a region you expected to work, re-check there.
     */
    protected function baseUrl(): string
    {
        $region = (string) (config('billing.gateways.paytabs.region') ?: $this->region);

        return match (strtoupper($region)) {
            'SAU' => 'https://secure.paytabs.sa',
            'ARE' => 'https://secure.paytabs.com',
            'EGY' => 'https://secure-egypt.paytabs.com',
            'OMN' => 'https://secure-oman.paytabs.com',
            'JOR' => 'https://secure-jordan.paytabs.com',
            'KWT' => 'https://secure-kuwait.paytabs.com',
            'IRQ' => 'https://secure-iraq.paytabs.com',
            'QAT' => 'https://secure.paytabs.com',
            'MAR' => 'https://secure.paytabs.com',
            'GLOBAL' => 'https://secure-global.paytabs.com',
            // Dashboard's "What is my region/endpoint URL" page is the
            // source of truth — default to SAU but log/verify when in doubt.
            default => 'https://secure.paytabs.sa',
        };
    }

    /**
     * PayTabs auth header — the RAW server_key, NO `Bearer` prefix.
     *
     * @return array<string, string>
     */
    protected function authHeader(): array
    {
        $serverKey = (string) (config('billing.gateways.paytabs.server_key') ?: $this->serverKey);

        return ['Authorization' => $serverKey];
    }

    /**
     * Verify a PayTabs IPN signature.
     *
     * Algorithm: HMAC-SHA256 over the raw request body, keyed with the
     * merchant server_key, compared via hash_equals.
     *
     * IMPORTANT: must hash the RAW body (Request::getContent()), not a
     * re-encoded JSON string — PHP's json_encode is not byte-identical
     * to PayTabs' serialization and the signature will not match.
     */
    protected function verifyIpn(string $rawBody, string $headerSig): bool
    {
        $serverKey = (string) (config('billing.gateways.paytabs.server_key') ?: $this->serverKey);

        if ($serverKey === '' || $headerSig === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $serverKey);

        return hash_equals($expected, $headerSig);
    }

    // ------------------------------------------------------------------
    // PaymentGateway
    // ------------------------------------------------------------------

    /**
     * SANDBOX VERIFICATION REQUIRED — built from PayTabs docs, untested.
     *
     * Creates a hosted PayPage by POST /payment/request. PayTabs responds
     * with a `tran_ref` (gateway payment id) and a `redirect_url` the
     * client must navigate to in order to complete the payment. The
     * Payment row is persisted as `pending` until the IPN promotes it.
     *
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        $profileId = (int) (config('billing.gateways.paytabs.profile_id') ?: $this->profileId);

        $cartId = (string) ($context['idempotency_key'] ?? Str::uuid());

        $payload = [
            'profile_id' => $profileId,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $cartId,
            'cart_description' => $context['description'] ?? 'Subscription',
            'cart_currency' => strtoupper($currency),
            'cart_amount' => number_format($amountCents / 100, 2, '.', ''),
            'customer_details' => $context['customer'] ?? [
                'name' => 'NA',
                'email' => 'na@example.com',
                'phone' => 'NA',
                'street1' => 'NA',
                'city' => 'NA',
                'state' => 'NA',
                'country' => 'SAU',
                'zip' => '00000',
            ],
            'return' => $context['return_url'] ?? '',
            'callback' => $context['callback_url'] ?? '',
        ];

        $response = $this->http()->post('/payment/request', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('PayTabs /payment/request failed: '.$response->status().' '.$response->body());
        }

        $body = (array) $response->json();
        $tranRef = (string) ($body['tran_ref'] ?? '');
        $redirectUrl = (string) ($body['redirect_url'] ?? '');

        if ($tranRef === '') {
            throw new RuntimeException('PayTabs /payment/request returned no tran_ref: '.$response->body());
        }

        if (! isset($context['tenant_id'])) {
            throw new RuntimeException('PayTabs::charge: tenant_id is required in $context.');
        }

        $attrs = [
            'tenant_id' => $context['tenant_id'],
            'gateway' => $this->id(),
            'gateway_payment_id' => $tranRef,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'metadata' => array_merge(
                (array) ($context['metadata'] ?? []),
                ['redirect_url' => $redirectUrl, 'cart_id' => $cartId, 'paytabs' => $body],
            ),
            'idempotency_key' => $context['idempotency_key'] ?? null,
        ];

        if (isset($context['invoice_id'])) {
            $attrs['invoice_id'] = $context['invoice_id'];
        }

        if (isset($context['payment_method_id'])) {
            $attrs['payment_method_id'] = $context['payment_method_id'];
        }

        $payment = new Payment;
        $payment->forceFill($attrs)->save();

        return $payment->fresh();
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    /**
     * SANDBOX VERIFICATION REQUIRED — built from PayTabs docs, untested.
     *
     * Refunds re-use POST /payment/request with `tran_type=refund` and
     * the original `tran_ref`. PayTabs supports partial refunds — when
     * $amountCents is null we refund the full outstanding amount
     * (amount_cents - refunded_cents).
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        $profileId = (int) (config('billing.gateways.paytabs.profile_id') ?: $this->profileId);

        $remaining = (int) $payment->amount_cents - (int) $payment->refunded_cents;
        $refundCents = $amountCents ?? $remaining;

        if ($refundCents <= 0) {
            throw new RuntimeException('PayTabs::refund: nothing left to refund on payment '.$payment->id);
        }

        $payload = [
            'profile_id' => $profileId,
            'tran_type' => 'refund',
            'tran_class' => 'ecom',
            'tran_ref' => $payment->gateway_payment_id,
            'cart_id' => (string) ($payment->idempotency_key ?? $payment->id),
            'cart_description' => 'Refund for payment '.$payment->id,
            'cart_currency' => strtoupper((string) $payment->currency),
            'cart_amount' => number_format($refundCents / 100, 2, '.', ''),
        ];

        $response = $this->http()->post('/payment/request', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('PayTabs refund failed: '.$response->status().' '.$response->body());
        }

        $body = (array) $response->json();

        return DB::transaction(function () use ($payment, $refundCents, $body) {
            $newRefunded = (int) $payment->refunded_cents + $refundCents;

            $payment->forceFill([
                'refunded_cents' => $newRefunded,
                'status' => $newRefunded >= (int) $payment->amount_cents ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
                'metadata' => array_merge((array) $payment->metadata, ['paytabs_refund' => $body]),
            ])->save();

            return $payment->fresh();
        });
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $rawBody = $request->getContent();
        $headerSig = (string) ($request->header('Signature') ?? '');

        if (! $this->verifyIpn($rawBody, $headerSig)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'PayTabs IPN signature verification failed.',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('PayTabs IPN signature verification failed.');
        }

        try {
            $payload = $request->json()->all();
            $this->onPaytabsCallback((array) $payload);

            $event->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            Log::warning('PayTabs webhook processing failed', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event->fresh();
    }

    /**
     * Route a verified PayTabs IPN through CheckoutService when it represents
     * an Authorised (response_status='A') payment for a known CheckoutSession.
     *
     * @param  array<string, mixed>  $payload
     */
    private function onPaytabsCallback(array $payload): void
    {
        $status = (string) ($payload['payment_result']['response_status'] ?? '');
        if ($status !== 'A') {
            return;
        }

        $tranRef = (string) ($payload['tran_ref'] ?? '');
        if ($tranRef === '') {
            return;
        }

        $session = CheckoutSession::query()
            ->where('gateway', 'paytabs')
            ->where('gateway_session_id', $tranRef)
            ->first();

        if ($session === null) {
            Log::warning('PayTabs IPN: no matching CheckoutSession', ['tran_ref' => $tranRef]);

            return;
        }

        if ($session->status === CheckoutSession::STATUS_COMPLETED) {
            return;
        }

        $currency = strtoupper((string) ($payload['cart_currency'] ?? $session->currency));
        $cartAmount = $payload['cart_amount'] ?? null;
        $amountPaidCents = is_numeric($cartAmount)
            ? (int) round(((float) $cartAmount) * 100)
            : (int) $session->amount_cents;

        $checkout = app(CheckoutService::class);
        $checkout->complete($session, [
            'gateway_subscription_id' => $tranRef,
            'status' => 'active',
            'unit_amount_cents' => (int) $session->amount_cents,
            'quantity' => 1,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'gateway_invoice_id' => $tranRef,
            'invoice_number' => 'INV-'.$session->public_id,
            'gateway_payment_id' => $tranRef,
            'paid_amount_cents' => $amountPaidCents,
            'amount_paid_cents' => $amountPaidCents,
            'total_cents' => $amountPaidCents,
            'currency' => $currency,
            'payment_metadata' => ['paytabs' => $payload],
        ]);
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway
    // ------------------------------------------------------------------

    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        throw new RuntimeException('PayTabs: subscription flow not yet wired — Phase 3.2. Implement via Agreement / Repeat Billing on a tokenized card. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        throw new RuntimeException('PayTabs: subscription flow not yet wired — Phase 3.2. Implement via Agreement / Repeat Billing on a tokenized card. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        throw new RuntimeException('PayTabs: subscription flow not yet wired — Phase 3.2. Implement via Agreement / Repeat Billing on a tokenized card. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function resume(Subscription $subscription): Subscription
    {
        throw new RuntimeException('PayTabs: subscription flow not yet wired — Phase 3.2. Implement via Agreement / Repeat Billing on a tokenized card. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        throw new RuntimeException('PayTabs: subscription flow not yet wired — Phase 3.2. Implement via Agreement / Repeat Billing on a tokenized card. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    // ------------------------------------------------------------------
    // Internals reserved for Phase 3.2 wiring
    // ------------------------------------------------------------------

    /**
     * Pre-baked HTTP client for the resolved region. Kept here so the
     * Phase 3.2 implementer doesn't have to re-derive base URL + auth
     * on every call.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders($this->authHeader())
            ->acceptJson()
            ->asJson();
    }
}
