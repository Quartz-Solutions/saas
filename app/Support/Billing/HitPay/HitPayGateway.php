<?php

namespace App\Support\Billing\HitPay;

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
 * HitPay driver — Phase 3.4 scaffold.
 *
 * Implements PaymentGateway (one-off charges via Payment Requests) and
 * SubscriptionGateway (HitPay Recurring Billing — Plans + Subscriptions).
 *
 * Uses ONLY the Illuminate HTTP client — the official `hit-pay/hitpay_php_wrapper`
 * package is intentionally NOT installed. Calls go to the REST API directly so
 * we stay vendor-thin and can swap the transport in tests via `Http::fake()`.
 *
 * Notes worth keeping near the code:
 *   - HitPay markets "150+ currencies" at checkout, but settlement is limited
 *     to the SGD / AUD / CAD / CHF / CNH / EUR / GBP / HKD / JPY / NOK / NZD /
 *     SEK / USD set. Currency conversion happens on HitPay's side at capture.
 *   - The HitPay API speaks DECIMAL strings (e.g. `"10.00"`), NOT integer
 *     cents. The boilerplate's canonical unit is integer cents, so this driver
 *     converts at the edge via `toDecimalString()`.
 *   - An order MUST only be marked paid AFTER the webhook signature verifies.
 *     The browser redirect carries no proof of payment and can be replayed —
 *     trust the webhook, not the redirect.
 *   - Supported rails on a Payment Request: FPX, DuitNow, PayNow, Atome,
 *     GrabPay, ShopeePay, AliPay, WeChat Pay, and cards (Visa / MC / Amex).
 *   - There are TWO live webhook schemes in production today:
 *       (a) Modern "Event Webhooks" — JSON body + `HITPAY-Signature` header
 *           which is `hash_hmac('sha256', $rawBody, $salt)`.
 *       (b) Legacy Payment Request callbacks — form-encoded body with an
 *           `hmac` field; signature is HMAC-SHA256 over the alphabetically
 *           sorted `key.value` concatenation of every OTHER field.
 *     This driver implements both and dispatches based on which signal is
 *     present on the inbound Request.
 */
class HitPayGateway implements PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'hitpay';
    }

    public function displayName(): string
    {
        return 'HitPay';
    }

    // ------------------------------------------------------------------
    // Auth + transport
    // ------------------------------------------------------------------

    /**
     * HitPay auth headers.
     *
     * `X-Requested-With: XMLHttpRequest` is REQUIRED — without it the API
     * returns the marketing site's HTML instead of JSON.
     *
     * `X-PLATFORM-KEY` is only included when the account is a HitPay
     * marketplace/platform parent — leave the config null for normal
     * direct-merchant integrations.
     *
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        $headers = [
            'X-BUSINESS-API-KEY' => (string) config('billing.gateways.hitpay.api_key'),
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $platformKey = config('billing.gateways.hitpay.platform_key');
        if (is_string($platformKey) && $platformKey !== '') {
            $headers['X-PLATFORM-KEY'] = $platformKey;
        }

        return $headers;
    }

    /**
     * Mode-aware API base URL. HitPay's sandbox uses a separate host (it is
     * NOT a query-string flag like some other gateways).
     */
    protected function baseUrl(): string
    {
        $mode = (string) (config('billing.gateways.hitpay.mode') ?: 'sandbox');

        return match (strtolower($mode)) {
            'live', 'production' => 'https://api.hit-pay.com/v1/',
            default => 'https://api.sandbox.hit-pay.com/v1/',
        };
    }

    /**
     * Pre-baked HTTP client for the resolved environment. Phase 3.4
     * implementers should call this from charge/refund/subscription
     * methods instead of re-deriving base URL + headers each time.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders($this->authHeaders())
            ->acceptJson()
            ->asForm();
    }

    /**
     * Convert integer cents (boilerplate convention) into the decimal-string
     * format HitPay expects (e.g. `1050` cents → `"10.50"`).
     */
    protected function toDecimalString(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    // ------------------------------------------------------------------
    // Webhook signature verification
    // ------------------------------------------------------------------

    /**
     * Dispatch verifier — branches on signal:
     *   - Header `HITPAY-Signature` present → modern Event Webhook path
     *   - Form field `hmac` present         → legacy Payment Request path
     *   - Neither                           → reject
     */
    protected function verify(Request $request): bool
    {
        $modernSig = $request->header('HITPAY-Signature')
            ?? ($_SERVER['HTTP_HITPAY_SIGNATURE'] ?? null);

        if (is_string($modernSig) && $modernSig !== '') {
            return $this->verifyEventWebhook($request);
        }

        $payload = $request->all();
        if (is_array($payload) && array_key_exists('hmac', $payload)) {
            return $this->verifyLegacyWebhook($payload);
        }

        return false;
    }

    /**
     * Modern Event Webhooks (recommended).
     *
     * Algorithm: HMAC-SHA256 over the RAW request body, keyed with the
     * configured salt. Compared via hash_equals to the `HITPAY-Signature`
     * header.
     *
     * IMPORTANT: must hash the RAW body (Request::getContent()) — re-encoding
     * via json_encode() is NOT byte-identical and the signature will not match.
     */
    protected function verifyEventWebhook(Request $request): bool
    {
        $salt = (string) config('billing.gateways.hitpay.salt');
        $headerSig = (string) ($request->header('HITPAY-Signature')
            ?? ($_SERVER['HTTP_HITPAY_SIGNATURE'] ?? ''));

        if ($salt === '' || $headerSig === '') {
            return false;
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $rawBody, $salt);

        return hash_equals($expected, $headerSig);
    }

    /**
     * Legacy Payment Request callback (form-encoded).
     *
     * Algorithm:
     *   1) Take every field EXCEPT `hmac`.
     *   2) Sort by key, alphabetically (ksort).
     *   3) Concatenate as `key.value` strings with NO separator between pairs
     *      (e.g. `amount10.50currencySGDstatuscompleted`).
     *   4) HMAC-SHA256 with the merchant salt.
     *   5) Compare with hash_equals against `$payload['hmac']`.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function verifyLegacyWebhook(array $payload): bool
    {
        $salt = (string) config('billing.gateways.hitpay.salt');
        $providedHmac = (string) ($payload['hmac'] ?? '');

        if ($salt === '' || $providedHmac === '') {
            return false;
        }

        $fields = $payload;
        unset($fields['hmac']);
        ksort($fields);

        $concat = '';
        foreach ($fields as $key => $value) {
            $concat .= $key.'.'.(is_scalar($value) ? (string) $value : json_encode($value));
        }

        $expected = hash_hmac('sha256', $concat, $salt);

        return hash_equals($expected, $providedHmac);
    }

    // ------------------------------------------------------------------
    // PaymentGateway
    // ------------------------------------------------------------------

    /**
     * Create a HitPay Payment Request and persist a pending Payment row.
     *
     * SANDBOX VERIFICATION REQUIRED — built from HitPay docs, untested.
     *
     * HitPay accepts an x-www-form-urlencoded body at POST /payment-requests
     * and returns JSON with `id` + `url`. The hosted `url` is the customer-
     * facing checkout page; the Payment row stores it on metadata so the
     * caller can redirect the browser there.
     *
     * Important: HitPay expects DECIMAL amounts ("10.00"), NOT integer cents.
     * Conversion happens here via {@see self::toDecimalString()}.
     *
     * The `reference_number` field doubles as our idempotency key — callers
     * may pass `$context['idempotency_key']`, otherwise a fresh UUID is
     * generated. The resulting Payment row's `idempotency_key` column
     * mirrors that value so a repeat call from the BillingService layer
     * surfaces the same upstream record.
     *
     * @param  array<string, mixed>  $context  Free-form bag. Recognised keys:
     *                                         - tenant_id        — bound to the Payment row
     *                                         - invoice_id       — bound to the Payment row
     *                                         - idempotency_key  — also sent as reference_number
     *                                         - customer.email   — buyer email (optional)
     *                                         - customer.name    — buyer name (optional)
     *                                         - description      — HitPay "purpose" line
     *                                         - return_url       — browser redirect_url
     *                                         - callback_url     — server webhook url
     *
     * Docs: https://docs.hitpayapp.com/apis/guide/online-payments
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from HitPay docs, untested
        $reference = (string) ($context['idempotency_key'] ?? Str::uuid());

        $body = [
            'amount' => $this->toDecimalString($amountCents),
            'currency' => strtoupper($currency),
            'reference_number' => $reference,
            'email' => $context['customer']['email'] ?? null,
            'name' => $context['customer']['name'] ?? null,
            'purpose' => $context['description'] ?? 'Subscription',
            'redirect_url' => $context['return_url'] ?? '',
            'webhook' => $context['callback_url'] ?? '',
            'send_email' => false,
            'send_sms' => false,
            'allow_repeated_payments' => false,
        ];

        $response = $this->http()
            ->post('payment-requests', $body)
            ->throw()
            ->json();

        $paymentRequestId = (string) ($response['id'] ?? '');
        if ($paymentRequestId === '') {
            throw new RuntimeException('HitPay: create-payment-request response missing id');
        }

        return Payment::create([
            'tenant_id' => $context['tenant_id'] ?? null,
            'invoice_id' => $context['invoice_id'] ?? null,
            'gateway' => $this->id(),
            'gateway_payment_id' => $paymentRequestId,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'idempotency_key' => $reference,
            'metadata' => [
                'redirect_url' => (string) ($response['url'] ?? ''),
                'reference_number' => $reference,
                'raw_response' => $response,
            ],
        ]);
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('HitPay: charge/refund flow not yet wired — Phase 3.4. Implement via POST /v1/payment-requests (amount as decimal string, not cents). FPX/DuitNow/PayNow/Atome/cards supported. Docs: https://docs.hitpayapp.com/introduction');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('HitPay: charge/refund flow not yet wired — Phase 3.4. Implement via POST /v1/payment-requests (amount as decimal string, not cents). FPX/DuitNow/PayNow/Atome/cards supported. Docs: https://docs.hitpayapp.com/introduction');
    }

    /**
     * Refund a captured HitPay charge (full or partial).
     *
     * SANDBOX VERIFICATION REQUIRED — built from HitPay docs, untested.
     *
     * HitPay refunds attach to the underlying CHARGE id (not the
     * payment-request id). The charge id is delivered on the webhook and
     * SHOULD be persisted onto `Payment::metadata.charge_id` by the
     * webhook handler. We fall back to `gateway_payment_id` if the caller
     * never recorded a separate charge id.
     *
     * Endpoint: POST {baseUrl}/charges/{payment_id}/refunds
     * Body:   amount (decimal string)
     *
     * Docs: https://docs.hitpayapp.com/apis/guide/online-payments
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        // SANDBOX VERIFICATION REQUIRED — built from HitPay docs, untested
        $chargeId = (string) ($payment->metadata['charge_id'] ?? $payment->gateway_payment_id ?? '');
        if ($chargeId === '') {
            throw new RuntimeException('HitPay: cannot refund — missing charge id on Payment#'.$payment->id);
        }

        $alreadyRefunded = (int) ($payment->refunded_cents ?? 0);
        $remaining = (int) $payment->amount_cents - $alreadyRefunded;
        $refundCents = $amountCents ?? $remaining;

        $body = [
            'amount' => $this->toDecimalString($refundCents),
        ];

        $response = $this->http()
            ->post('charges/'.$chargeId.'/refunds', $body)
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
        throw new RuntimeException('HitPay: charge/refund flow not yet wired — Phase 3.4. Implement via POST /v1/payment-requests (amount as decimal string, not cents). FPX/DuitNow/PayNow/Atome/cards supported. Docs: https://docs.hitpayapp.com/introduction');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('HitPay: charge/refund flow not yet wired — Phase 3.4. Implement via POST /v1/payment-requests (amount as decimal string, not cents). FPX/DuitNow/PayNow/Atome/cards supported. Docs: https://docs.hitpayapp.com/introduction');
    }

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        if (! $this->verify($request)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'HitPay webhook signature verification failed.',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('HitPay webhook signature verification failed.');
        }

        $event->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway
    // ------------------------------------------------------------------

    /**
     * Create a HitPay Recurring Billing subscription.
     *
     * SANDBOX VERIFICATION REQUIRED — built from HitPay docs, untested.
     *
     * HitPay's recurring engine is a two-step model: first a "plan" object
     * must exist (created via /recurring-billing/plans, normally synced
     * by the PlanService), then a subscription is opened against that
     * plan via POST /recurring-billing.
     *
     * The plan id MUST be stored on `Plan::gateway_ids['hitpay']` ahead of
     * this call (PlanService handles that). The tenant's billing email is
     * resolved off the owner (mirrors the Stripe driver's
     * {@see StripeGateway::ensureCustomer()} contract).
     *
     * Body fields (per docs):
     *   - plan_id          — HitPay plan id
     *   - customer_email   — buyer email
     *   - start_date       — YYYY-MM-DD (today by default)
     *   - redirect_url     — browser return after card setup
     *
     * Docs: https://docs.hitpayapp.com/apis/guide/recurring-billing
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        // SANDBOX VERIFICATION REQUIRED — built from HitPay docs, untested
        $planId = (string) ($plan->gateway_ids['hitpay'] ?? '');
        if ($planId === '') {
            throw new RuntimeException('HitPay: Plan#'.$plan->id.' is missing gateway_ids.hitpay — sync the plan to HitPay first.');
        }

        $email = (string) ($context['customer_email'] ?? $tenant->owner?->email ?? '');
        if ($email === '') {
            throw new RuntimeException('HitPay: tenant has no billing email — cannot start subscription.');
        }

        $body = [
            'plan_id' => $planId,
            'customer_email' => $email,
            'start_date' => (string) ($context['start_date'] ?? now()->toDateString()),
            'redirect_url' => (string) ($context['return_url'] ?? ''),
        ];

        $response = $this->http()
            ->post('recurring-billing', $body)
            ->throw()
            ->json();

        $subscriptionId = (string) ($response['id'] ?? '');
        if ($subscriptionId === '') {
            throw new RuntimeException('HitPay: create-subscription response missing id');
        }

        $attrs = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => $this->id(),
            'gateway_subscription_id' => $subscriptionId,
            'status' => (string) ($response['status'] ?? 'incomplete'),
            'currency' => strtoupper((string) $plan->currency),
            'unit_amount_cents' => (int) $plan->price_cents,
            'quantity' => 1,
            'metadata' => [
                'url' => (string) ($response['url'] ?? ''),
                'raw_response' => $response,
            ],
        ];

        $existing = Subscription::query()
            ->where('gateway', $this->id())
            ->where('gateway_subscription_id', $subscriptionId)
            ->first();

        if ($existing !== null) {
            $existing->forceFill($attrs)->save();

            return $existing->fresh();
        }

        $subscription = new Subscription;
        $subscription->forceFill($attrs)->save();

        return $subscription->fresh();
    }

    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        throw new RuntimeException('HitPay: subscription flow not yet wired — Phase 3.4. Implement via /v1/recurring-billing/plans + /v1/recurring-billing (start_date + customer_email required). Docs: https://docs.hitpayapp.com/apis/guide/recurring-billing');
    }

    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        throw new RuntimeException('HitPay: subscription flow not yet wired — Phase 3.4. Implement via /v1/recurring-billing/plans + /v1/recurring-billing (start_date + customer_email required). Docs: https://docs.hitpayapp.com/apis/guide/recurring-billing');
    }

    public function resume(Subscription $subscription): Subscription
    {
        throw new RuntimeException('HitPay: subscription flow not yet wired — Phase 3.4. Implement via /v1/recurring-billing/plans + /v1/recurring-billing (start_date + customer_email required). Docs: https://docs.hitpayapp.com/apis/guide/recurring-billing');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        throw new RuntimeException('HitPay: subscription flow not yet wired — Phase 3.4. Implement via /v1/recurring-billing/plans + /v1/recurring-billing (start_date + customer_email required). Docs: https://docs.hitpayapp.com/apis/guide/recurring-billing');
    }
}
