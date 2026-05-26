<?php

namespace App\Support\Billing\Telr;

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
 * Telr driver — Phase 3.3 scaffold.
 *
 * Implements PaymentGateway and SubscriptionGateway. Telr supports recurring
 * charges via "Repeat Billing" / "Agreement" on a previously stored card
 * token, so this driver claims the SubscriptionGateway seam even though the
 * lifecycle methods are still stubs.
 *
 * Notes worth keeping near the code:
 *   - Supported rails on a single integration: Mada, Apple Pay, Google Pay,
 *     Samsung Pay, and Tabby BNPL (in addition to Visa/MC/Amex/etc).
 *   - Orders created through /gateway/order.json EXPIRE 30 minutes after
 *     creation. If the customer doesn't reach the auth page in time, the
 *     order must be re-created.
 *   - The new REST API uses a SINGLE host (https://secure.telr.com). There
 *     is no separate sandbox hostname — test mode is selected by the
 *     `ivp_test` request param: 0 = live, 1 = test (auth always approves),
 *     2 = test (auth always declines).
 *   - The IPN signature is plain SHA1 of `secret:f1:f2:…`, NOT HMAC.
 *     Missing fields are sent as empty strings — the colon separators
 *     stay so positional alignment is preserved. Values must be
 *     percent-decoded BEFORE being concatenated for hashing.
 *
 * Auth: REST endpoints use HTTP Basic auth — username = store_id,
 * password = auth_key. See authPair() / http().
 */
class TelrGateway implements PaymentGateway, SubscriptionGateway
{
    /**
     * Single REST host. Test mode is selected per-request via `ivp_test`
     * (0 = live, 1 = approve-all, 2 = decline-all) — NOT via hostname.
     */
    protected const BASE_URL = 'https://secure.telr.com/gateway/order.json';

    public function id(): string
    {
        return 'telr';
    }

    public function displayName(): string
    {
        return 'Telr';
    }

    // ------------------------------------------------------------------
    // Auth + HTTP helpers
    // ------------------------------------------------------------------

    /**
     * HTTP Basic credentials for the Telr REST endpoints.
     *
     * Use as:  Http::withBasicAuth(...$this->authPair())->post(...).
     *
     * @return array{0: string, 1: string} [store_id, auth_key]
     */
    protected function authPair(): array
    {
        return [
            (string) config('billing.gateways.telr.store_id'),
            (string) config('billing.gateways.telr.auth_key'),
        ];
    }

    /**
     * Pre-baked HTTP client pointed at the Telr REST host with Basic auth
     * applied. Kept here so the Phase 3.3 implementer doesn't have to
     * re-derive base URL + auth on every call.
     */
    protected function http(): PendingRequest
    {
        [$storeId, $authKey] = $this->authPair();

        return Http::withBasicAuth($storeId, $authKey)
            ->acceptJson()
            ->asJson();
    }

    // ------------------------------------------------------------------
    // IPN signature verification (plain SHA1, NOT HMAC)
    // ------------------------------------------------------------------

    /**
     * Shared secret used to compute the `*_check` hashes on incoming IPNs.
     * Configured in the Telr merchant dashboard under "IPN secret".
     */
    protected function ipnSecret(): string
    {
        return (string) config('billing.gateways.telr.ipn_secret');
    }

    /**
     * Compute the expected `tran_check` for an inbound IPN payload.
     *
     * Algorithm — plain SHA1 (NOT HMAC) over the literal string:
     *
     *   secret : tran_store : tran_type : tran_class : tran_test :
     *   tran_ref : tran_prevref : tran_firstref : tran_currency :
     *   tran_amount : tran_cartid : tran_desc : tran_status :
     *   tran_authcode : tran_authmessage
     *
     * Rules:
     *   - Missing fields become empty strings — DO NOT skip them. The
     *     colon separators must stay so positional alignment is preserved.
     *   - Values arrive percent-encoded in the POST body. They MUST be
     *     percent-decoded BEFORE being concatenated, otherwise the hash
     *     will not match Telr's.
     *
     * Other `*_check` field families exist on different IPN flows and are
     * computed the same way but over DIFFERENT field lists. Implement
     * them when the corresponding flow is wired:
     *
     *   - card_check       — card token / Stored Card lifecycle IPNs
     *   - bill_check       — billing-address-update IPNs
     *   - acquirer_check   — acquirer-side response IPNs
     *   - agreement_check  — Repeat Billing / Agreement IPNs  (needed for subscriptions)
     *   - account_check    — merchant account status IPNs
     *
     * TODO(Phase 3.3): implement the above sibling checkers alongside
     * tran_check once the subscription + tokenization flows are wired.
     *
     * @param  array<string, mixed>  $payload  Raw POST body of the IPN.
     */
    protected function computeTranCheck(array $payload): string
    {
        $fields = [
            'tran_store',
            'tran_type',
            'tran_class',
            'tran_test',
            'tran_ref',
            'tran_prevref',
            'tran_firstref',
            'tran_currency',
            'tran_amount',
            'tran_cartid',
            'tran_desc',
            'tran_status',
            'tran_authcode',
            'tran_authmessage',
        ];

        $parts = [$this->ipnSecret()];

        foreach ($fields as $field) {
            $value = $payload[$field] ?? '';
            // Telr percent-encodes IPN field values; decode BEFORE hashing.
            $parts[] = is_string($value) ? rawurldecode($value) : (string) $value;
        }

        return sha1(implode(':', $parts));
    }

    /**
     * Verify the `tran_check` field on an inbound IPN against a freshly
     * computed expected value. Constant-time compared.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function verifyTranCheck(array $payload): bool
    {
        $received = strtolower((string) ($payload['tran_check'] ?? ''));
        $expected = strtolower($this->computeTranCheck($payload));

        if ($received === '' || $expected === '') {
            return false;
        }

        return hash_equals($received, $expected);
    }

    // ------------------------------------------------------------------
    // PaymentGateway
    // ------------------------------------------------------------------

    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('Telr: charge/refund flow not yet wired — Phase 3.3. Implement via REST Create Order /gateway/order.json (returns _links.auth for redirect). Statuses: PENDING/AUTHORISED/PAID/CANCELLED/DECLINED. Docs: https://docs.telr.com/reference/introduction');
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('Telr: charge/refund flow not yet wired — Phase 3.3. Implement via REST Create Order /gateway/order.json (returns _links.auth for redirect). Statuses: PENDING/AUTHORISED/PAID/CANCELLED/DECLINED. Docs: https://docs.telr.com/reference/introduction');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('Telr: charge/refund flow not yet wired — Phase 3.3. Implement via REST Create Order /gateway/order.json (returns _links.auth for redirect). Statuses: PENDING/AUTHORISED/PAID/CANCELLED/DECLINED. Docs: https://docs.telr.com/reference/introduction');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('Telr: charge/refund flow not yet wired — Phase 3.3. Implement via REST Create Order /gateway/order.json (returns _links.auth for redirect). Statuses: PENDING/AUTHORISED/PAID/CANCELLED/DECLINED. Docs: https://docs.telr.com/reference/introduction');
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException('Telr: charge/refund flow not yet wired — Phase 3.3. Implement via REST Create Order /gateway/order.json (returns _links.auth for redirect). Statuses: PENDING/AUTHORISED/PAID/CANCELLED/DECLINED. Docs: https://docs.telr.com/reference/introduction');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('Telr: charge/refund flow not yet wired — Phase 3.3. Implement via REST Create Order /gateway/order.json (returns _links.auth for redirect). Statuses: PENDING/AUTHORISED/PAID/CANCELLED/DECLINED. Docs: https://docs.telr.com/reference/introduction');
    }

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $payload = $request->all();

        if (! $this->verifyTranCheck($payload)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'Telr IPN signature verification failed (tran_check mismatch).',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('Telr IPN signature verification failed (tran_check mismatch).');
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

    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        throw new RuntimeException('Telr: subscription flow not yet wired — Phase 3.3. Implement via Repeat Billing / Agreement on a Stored Card token. Verify with agreement_check on IPN. Docs: https://docs.telr.com/reference/introduction');
    }

    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        throw new RuntimeException('Telr: subscription flow not yet wired — Phase 3.3. Implement via Repeat Billing / Agreement on a Stored Card token. Verify with agreement_check on IPN. Docs: https://docs.telr.com/reference/introduction');
    }

    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        throw new RuntimeException('Telr: subscription flow not yet wired — Phase 3.3. Implement via Repeat Billing / Agreement on a Stored Card token. Verify with agreement_check on IPN. Docs: https://docs.telr.com/reference/introduction');
    }

    public function resume(Subscription $subscription): Subscription
    {
        throw new RuntimeException('Telr: subscription flow not yet wired — Phase 3.3. Implement via Repeat Billing / Agreement on a Stored Card token. Verify with agreement_check on IPN. Docs: https://docs.telr.com/reference/introduction');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        throw new RuntimeException('Telr: subscription flow not yet wired — Phase 3.3. Implement via Repeat Billing / Agreement on a Stored Card token. Verify with agreement_check on IPN. Docs: https://docs.telr.com/reference/introduction');
    }
}
