<?php

namespace App\Support\Billing\MyFatoorah;

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
class MyFatoorahGateway implements PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'myfatoorah';
    }

    public function displayName(): string
    {
        return 'MyFatoorah';
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

    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('MyFatoorah: charge/refund flow not yet wired — Phase 3.3. Implement via /v2/ExecutePayment (hosted or embedded) + /v2/MakeRefund. KNET (KW), Benefit (BH), Mada (SA), STC Pay supported. Docs: https://docs.myfatoorah.com/docs/get-started');
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

        $event->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway (V2 Recurring Payment)
    // ------------------------------------------------------------------

    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        throw new RuntimeException('MyFatoorah: subscription flow not yet wired — Phase 3.3. Implement via V2 Recurring Payment (daily/weekly/monthly/custom 1-180 days). Docs: https://docs.myfatoorah.com/docs/get-started');
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
