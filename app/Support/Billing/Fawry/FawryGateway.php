<?php

namespace App\Support\Billing\Fawry;

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
 * Fawry driver — Phase 3.2 scaffold.
 *
 * Fawry is an Egypt-only payment network (kiosks, wallets, online cards).
 *
 * Quirks worth keeping in mind:
 *  - Currency: EGP only. Cross-currency charges are not supported.
 *  - Settlement: ~5 business days after a kiosk payment clears.
 *  - Callback (server-notification V2) URL is configured once at merchant
 *    onboarding — it is NOT supplied per-request. For multi-tenant routing
 *    we must route on the inbound payload (merchantRefNum prefix etc.), not
 *    on a per-tenant callback URL.
 *  - Subscriptions are not a first-class concept. Two paths exist:
 *      (a) "Pay-By-Link recurring invoices" — Fawry generates the next
 *          invoice each period and emails / SMSes the customer a new link.
 *      (b) MIT (merchant-initiated) charges against a tokenized card.
 *
 * HTTP is done through Illuminate\Support\Facades\Http — there is no
 * official composer SDK in active maintenance, so we sign/verify by hand.
 */
class FawryGateway implements PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'fawry';
    }

    public function displayName(): string
    {
        return 'Fawry';
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs
    // ------------------------------------------------------------------

    /**
     * SANDBOX VERIFICATION REQUIRED — built from Fawry docs, untested.
     *
     * Pay-via-Kiosk reference flow. POSTs to
     * `{baseUrl}/ECommerceWeb/Fawry/payments/charge` and persists a Payment
     * row in `pending` status. The response `referenceNumber` is the FawryRef
     * the customer uses at a kiosk / wallet to settle the order; final
     * confirmation arrives later via Server Notification V2.
     *
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from Fawry docs, untested.
        $merchantCode = (string) config('billing.gateways.fawry.merchant_code');
        $merchantRefNum = (string) ($context['idempotency_key'] ?? Str::uuid());
        $amount = $this->formatAmount($amountCents / 100);
        $returnUrl = (string) ($context['return_url'] ?? '');
        $paymentExpiry = 30 * 24 * 3600 * 1000; // 30 days in ms.

        $chargeItems = $context['items'] ?? [[
            'itemId' => '1',
            'description' => 'Subscription',
            'price' => $amount,
            'quantity' => 1,
        ]];

        $body = [
            'merchantCode' => $merchantCode,
            'merchantRefNum' => $merchantRefNum,
            'customerName' => $context['customer']['name'] ?? 'NA',
            'customerMobile' => $context['customer']['mobile'] ?? '01000000000',
            'customerEmail' => $context['customer']['email'] ?? '',
            'amount' => $amount,
            'currencyCode' => 'EGP',
            'chargeItems' => $chargeItems,
            'returnUrl' => $returnUrl,
            'paymentExpiry' => $paymentExpiry,
            'paymentMethod' => 'PAYATFAWRY',
            'signature' => $this->signRequest([
                $merchantCode,
                $merchantRefNum,
                '',             // customerProfileId
                'PAYATFAWRY',   // paymentMethod
                $amount,        // amount (2dp)
                '',             // cardNumber
                '',             // cardExpiryYear
                '',             // cardExpiryMonth
                '',             // cvv
                $returnUrl,
            ]),
        ];

        $response = $this->http()
            ->post('/ECommerceWeb/Fawry/payments/charge', $body)
            ->throw()
            ->json();

        $referenceNumber = (string) ($response['referenceNumber'] ?? '');
        if ($referenceNumber === '') {
            throw new RuntimeException('Fawry charge: missing referenceNumber in response — '.json_encode($response));
        }

        if (! isset($context['tenant_id'])) {
            throw new RuntimeException('Fawry charge: tenant_id is required in $context for new payments.');
        }

        $attrs = [
            'tenant_id' => $context['tenant_id'],
            'gateway' => 'fawry',
            'gateway_payment_id' => $referenceNumber,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'refunded_cents' => 0,
            'currency' => strtoupper($currency),
            'idempotency_key' => $merchantRefNum,
            'metadata' => [
                'fawry_ref' => $referenceNumber,
                'merchant_ref_num' => $merchantRefNum,
                'expiry' => $response['expirationTime'] ?? $paymentExpiry,
                'status_code' => $response['statusCode'] ?? null,
                'status_description' => $response['statusDescription'] ?? null,
            ],
        ];

        if (isset($context['invoice_id'])) {
            $attrs['invoice_id'] = $context['invoice_id'];
        }

        return DB::transaction(function () use ($attrs) {
            $payment = new Payment;
            $payment->forceFill($attrs)->save();

            return $payment->fresh();
        });
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
    }

    /**
     * SANDBOX VERIFICATION REQUIRED — built from Fawry docs, untested.
     *
     * POSTs to `{baseUrl}/ECommerceWeb/Fawry/payments/refund` with the
     * FawryRef (gateway_payment_id), refund amount, and a signature over
     * merchantCode + referenceNumber + refundAmount(2dp) + (reason ?? '')
     * + secureKey.
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from Fawry docs, untested.
        $merchantCode = (string) config('billing.gateways.fawry.merchant_code');
        $referenceNumber = (string) $payment->gateway_payment_id;
        $refundCents = $amountCents ?? ((int) $payment->amount_cents - (int) $payment->refunded_cents);
        $refundAmount = $this->formatAmount($refundCents / 100);
        $reason = (string) ($payment->metadata['refund_reason'] ?? '');

        $body = [
            'merchantCode' => $merchantCode,
            'referenceNumber' => $referenceNumber,
            'refundAmount' => $refundAmount,
            'reason' => $reason,
            'signature' => $this->signRequest([
                $merchantCode,
                $referenceNumber,
                $refundAmount,
                $reason,
            ]),
        ];

        $this->http()
            ->post('/ECommerceWeb/Fawry/payments/refund', $body)
            ->throw();

        return DB::transaction(function () use ($payment, $refundCents) {
            $newRefunded = (int) $payment->refunded_cents + $refundCents;
            $payment->forceFill([
                'refunded_cents' => $newRefunded,
                'status' => $newRefunded >= (int) $payment->amount_cents ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
            ])->save();

            return $payment->fresh();
        });
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
    }

    // ------------------------------------------------------------------
    // Webhook handler — REAL
    // ------------------------------------------------------------------

    /**
     * Fawry Server Notification V2. Body is JSON containing a `signature`
     * field that is a SHA-256 hash over an ordered concat of payload fields
     * + the merchant `secureKey`. Fawry retries until we return HTTP 200,
     * so the wrapping route must always 200 on processed events.
     */
    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $payload = $request->json()->all();

        if (! $this->verifyNotification($payload)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'Fawry signature verification failed',
            ])->save();

            throw new RuntimeException('Fawry signature verification failed');
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
        throw new RuntimeException('Fawry: subscriptions not yet wired — Phase 3.2. Two paths: (a) Pay-By-Link recurring invoices, (b) MIT charges on tokenized cards. Docs: https://developer.fawrystaging.com/docs/pay-by-link-recurring-invoice');
    }

    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        throw new RuntimeException('Fawry: subscriptions not yet wired — Phase 3.2. Two paths: (a) Pay-By-Link recurring invoices, (b) MIT charges on tokenized cards. Docs: https://developer.fawrystaging.com/docs/pay-by-link-recurring-invoice');
    }

    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        throw new RuntimeException('Fawry: subscriptions not yet wired — Phase 3.2. Two paths: (a) Pay-By-Link recurring invoices, (b) MIT charges on tokenized cards. Docs: https://developer.fawrystaging.com/docs/pay-by-link-recurring-invoice');
    }

    public function resume(Subscription $subscription): Subscription
    {
        throw new RuntimeException('Fawry: subscriptions not yet wired — Phase 3.2. Two paths: (a) Pay-By-Link recurring invoices, (b) MIT charges on tokenized cards. Docs: https://developer.fawrystaging.com/docs/pay-by-link-recurring-invoice');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        throw new RuntimeException('Fawry: subscriptions not yet wired — Phase 3.2. Two paths: (a) Pay-By-Link recurring invoices, (b) MIT charges on tokenized cards. Docs: https://developer.fawrystaging.com/docs/pay-by-link-recurring-invoice');
    }

    // ------------------------------------------------------------------
    // Signing + verification — REAL
    // ------------------------------------------------------------------

    /**
     * Build a Fawry request signature.
     *
     * Fawry requires SHA-256 over a fixed-order concatenation of specific
     * request fields followed by the merchant `secureKey`. The exact field
     * order VARIES per endpoint — callers MUST pass values in the order
     * specified by each endpoint's docs.
     *
     * Examples of documented orderings:
     *   Charge Request:
     *     merchantCode + merchantRefNum + customerProfileId + paymentMethod
     *     + amount(2dp) + cardNumber + cardExpiryYear + cardExpiryMonth
     *     + cvv + returnUrl + secureKey
     *
     *   Refund Request:
     *     merchantCode + referenceNumber + refundAmount(2dp)
     *     + (reason ?? '') + secureKey
     *
     *   Pay With Card Token (3DS):
     *     merchantCode + merchantRefNum + customerProfileId + paymentMethod
     *     + amount(2dp) + cardToken + cvv + returnUrl + secureKey
     *
     * Pass amount values pre-formatted via {@see self::formatAmount()}.
     *
     * @param  array<int, string|int|float|null>  $orderedValues
     */
    protected function signRequest(array $orderedValues): string
    {
        return hash('sha256', implode('', $orderedValues).config('billing.gateways.fawry.secure_key'));
    }

    /**
     * Verify a Server Notification V2 callback signature.
     *
     * The expected concat order for the notification signature is:
     *   fawryRefNumber
     *   + merchantRefNum
     *   + paymentAmount (2dp)
     *   + orderAmount   (2dp)
     *   + orderStatus
     *   + paymentMethod
     *   + paymentReferenceNumber (empty string when this is an order-creation event,
     *     i.e. before the customer has paid at a kiosk / wallet)
     *   + secureKey
     *
     * Amount formatting follows Fawry's spec: keep the decimal point, drop
     * thousand separators — number_format($v, 2, '.', '').
     *
     * @param  array<string, mixed>  $payload
     */
    protected function verifyNotification(array $payload): bool
    {
        $provided = (string) ($payload['signature'] ?? $payload['messageSignature'] ?? '');

        if ($provided === '') {
            return false;
        }

        $concat = implode('', [
            (string) ($payload['fawryRefNumber'] ?? ''),
            (string) ($payload['merchantRefNumber'] ?? $payload['merchantRefNum'] ?? ''),
            $this->formatAmount($payload['paymentAmount'] ?? 0),
            $this->formatAmount($payload['orderAmount'] ?? 0),
            (string) ($payload['orderStatus'] ?? ''),
            (string) ($payload['paymentMethod'] ?? ''),
            (string) ($payload['paymentReferenceNumber'] ?? ''),
        ]);

        $expected = hash('sha256', $concat.config('billing.gateways.fawry.secure_key'));

        return hash_equals($expected, $provided);
    }

    /**
     * Format a monetary value the way Fawry expects in signatures:
     * two decimal places, dot separator, no thousand separators.
     */
    protected function formatAmount(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    // ------------------------------------------------------------------
    // Environment / base URL — REAL
    // ------------------------------------------------------------------

    /**
     * Base URL for Fawry's REST APIs, switched by environment.
     *
     *  - staging    → https://atfawry.fawrystaging.com
     *  - production → https://www.atfawry.com
     */
    protected function baseUrl(): string
    {
        $env = (string) config('billing.gateways.fawry.environment', 'staging');

        return match ($env) {
            'production' => 'https://www.atfawry.com',
            default => 'https://atfawry.fawrystaging.com',
        };
    }

    /**
     * Pre-configured Http client pointed at the right Fawry host. Use this
     * once the real charge/refund flows are wired in Phase 3.2.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson();
    }
}
