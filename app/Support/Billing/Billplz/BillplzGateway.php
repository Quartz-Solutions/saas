<?php

namespace App\Support\Billing\Billplz;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Support\Billing\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Billplz driver — Phase 3.4 scaffold.
 *
 * Billplz is a Malaysia-only bills/invoice gateway. It is NOT a card
 * processor in the Stripe sense — every transaction is modelled as a
 * "bill" sitting under a "collection". The customer pays the bill via
 * the hosted Billplz checkout (FPX online banking, cards, e-wallets,
 * Boost, GrabPay, etc.) and Billplz then fires:
 *
 *   - a server-to-server callback (POST callback_url)  ← source of truth
 *   - a browser redirect       (GET  redirect_url)     ← UX only
 *
 * The order of those two is NOT guaranteed — never trust state from the
 * redirect alone; always wait for the callback.
 *
 * Why we only implement PaymentGateway (and NOT SubscriptionGateway):
 * Billplz has no native subscription engine. "Recurring billing" is
 * implemented merchant-side by issuing N bills on a schedule (one bill
 * per period). When subscriptions land in Phase 3.x, that scheduling
 * happens in the BillingService layer, not in this driver.
 *
 * Other quirks worth keeping in mind:
 *  - Currency: MYR only. Cross-currency charges are not supported.
 *  - Lifetime cap: 20,000 collection records per Billplz account. Do not
 *    spin up a fresh collection per tenant; reuse the configured
 *    collection_id and key bills by tenant via reference_1/reference_2.
 *  - Amounts are integers in sen (1 sen = 0.01 MYR) — aligns with the
 *    boilerplate's *_cents column convention.
 *  - Auto-FPX direct mode: append `?auto_submit=true` to the bill URL
 *    plus `reference_1_label=Bank+Code` + `reference_1=<FPX bank code>`
 *    to skip the Billplz bank-selection page.
 *  - Refunds: NOT exposed in Billplz's public API. They are handled out
 *    of band via the Billplz dashboard / sales contact. Do not expose a
 *    refund button in the UI until this is verified with the sales
 *    channel.
 *  - HTTP Basic auth: api_key as the username, password is blank.
 *  - All traffic HTTPS only.
 *
 * Signatures (X-Signature) use TWO different algorithms depending on the
 * delivery channel — callback (server POST) is SHA-512; redirect (browser
 * GET) is SHA-256. See {@see self::verifyCallback()} and
 * {@see self::verifyRedirect()}.
 */
class BillplzGateway implements PaymentGateway
{
    public function id(): string
    {
        return 'billplz';
    }

    public function displayName(): string
    {
        return 'Billplz';
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs (Phase 3.4)
    // ------------------------------------------------------------------

    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('Billplz: charge/refund flow not yet wired — Phase 3.4. Implement via /api/v3/bills (creates a bill under collection_id, returns bill URL). Auto-FPX direct mode: append ?auto_submit=true + reference_1_label=Bank+Code + reference_1=<FPX bank code>. Docs: https://www.billplz.com/api#introduction');
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('Billplz: charge/refund flow not yet wired — Phase 3.4. Implement via /api/v3/bills (creates a bill under collection_id, returns bill URL). Auto-FPX direct mode: append ?auto_submit=true + reference_1_label=Bank+Code + reference_1=<FPX bank code>. Docs: https://www.billplz.com/api#introduction');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('Billplz: charge/refund flow not yet wired — Phase 3.4. Implement via /api/v3/bills (creates a bill under collection_id, returns bill URL). Auto-FPX direct mode: append ?auto_submit=true + reference_1_label=Bank+Code + reference_1=<FPX bank code>. Docs: https://www.billplz.com/api#introduction');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('Billplz: charge/refund flow not yet wired — Phase 3.4. Implement via /api/v3/bills (creates a bill under collection_id, returns bill URL). Auto-FPX direct mode: append ?auto_submit=true + reference_1_label=Bank+Code + reference_1=<FPX bank code>. Docs: https://www.billplz.com/api#introduction');
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException('Billplz: charge/refund flow not yet wired — Phase 3.4. Implement via /api/v3/bills (creates a bill under collection_id, returns bill URL). Auto-FPX direct mode: append ?auto_submit=true + reference_1_label=Bank+Code + reference_1=<FPX bank code>. Docs: https://www.billplz.com/api#introduction');
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException('Billplz: charge/refund flow not yet wired — Phase 3.4. Implement via /api/v3/bills (creates a bill under collection_id, returns bill URL). Auto-FPX direct mode: append ?auto_submit=true + reference_1_label=Bank+Code + reference_1=<FPX bank code>. Docs: https://www.billplz.com/api#introduction');
    }

    // ------------------------------------------------------------------
    // Webhook handler — REAL
    // ------------------------------------------------------------------

    /**
     * Billplz POSTs form-encoded data to the bill's callback_url whenever
     * a bill's state changes (paid / failed). The body includes every
     * bill field plus an `x_signature` value. Verify with SHA-512 over
     * the alphabetically-sorted "key value" pairs joined by "|" — see
     * {@see self::verifyCallback()}.
     *
     * Billplz retries the callback on non-2xx — make sure the wrapping
     * controller route returns 200 once we've durably persisted the
     * WebhookEvent row.
     */
    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $params = $request->all();

        if (! $this->verifyCallback($params)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'Billplz callback signature verification failed',
            ])->save();

            throw new RuntimeException('Billplz callback signature verification failed');
        }

        $event->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    // ------------------------------------------------------------------
    // Signature verification — REAL (dual scheme)
    // ------------------------------------------------------------------

    /**
     * Build the canonical "source" string that Billplz signs.
     *
     * Rules (identical for callback + redirect; the algorithm differs):
     *   1) Drop the `x_signature` key itself.
     *   2) Sort the remaining params alphabetically by key.
     *   3) For each pair, render as `key + " " + value` (single space).
     *   4) Join those rendered pairs with `|` (pipe).
     *
     * Nested arrays are not part of the spec — callers should only pass
     * flat scalar params (form-encoded POST body or query string).
     *
     * @param  array<string, mixed>  $params
     */
    protected function buildSignedString(array $params): string
    {
        unset($params['x_signature']);

        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key.' '.(is_scalar($value) || $value === null ? (string) $value : json_encode($value));
        }

        return implode('|', $pairs);
    }

    /**
     * Verify a Billplz server-to-server callback signature.
     *
     * Algorithm: HMAC-SHA512 over {@see self::buildSignedString()} using
     * the merchant's x_signature_key, compared with `hash_equals` against
     * the `x_signature` form field.
     *
     * @param  array<string, mixed>  $params
     */
    protected function verifyCallback(array $params): bool
    {
        $provided = (string) ($params['x_signature'] ?? '');
        if ($provided === '') {
            return false;
        }

        $key = (string) config('billing.gateways.billplz.x_signature_key');
        if ($key === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $this->buildSignedString($params), $key);

        return hash_equals($expected, $provided);
    }

    /**
     * Verify a Billplz browser-redirect signature.
     *
     * Algorithm: HMAC-SHA256 over {@see self::buildSignedString()} using
     * the merchant's x_signature_key, compared with `hash_equals` against
     * the `x_signature` query string parameter.
     *
     * IMPORTANT: redirect arrival is NOT a source of truth — it just
     * exists so the user lands on the right thank-you page. Always
     * reconcile state from the matching callback.
     *
     * @param  array<string, mixed>  $params
     */
    protected function verifyRedirect(array $params): bool
    {
        $provided = (string) ($params['x_signature'] ?? '');
        if ($provided === '') {
            return false;
        }

        $key = (string) config('billing.gateways.billplz.x_signature_key');
        if ($key === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $this->buildSignedString($params), $key);

        return hash_equals($expected, $provided);
    }

    // ------------------------------------------------------------------
    // HTTP — REAL plumbing for Phase 3.4 wiring
    // ------------------------------------------------------------------

    /**
     * HTTP Basic-Auth credentials for the Billplz REST API.
     *
     * Billplz uses the merchant api_key as the Basic-auth username and
     * a blank password — i.e. `Authorization: Basic base64(api_key:)`.
     *
     * @return array{0: string, 1: string}
     */
    protected function authPair(): array
    {
        return [
            (string) config('billing.gateways.billplz.api_key'),
            '',
        ];
    }

    /**
     * Base URL for Billplz's REST API, switched by environment.
     *
     *  - sandbox    → https://www.billplz-sandbox.com/api/
     *  - production → https://www.billplz.com/api/
     */
    protected function baseUrl(): string
    {
        return (bool) config('billing.gateways.billplz.sandbox')
            ? 'https://www.billplz-sandbox.com/api/'
            : 'https://www.billplz.com/api/';
    }

    /**
     * Pre-configured Http client pointed at the right Billplz host with
     * Basic-auth attached. Use this once the real charge/refund flows
     * are wired in Phase 3.4.
     */
    protected function http(): PendingRequest
    {
        [$user, $pass] = $this->authPair();

        return Http::baseUrl($this->baseUrl())
            ->withBasicAuth($user, $pass)
            ->acceptJson()
            ->asForm();
    }
}
