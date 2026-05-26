<?php

namespace App\Support\Billing\PayTabs;

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
class PayTabsGateway implements PaymentGateway, SubscriptionGateway
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

    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException('PayTabs: charge/refund flow not yet wired — Phase 3.2. Implement via /payment/request (hosted PayPage) or /payment/managed (managed form). Auth header = raw server_key. Docs: https://docs.paytabs.com/manuals/Find-Your-Fit-Start-Building/');
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
