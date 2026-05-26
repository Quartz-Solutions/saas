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
use Illuminate\Support\Facades\Http;
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

    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('Fawry: charge/refund flow not yet wired — Phase 3.2. Implement via Create Payment Request API (returns fawryRefNumber valid ~30 days for kiosk + online). Docs: https://developer.fawrystaging.com/docs-home');
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
