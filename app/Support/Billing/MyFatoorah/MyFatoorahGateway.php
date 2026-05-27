<?php

namespace App\Support\Billing\MyFatoorah;

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
 * MyFatoorah driver — Phase 3.3 scaffold.
 *
 * Implements PaymentGateway (one-off /v2/ExecutePayment) and
 * SubscriptionGateway (V2 Recurring Payment — daily/weekly/monthly or a
 * custom 1–180 day cadence). Charge / refund / subscription lifecycle
 * methods are intentionally stubs at this stage; the webhook signature
 * verification path is real and ready to receive callbacks today.
 *
 * Notes from MyFatoorah ops experience worth keeping near the code:
 *   - A merchant portal allows up to FIVE API tokens, one per country.
 *     Each token is bound to its country host below — using a KW token
 *     against the SA host will return 401 every time. Pick the token
 *     that matches `billing.gateways.myfatoorah.country`.
 *   - Sandbox is a single SHARED host (https://apitest.myfatoorah.com)
 *     regardless of country — the per-country split exists only for
 *     live traffic.
 *   - Webhook endpoint MUST be HTTPS; MyFatoorah will not deliver to
 *     plain HTTP. Surface this loudly during onboarding.
 *   - V2 webhooks (this driver) sign a comma-separated property list
 *     using HMAC-SHA256 base64. V1 webhooks (legacy) used a different
 *     payload shape and are NOT supported here — make sure the portal
 *     is set to "Webhook V2".
 *   - Supported rails per country: KNET (KW), Benefit (BH), Mada (SA),
 *     KFAST/STC Pay where available, plus Visa/MC/Amex everywhere.
 *
 * Auth header: `Bearer <api_token>` — the token is a long JWT-like
 * string copied verbatim from Integration Settings → API Key.
 */
class MyFatoorahGateway implements CheckoutGateway, PaymentGateway, SubscriptionGateway
{
    /** ISO 4217 codes MyFatoorah uses 3-decimal minor units for. */
    private const THREE_DECIMAL_CURRENCIES = ['KWD', 'BHD', 'OMR', 'JOD'];

    public function id(): string
    {
        return 'myfatoorah';
    }

    public function displayName(): string
    {
        return 'MyFatoorah';
    }

    // ------------------------------------------------------------------
    // CheckoutGateway
    // ------------------------------------------------------------------

    /**
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        return ['KWD', 'SAR', 'AED', 'BHD', 'QAR', 'OMR', 'JOD', 'USD', 'EUR', 'GBP', 'EGP'];
    }

    public function supportsSubscriptions(): bool
    {
        return true;
    }

    /**
     * Create a MyFatoorah invoice and return a RedirectCheckout pointing at
     * the hosted InvoiceURL. Idempotent on $session->gateway_session_id.
     */
    public function initiateCheckout(CheckoutSession $session): CheckoutResult
    {
        if ($session->gateway_session_id !== null && is_array($session->result_payload)) {
            $existingUrl = (string) ($session->result_payload['url'] ?? '');
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

        $currency = strtoupper((string) $session->currency);

        $body = [
            'CustomerName' => $session->user?->name ?? 'NA',
            'NotificationOption' => 'LNK',
            'InvoiceValue' => $this->minorToMajor((int) $session->amount_cents, $currency),
            'DisplayCurrencyIso' => $currency,
            'CustomerEmail' => $session->user?->email,
            'CallBackUrl' => route('checkout.return', ['session' => $session->public_id]),
            'ErrorUrl' => route('checkout.return', ['session' => $session->public_id]).'?canceled=1',
            'Language' => 'EN',
            'CustomerReference' => $session->public_id,
            'UserDefinedField' => $session->public_id,
        ];

        $response = $this->http()->post('/v2/SendPayment', $body);

        if (! $response->successful()) {
            throw new RuntimeException('MyFatoorah SendPayment failed: '.$response->body());
        }

        $payload = (array) $response->json();
        $data = (array) ($payload['Data'] ?? []);
        $invoiceId = (string) ($data['InvoiceId'] ?? '');
        $invoiceUrl = (string) ($data['InvoiceURL'] ?? '');

        if ($invoiceId === '' || $invoiceUrl === '') {
            throw new RuntimeException('MyFatoorah SendPayment returned no InvoiceId/InvoiceURL: '.$response->body());
        }

        $result = new RedirectCheckout(
            gatewaySessionId: $invoiceId,
            url: $invoiceUrl,
        );

        $session->forceFill([
            'gateway' => 'myfatoorah',
            'gateway_session_id' => $result->gatewaySessionId,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'result_kind' => $result->kind,
            'result_payload' => $result->toPayload(),
        ])->save();

        return $result;
    }

    /**
     * Convert integer minor-unit cents to the major-unit decimal MyFatoorah
     * expects in /v2/SendPayment payloads. KWD/BHD/OMR/JOD are 3-decimal
     * currencies (fils), everything else divides by 100.
     */
    private function minorToMajor(int $amountCents, string $currency): float
    {
        $divisor = in_array(strtoupper($currency), self::THREE_DECIMAL_CURRENCIES, true) ? 1000 : 100;

        return round($amountCents / $divisor, 3);
    }

    /**
     * Inverse of minorToMajor — turns a MyFatoorah TransactionAmount back
     * into integer minor units for our amount_paid_cents columns.
     */
    private function majorToMinor(float|int|string $major, string $currency): int
    {
        $multiplier = in_array(strtoupper($currency), self::THREE_DECIMAL_CURRENCIES, true) ? 1000 : 100;

        return (int) round(((float) $major) * $multiplier);
    }

    // ------------------------------------------------------------------
    // Region + auth helpers
    // ------------------------------------------------------------------

    /**
     * Bearer auth header — the api_token is a JWT-like string copied
     * verbatim from the merchant portal. Each portal allows up to FIVE
     * tokens (one per country); use the one bound to the configured
     * country, otherwise live calls will 401.
     *
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.config('billing.gateways.myfatoorah.api_token')];
    }

    /**
     * Country-aware API base URL.
     *
     * Sandbox is a SINGLE shared host (apitest.myfatoorah.com) — country
     * only matters in live mode. KW / BH / JO / OM share one host; UAE,
     * SA, QA and EG each have dedicated regional hosts.
     */
    protected function baseUrl(): string
    {
        $environment = (string) config('billing.gateways.myfatoorah.environment', 'test');

        if ($environment === 'test') {
            return 'https://apitest.myfatoorah.com';
        }

        $country = (string) config('billing.gateways.myfatoorah.country', 'kuwait');

        return match ($country) {
            'kuwait', 'bahrain', 'jordan', 'oman' => 'https://api.myfatoorah.com',
            'uae' => 'https://api-ae.myfatoorah.com',
            'saudi_arabia' => 'https://api-sa.myfatoorah.com',
            'qatar' => 'https://api-qa.myfatoorah.com',
            'egypt' => 'https://api-eg.myfatoorah.com',
            default => 'https://api.myfatoorah.com',
        };
    }

    // ------------------------------------------------------------------
    // Webhook signature verification (V2)
    // ------------------------------------------------------------------

    /**
     * Build the canonical signed string for an event payload.
     *
     * MyFatoorah V2 webhooks sign a comma-separated `key=value,key=value`
     * string built from a SPECIFIC ordered subset of the event's `Data`
     * properties. Nulls become empty strings. The string is then HMAC-
     * SHA256'd with the webhook secret and base64-encoded.
     *
     * If the event type is unknown to us, we return an empty string so
     * verifySignature() short-circuits to false instead of accepting an
     * unverifiable payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function signedString(string $eventType, array $data): string
    {
        $fields = match ($eventType) {
            'TransactionsStatusChanged' => [
                'Invoice.Id',
                'Invoice.Status',
                'Invoice.CustomerReference',
                'Invoice.UserDefinedField',
                'Invoice.ExternalIdentifier',
            ],
            'RefundStatusChanged' => [
                'Refund.RefundReference',
                'Refund.Status',
                'Refund.Amount',
                'Refund.RefundId',
            ],
            'BalanceTransferred' => [
                'BalanceTransferred.Reference',
                'BalanceTransferred.Amount',
            ],
            'SupplierStatusChanged' => [
                'Supplier.Reference',
                'Supplier.Status',
            ],
            'RecurringStatusChanged' => [
                'Recurring.RecurringId',
                'Recurring.Status',
                'Recurring.CustomerReference',
                'Recurring.UserDefinedField',
            ],
            default => [],
        };

        if ($fields === []) {
            return '';
        }

        $parts = [];
        foreach ($fields as $field) {
            $key = $this->lastSegment($field);
            $value = $data[$key] ?? null;
            $parts[] = $key.'='.($value === null ? '' : (string) $value);
        }

        return implode(',', $parts);
    }

    /**
     * Verify a MyFatoorah V2 webhook signature.
     *
     * Algorithm:
     *   1. Read `MyFatoorah-Signature` header (base64-encoded HMAC).
     *   2. Read JSON body → `EventType` + `Data`.
     *   3. Build comma-separated `key=value` string per event type.
     *   4. base64_encode(hash_hmac('sha256', $signed, $secret, true)).
     *   5. hash_equals against the header.
     */
    protected function verifySignature(Request $request): bool
    {
        $headerSig = (string) ($request->header('MyFatoorah-Signature') ?? '');
        $secret = (string) config('billing.gateways.myfatoorah.webhook_secret', '');

        if ($headerSig === '' || $secret === '') {
            return false;
        }

        try {
            $payload = $request->json()->all();
        } catch (Throwable) {
            return false;
        }

        $eventType = (string) ($payload['EventType'] ?? '');
        $data = (array) ($payload['Data'] ?? []);

        $signed = $this->signedString($eventType, $data);
        if ($signed === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $signed, $secret, true));

        return hash_equals($expected, $headerSig);
    }

    /**
     * Helper — strips the dotted prefix from `Invoice.Id` etc. so the
     * field name matches the key used in the flat `Data` object that
     * MyFatoorah delivers in the webhook body.
     */
    private function lastSegment(string $dotted): string
    {
        $pos = strrpos($dotted, '.');

        return $pos === false ? $dotted : substr($dotted, $pos + 1);
    }

    // ------------------------------------------------------------------
    // PaymentGateway
    // ------------------------------------------------------------------

    /**
     * SANDBOX VERIFICATION REQUIRED — built from MyFatoorah docs, untested.
     *
     * POST /v2/SendPayment — creates an invoice and returns an InvoiceURL
     * the customer is redirected to. NotificationOption=LNK skips MyFatoorah-
     * driven SMS/email and just hands back the link so we can redirect.
     *
     * Amount is sent as a DECIMAL ($amountCents / 100) and the upstream
     * `DisplayCurrencyIso` must be upper-case.
     *
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        $idempotencyKey = (string) ($context['idempotency_key'] ?? Str::uuid());

        $body = [
            'CustomerName' => $context['customer']['name'] ?? 'NA',
            'NotificationOption' => 'LNK',
            'InvoiceValue' => $amountCents / 100,
            'DisplayCurrencyIso' => strtoupper($currency),
            'CustomerEmail' => $context['customer']['email'] ?? null,
            'MobileCountryCode' => $context['customer']['mobile_country_code'] ?? null,
            'CustomerMobile' => $context['customer']['mobile'] ?? null,
            'CallBackUrl' => $context['return_url'] ?? '',
            'ErrorUrl' => $context['error_url'] ?? ($context['return_url'] ?? ''),
            'Language' => $context['language'] ?? 'EN',
            'UserDefinedField' => $idempotencyKey,
        ];

        $response = $this->http()->post('/v2/SendPayment', $body);

        if (! $response->successful()) {
            throw new RuntimeException('MyFatoorah SendPayment failed: '.$response->body());
        }

        $payload = (array) $response->json();
        $data = (array) ($payload['Data'] ?? []);
        $invoiceId = (string) ($data['InvoiceId'] ?? '');
        $invoiceUrl = (string) ($data['InvoiceURL'] ?? '');

        if ($invoiceId === '') {
            throw new RuntimeException('MyFatoorah SendPayment returned no InvoiceId: '.$response->body());
        }

        $attrs = [
            'gateway' => 'myfatoorah',
            'gateway_payment_id' => $invoiceId,
            'status' => 'pending',
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'redirect_url' => $invoiceUrl,
                'myfatoorah_response' => $data,
            ],
        ];

        if (isset($context['tenant_id'])) {
            $attrs['tenant_id'] = $context['tenant_id'];
        }
        if (isset($context['invoice_id'])) {
            $attrs['invoice_id'] = $context['invoice_id'];
        }

        if (! isset($attrs['tenant_id'])) {
            throw new RuntimeException('MyFatoorah::charge: tenant_id is required in $context for new payments.');
        }

        $payment = new Payment;
        $payment->forceFill($attrs)->save();

        return $payment->fresh();
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    /**
     * SANDBOX VERIFICATION REQUIRED — built from MyFatoorah docs, untested.
     *
     * POST /v2/MakeRefund — the `Key` is the underlying PaymentId (not the
     * InvoiceId), per MyFatoorah refund semantics. We currently store the
     * InvoiceId in `gateway_payment_id` after SendPayment, so callers may
     * need to swap it for the PaymentId resolved from a payment-status
     * lookup before calling this — flagged here as part of the sandbox
     * verification work.
     *
     * Amount is sent as a DECIMAL.
     */
    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        $refundCents = $amountCents ?? ((int) $payment->amount_cents - (int) $payment->refunded_cents);

        $body = [
            'Key' => (string) $payment->gateway_payment_id,
            'KeyType' => 'PaymentId',
            'RefundChargeOnCustomer' => false,
            'ServiceChargeOnCustomer' => false,
            'Amount' => $refundCents / 100,
        ];

        $response = $this->http()->post('/v2/MakeRefund', $body);

        if (! $response->successful()) {
            throw new RuntimeException('MyFatoorah MakeRefund failed: '.$response->body());
        }

        $payload = (array) $response->json();
        $data = (array) ($payload['Data'] ?? []);

        return DB::transaction(function () use ($payment, $refundCents, $data) {
            $newRefunded = (int) $payment->refunded_cents + $refundCents;
            $metadata = (array) ($payment->metadata ?? []);
            $metadata['myfatoorah_refund'] = $data;

            $payment->forceFill([
                'refunded_cents' => $newRefunded,
                'status' => $newRefunded >= (int) $payment->amount_cents ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
                'metadata' => $metadata,
            ])->save();

            return $payment->fresh();
        });
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        if (! $this->verifySignature($request)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'MyFatoorah webhook signature verification failed.',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('MyFatoorah webhook signature verification failed.');
        }

        try {
            $payload = (array) $request->json()->all();
            $eventType = (string) ($payload['EventType'] ?? '');
            $data = (array) ($payload['Data'] ?? []);

            if ($eventType === 'TransactionsStatusChanged' && (string) ($data['InvoiceStatus'] ?? '') === 'Paid') {
                $this->onCheckoutPaid($data);
            }

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

            Log::warning('MyFatoorah webhook processing failed', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event->fresh();
    }

    /**
     * Reconcile a TransactionsStatusChanged → Paid webhook with the local
     * CheckoutSession, delegating Subscription/Invoice/Payment creation to
     * CheckoutService::complete.
     *
     * @param  array<string, mixed>  $data  The webhook event's Data block.
     */
    private function onCheckoutPaid(array $data): void
    {
        $invoiceId = (string) ($data['InvoiceId'] ?? '');
        $publicId = (string) ($data['CustomerReference'] ?? $data['UserDefinedField'] ?? '');

        $session = null;
        if ($invoiceId !== '') {
            $session = CheckoutSession::query()
                ->where('gateway', 'myfatoorah')
                ->where('gateway_session_id', $invoiceId)
                ->first();
        }
        if ($session === null && $publicId !== '') {
            $session = CheckoutSession::query()->where('public_id', $publicId)->first();
        }

        if ($session === null) {
            Log::warning('MyFatoorah TransactionsStatusChanged: no matching CheckoutSession', [
                'invoice_id' => $invoiceId,
                'customer_reference' => $publicId,
            ]);

            return;
        }

        $currency = strtoupper((string) ($data['TransactionCurrencyIso'] ?? $session->currency));
        $txAmount = $data['TransactionAmount'] ?? $data['InvoiceTransactionValue'] ?? null;
        $amountPaidCents = $txAmount !== null
            ? $this->majorToMinor($txAmount, $currency)
            : (int) $session->amount_cents;

        app(CheckoutService::class)->complete($session, [
            'gateway_payment_id' => (string) ($data['TransactionId'] ?? $invoiceId),
            'amount_paid_cents' => $amountPaidCents,
            'paid_amount_cents' => $amountPaidCents,
            'currency' => $currency,
        ]);
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway (V2 Recurring Payment)
    // ------------------------------------------------------------------

    /**
     * SANDBOX VERIFICATION REQUIRED — built from MyFatoorah docs, untested.
     *
     * POST /v2/InitiateRecurringPayment (V2 Recurring). MyFatoorah supports
     * daily / weekly / monthly cadences or a custom 1–180 day interval. The
     * upstream returns a redirect URL the customer must visit to authorize
     * the first charge + save the card token used for subsequent renewals.
     *
     * @param  array<string, mixed>  $context
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        $idempotencyKey = (string) ($context['idempotency_key'] ?? Str::uuid());

        $body = [
            'CustomerName' => $context['customer']['name'] ?? $tenant->name ?? 'NA',
            'NotificationOption' => 'LNK',
            'InvoiceValue' => (int) $plan->price_cents / 100,
            'DisplayCurrencyIso' => strtoupper((string) $plan->currency),
            'CustomerEmail' => $context['customer']['email'] ?? null,
            'MobileCountryCode' => $context['customer']['mobile_country_code'] ?? null,
            'CustomerMobile' => $context['customer']['mobile'] ?? null,
            'CallBackUrl' => $context['return_url'] ?? '',
            'ErrorUrl' => $context['error_url'] ?? ($context['return_url'] ?? ''),
            'Language' => $context['language'] ?? 'EN',
            'UserDefinedField' => $idempotencyKey,
            'RecurringModel' => $this->mapRecurringModel($plan),
        ];

        $response = $this->http()->post('/v2/InitiateRecurringPayment', $body);

        if (! $response->successful()) {
            throw new RuntimeException('MyFatoorah InitiateRecurringPayment failed: '.$response->body());
        }

        $payload = (array) $response->json();
        $data = (array) ($payload['Data'] ?? []);

        $recurringId = (string) ($data['RecurringId'] ?? $data['InvoiceId'] ?? '');
        $redirectUrl = (string) ($data['InvoiceURL'] ?? $data['PaymentURL'] ?? '');

        if ($recurringId === '') {
            throw new RuntimeException('MyFatoorah InitiateRecurringPayment returned no RecurringId: '.$response->body());
        }

        $subscription = new Subscription;
        $subscription->forceFill([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => 'myfatoorah',
            'gateway_subscription_id' => $recurringId,
            'status' => 'pending',
            'currency' => strtoupper((string) $plan->currency),
            'unit_amount_cents' => (int) $plan->price_cents,
            'quantity' => 1,
            'metadata' => [
                'redirect_url' => $redirectUrl,
                'myfatoorah_response' => $data,
                'idempotency_key' => $idempotencyKey,
            ],
        ])->save();

        return $subscription->fresh();
    }

    /**
     * Map our internal Plan billing cadence to MyFatoorah's RecurringModel
     * payload. MyFatoorah accepts Daily / Weekly / Monthly / Custom — for
     * anything else we fall through to Monthly to keep the call valid;
     * sandbox verification should confirm the exact accepted values.
     *
     * @return array<string, mixed>
     */
    protected function mapRecurringModel(Plan $plan): array
    {
        $period = (string) $plan->billing_period;
        $interval = max(1, (int) $plan->billing_interval);

        return match ($period) {
            'day' => ['RecurringType' => 'Daily', 'IntervalDays' => $interval],
            'week' => ['RecurringType' => 'Weekly'],
            'month' => ['RecurringType' => 'Monthly'],
            'year' => ['RecurringType' => 'Custom', 'IntervalDays' => min(180, 365 * $interval)],
            default => ['RecurringType' => 'Monthly'],
        };
    }

    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        throw new RuntimeException('MyFatoorah: subscription flow not yet wired — Phase 3.3. Implement via V2 Recurring Payment (daily/weekly/monthly/custom 1-180 days). Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        throw new RuntimeException('MyFatoorah: subscription flow not yet wired — Phase 3.3. Implement via V2 Recurring Payment (daily/weekly/monthly/custom 1-180 days). Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function resume(Subscription $subscription): Subscription
    {
        throw new RuntimeException('MyFatoorah: subscription flow not yet wired — Phase 3.3. Implement via V2 Recurring Payment (daily/weekly/monthly/custom 1-180 days). Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        throw new RuntimeException('MyFatoorah: subscription flow not yet wired — Phase 3.3. Implement via V2 Recurring Payment (daily/weekly/monthly/custom 1-180 days). Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    // ------------------------------------------------------------------
    // Internals reserved for Phase 3.3 wiring
    // ------------------------------------------------------------------

    /**
     * Pre-baked HTTP client for the resolved country/environment host.
     * Kept here so the Phase 3.3 implementer doesn't have to re-derive
     * base URL + auth on every call.
     */
    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders($this->authHeaders())
            ->acceptJson()
            ->asJson();
    }
}
