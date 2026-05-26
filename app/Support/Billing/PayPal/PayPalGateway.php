<?php

namespace App\Support\Billing\PayPal;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\Billing\PaymentGateway;
use App\Support\Billing\SubscriptionGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * PayPal driver — Phase 3.1 scaffold.
 *
 * Implements PaymentGateway + SubscriptionGateway against the PayPal REST
 * API directly via Laravel's HTTP client (no composer SDK dependency).
 *
 * What is REAL in this scaffold:
 *   - OAuth2 client-credentials token exchange (with cache).
 *   - Webhook signature verification via /v1/notifications/verify-webhook-signature.
 *   - handleWebhook() — verify + persist event status.
 *
 * What is STUBBED (throws RuntimeException with doc links):
 *   - One-off charge / authorize / capture / refund / void / status.
 *   - Subscription create / changePlan / cancel / resume / sync.
 *
 * The stubs let the GatewayRegistry resolve 'paypal' so the rest of the
 * billing pipeline (BillingService, /webhooks/{gateway} dispatch) can be
 * wired and tested while the gateway-specific calls are implemented in
 * follow-up tickets.
 */
class PayPalGateway implements PaymentGateway, SubscriptionGateway
{
    public function id(): string
    {
        return 'paypal';
    }

    public function displayName(): string
    {
        return 'PayPal';
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    public function void(Payment $payment): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    public function status(Payment $payment): Payment
    {
        // Docs: https://developer.paypal.com/docs/api/orders/v2/
        throw new RuntimeException('PayPal: charge/refund flow not yet wired — Phase 3.1. Implement via /v2/checkout/orders + /v2/payments/captures. Docs: https://developer.paypal.com/docs/api/orders/v2/');
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway — stubs
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    public function resume(Subscription $subscription): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        // Docs: https://developer.paypal.com/docs/api/subscriptions/v1/
        throw new RuntimeException('PayPal: subscription flow not yet wired — Phase 3.1. Implement via /v1/billing/subscriptions. Docs: https://developer.paypal.com/docs/api/subscriptions/v1/');
    }

    // ------------------------------------------------------------------
    // Webhook handling — real
    // ------------------------------------------------------------------

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        try {
            $verified = $this->verifyWebhookSignature($request);
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'PayPal signature verification error: '.$e->getMessage(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            Log::warning('PayPal webhook verification threw', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('PayPal webhook signature verification failed', 0, $e);
        }

        if (! $verified) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'PayPal signature verification failed',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            throw new RuntimeException('PayPal webhook signature verification failed');
        }

        try {
            $payload = $request->json()->all();
            $eventType = (string) ($payload['event_type'] ?? '');

            $event->forceFill([
                'event_type' => $eventType !== '' ? $eventType : $event->event_type,
                'status' => 'processed',
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();

            Log::warning('PayPal webhook processing failed', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event->fresh();
    }

    /**
     * Verify a PayPal webhook by asking PayPal's verify endpoint.
     *
     * This is the canonical "easy" approach per PayPal's docs — we hand
     * the headers + raw body + our configured webhook_id back to PayPal
     * and they tell us if it's authentic.
     *
     * Docs: https://developer.paypal.com/api/rest/webhooks/rest/#link-verifywebhooksignature
     */
    protected function verifyWebhookSignature(Request $request): bool
    {
        $webhookId = (string) config('billing.gateways.paypal.webhook_id', '');

        if ($webhookId === '') {
            throw new RuntimeException('PayPal webhook_id is not configured (billing.gateways.paypal.webhook_id).');
        }

        // PayPal sends the body as JSON — re-decode the raw body so we
        // pass the *exact* object PayPal hashed, not a re-serialised one.
        $rawBody = $request->getContent();
        $webhookEvent = json_decode($rawBody, true);

        if (! is_array($webhookEvent)) {
            return false;
        }

        $payload = [
            'auth_algo' => (string) $request->header('paypal-auth-algo', ''),
            'cert_url' => (string) $request->header('paypal-cert-url', ''),
            'transmission_id' => (string) $request->header('paypal-transmission-id', ''),
            'transmission_sig' => (string) $request->header('paypal-transmission-sig', ''),
            'transmission_time' => (string) $request->header('paypal-transmission-time', ''),
            'webhook_id' => $webhookId,
            'webhook_event' => $webhookEvent,
        ];

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', $payload);

        if (! $response->successful()) {
            Log::warning('PayPal verify-webhook-signature returned non-2xx', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return (string) $response->json('verification_status') === 'SUCCESS';
    }

    // ------------------------------------------------------------------
    // OAuth2 token + base URL — real
    // ------------------------------------------------------------------

    /**
     * Fetch (or reuse) a PayPal OAuth2 client_credentials access token.
     *
     * Tokens are valid for ~9 hours (`expires_in` ≈ 31668s). We cache for
     * `expires_in - 60` to give a one-minute safety margin against clock
     * skew between this host and PayPal.
     *
     * Docs: https://developer.paypal.com/api/rest/authentication/
     */
    protected function accessToken(): string
    {
        $mode = $this->mode();
        $cacheKey = "paypal:access_token:{$mode}";

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) config('billing.gateways.paypal.client_id', '');
        $clientSecret = (string) config('billing.gateways.paypal.client_secret', '');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal client_id / client_secret are not configured.');
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->acceptJson()
            ->asForm()
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayPal OAuth2 token request failed: '.$response->status().' '.$response->body());
        }

        $token = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 0);

        if ($token === '') {
            throw new RuntimeException('PayPal OAuth2 response did not include an access_token.');
        }

        $ttl = max(60, $expiresIn - 60);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Resolve the PayPal REST API base URL for the configured mode.
     */
    protected function baseUrl(): string
    {
        return $this->mode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function mode(): string
    {
        $mode = (string) config('billing.gateways.paypal.mode', 'sandbox');

        return $mode === 'live' ? 'live' : 'sandbox';
    }
}
