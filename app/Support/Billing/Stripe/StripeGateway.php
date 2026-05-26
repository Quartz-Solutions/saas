<?php

namespace App\Support\Billing\Stripe;

use App\Jobs\RetryFailedPayment;
use App\Models\GatewayCustomer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\Billing\PaymentGateway;
use App\Support\Billing\SubscriptionGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Webhook;
use Throwable;

/**
 * Stripe driver — Phase 3.1.
 *
 * Implements PaymentGateway (one-off charges via PaymentIntents) and
 * SubscriptionGateway (Stripe Billing). Webhook handler verifies the
 * incoming signature via the Stripe SDK helper.
 *
 * The Stripe SDK client is injected so tests can swap it for a mock via
 * the container (singleton key: StripeClient::class).
 */
class StripeGateway implements PaymentGateway, SubscriptionGateway
{
    public function __construct(
        private readonly StripeClient $client,
        private readonly string $webhookSecret = '',
        private readonly int $webhookTolerance = 300,
    ) {}

    public function id(): string
    {
        return 'stripe';
    }

    public function displayName(): string
    {
        return 'Stripe';
    }

    /**
     * Ensure a Stripe Product + Price exists for the given local Plan and
     * return the Price ID. Stripe Prices are immutable, so a price/currency/
     * period change here creates a NEW Price; existing subscriptions on the
     * old Price are untouched until they migrate.
     *
     * Idempotent — Product lookup is by metadata.plan_slug; Price reuse is
     * by the fingerprint stored on Plan::gateway_ids.stripe_fingerprint.
     */
    public function syncPriceForPlan(Plan $plan): ?string
    {
        if ((int) $plan->price_cents === 0) {
            return null; // Free plans don't need a Stripe Price.
        }

        $product = $this->ensureProductForPlan($plan);

        $fingerprint = $this->planFingerprint($plan);
        $existingIds = (array) ($plan->gateway_ids ?? []);
        $existingPriceId = $existingIds['stripe'] ?? null;
        $existingFingerprint = $existingIds['stripe_fingerprint'] ?? null;

        if ($existingPriceId !== null && $existingFingerprint === $fingerprint) {
            return $existingPriceId;
        }

        $price = $this->client->prices->create([
            'product' => $product->id,
            'currency' => strtolower((string) $plan->currency),
            'unit_amount' => (int) $plan->price_cents,
            'recurring' => [
                'interval' => $this->stripeInterval($plan->billing_period),
                'interval_count' => max(1, (int) $plan->billing_interval),
            ],
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
            ],
        ]);

        $plan->forceFill([
            'gateway_ids' => array_merge($existingIds, [
                'stripe' => $price->id,
                'stripe_product' => $product->id,
                'stripe_fingerprint' => $fingerprint,
            ]),
        ])->save();

        return $price->id;
    }

    protected function ensureProductForPlan(Plan $plan): StripeObject
    {
        $existingProductId = $plan->gateway_ids['stripe_product'] ?? null;

        if ($existingProductId !== null) {
            try {
                return $this->client->products->retrieve($existingProductId);
            } catch (Throwable) {
                // Product was deleted in Stripe — fall through and recreate.
            }
        }

        return $this->client->products->create([
            'name' => $plan->name,
            'description' => $plan->description,
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
            ],
        ]);
    }

    protected function planFingerprint(Plan $plan): string
    {
        return hash('sha256', implode('|', [
            (int) $plan->price_cents,
            strtoupper((string) $plan->currency),
            (string) $plan->billing_period,
            (int) $plan->billing_interval,
        ]));
    }

    protected function stripeInterval(string $period): string
    {
        return match ($period) {
            'day' => 'day',
            'week' => 'week',
            'month' => 'month',
            'year' => 'year',
            default => throw new RuntimeException("Stripe does not support billing period [{$period}]."),
        };
    }

    /**
     * Build a Customer Portal URL for the tenant.
     */
    public function customerPortalUrl(Tenant $tenant, string $returnUrl): string
    {
        $customer = $this->ensureCustomer($tenant);

        $session = $this->client->billingPortal->sessions->create([
            'customer' => $customer->gateway_customer_id,
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    // ------------------------------------------------------------------
    // PaymentGateway
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        $params = array_merge([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'confirm' => true,
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
        ], $context['stripe'] ?? []);

        $intent = $this->client->paymentIntents->create($params, [
            'idempotency_key' => $context['idempotency_key'] ?? null,
        ]);

        return $this->upsertPaymentFromIntent($intent, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        $params = array_merge([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'capture_method' => 'manual',
            'confirm' => true,
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
        ], $context['stripe'] ?? []);

        $intent = $this->client->paymentIntents->create($params, [
            'idempotency_key' => $context['idempotency_key'] ?? null,
        ]);

        return $this->upsertPaymentFromIntent($intent, $context);
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        $params = $amountCents === null ? [] : ['amount_to_capture' => $amountCents];
        $intent = $this->client->paymentIntents->capture($payment->gateway_payment_id, $params);

        return $this->upsertPaymentFromIntent($intent);
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        $params = ['payment_intent' => $payment->gateway_payment_id];
        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }

        $refund = $this->client->refunds->create($params);

        return DB::transaction(function () use ($payment, $refund, $amountCents) {
            $newRefunded = (int) $payment->refunded_cents + (int) ($refund->amount ?? $amountCents ?? $payment->amount_cents);
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
        $intent = $this->client->paymentIntents->cancel($payment->gateway_payment_id);

        return $this->upsertPaymentFromIntent($intent);
    }

    public function status(Payment $payment): Payment
    {
        $intent = $this->client->paymentIntents->retrieve($payment->gateway_payment_id);

        return $this->upsertPaymentFromIntent($intent);
    }

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        $signature = $request->header('Stripe-Signature') ?? '';

        if ($this->webhookSecret !== '') {
            try {
                Webhook::constructEvent(
                    $request->getContent(),
                    $signature,
                    $this->webhookSecret,
                    $this->webhookTolerance,
                );
            } catch (SignatureVerificationException $e) {
                $event->forceFill([
                    'status' => 'failed',
                    'error_message' => 'Signature verification failed: '.$e->getMessage(),
                ])->save();

                throw $e;
            }
        }

        try {
            $payload = $request->json()->all();
            $type = (string) ($payload['type'] ?? '');

            $this->dispatchEvent($type, $payload);

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

            Log::warning('Stripe webhook processing failed', [
                'event_id' => $event->id,
                'gateway_event_id' => $event->gateway_event_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $event->fresh();
    }

    // ------------------------------------------------------------------
    // SubscriptionGateway
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function createSubscription(Tenant $tenant, Plan $plan, array $context = []): Subscription
    {
        $priceId = $this->priceIdForPlan($plan);

        $customer = $this->ensureCustomer($tenant);

        $params = array_merge([
            'customer' => $customer->gateway_customer_id,
            'items' => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand' => ['latest_invoice.payment_intent'],
        ], $context['stripe'] ?? []);

        $trialDays = $context['trial_days'] ?? $plan->trial_days ?? config('billing.trial_days');
        if ((int) $trialDays > 0) {
            $params['trial_period_days'] = (int) $trialDays;
        }

        $stripeSub = $this->client->subscriptions->create($params);

        return $this->upsertSubscription($tenant, $plan, $stripeSub);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $context = []): Subscription
    {
        $stripeSub = $this->client->subscriptions->retrieve($subscription->gateway_subscription_id);
        $itemId = $stripeSub->items->data[0]->id ?? null;

        if ($itemId === null) {
            throw new RuntimeException('Stripe subscription has no items to swap.');
        }

        $priceId = $this->priceIdForPlan($newPlan);

        $updated = $this->client->subscriptions->update($subscription->gateway_subscription_id, array_merge([
            'items' => [['id' => $itemId, 'price' => $priceId]],
            'proration_behavior' => 'create_prorations',
            'cancel_at_period_end' => false,
        ], $context['stripe'] ?? []));

        return $this->upsertSubscription($subscription->tenant, $newPlan, $updated);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function cancel(Subscription $subscription, array $context = []): Subscription
    {
        $immediate = (bool) ($context['immediately'] ?? false);

        if ($immediate) {
            $updated = $this->client->subscriptions->cancel($subscription->gateway_subscription_id, []);
        } else {
            $updated = $this->client->subscriptions->update($subscription->gateway_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        }

        return $this->upsertSubscription($subscription->tenant, $subscription->plan, $updated);
    }

    public function resume(Subscription $subscription): Subscription
    {
        $updated = $this->client->subscriptions->update($subscription->gateway_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        return $this->upsertSubscription($subscription->tenant, $subscription->plan, $updated);
    }

    public function syncFromGateway(Subscription $subscription): Subscription
    {
        $updated = $this->client->subscriptions->retrieve($subscription->gateway_subscription_id);

        return $this->upsertSubscription($subscription->tenant, $subscription->plan, $updated);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchEvent(string $type, array $payload): void
    {
        match (true) {
            $type === 'customer.subscription.created',
            $type === 'customer.subscription.updated',
            $type === 'customer.subscription.deleted' => $this->onSubscriptionEvent($payload),
            $type === 'invoice.paid',
            $type === 'invoice.payment_succeeded' => $this->onInvoicePaid($payload),
            $type === 'invoice.payment_failed' => $this->onInvoicePaymentFailed($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onSubscriptionEvent(array $payload): void
    {
        $object = $payload['data']['object'] ?? null;
        if (! is_array($object)) {
            return;
        }

        $stripeId = (string) ($object['id'] ?? '');
        if ($stripeId === '') {
            return;
        }

        Subscription::query()
            ->where('gateway', 'stripe')
            ->where('gateway_subscription_id', $stripeId)
            ->each(function (Subscription $sub) use ($object) {
                $sub->forceFill($this->subscriptionAttributesFromStripe($object))->save();
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onInvoicePaid(array $payload): void
    {
        $object = $payload['data']['object'] ?? null;
        if (! is_array($object)) {
            return;
        }

        $invoice = $this->upsertInvoiceFromStripe($object);

        if ($invoice !== null) {
            $invoice->forceFill([
                'status' => 'paid',
                'amount_paid_cents' => (int) ($object['amount_paid'] ?? $invoice->total_cents),
                'amount_due_cents' => 0,
                'paid_at' => isset($object['status_transitions']['paid_at'])
                    ? CarbonImmutable::createFromTimestamp((int) $object['status_transitions']['paid_at'])
                    : now(),
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onInvoicePaymentFailed(array $payload): void
    {
        $object = $payload['data']['object'] ?? null;
        if (! is_array($object)) {
            return;
        }

        $invoice = $this->upsertInvoiceFromStripe($object);

        if ($invoice !== null) {
            $invoice->forceFill(['status' => 'open'])->save();

            $subscription = $invoice->subscription;
            if ($subscription !== null) {
                $subscription->forceFill(['status' => 'past_due'])->save();

                RetryFailedPayment::dispatch($invoice->id, 1)
                    ->delay(now()->addDays(config('billing.dunning.backoff_days.0', 1)));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $object  Stripe Invoice object as array.
     */
    private function upsertInvoiceFromStripe(array $object): ?Invoice
    {
        $stripeId = (string) ($object['id'] ?? '');
        if ($stripeId === '') {
            return null;
        }

        $existing = Invoice::query()
            ->where('gateway', 'stripe')
            ->where('gateway_invoice_id', $stripeId)
            ->first();

        $subscriptionLocalId = null;
        $subscriptionId = $object['subscription'] ?? null;
        if (is_string($subscriptionId)) {
            $subscriptionLocalId = Subscription::query()
                ->where('gateway', 'stripe')
                ->where('gateway_subscription_id', $subscriptionId)
                ->value('id');
        }

        $tenantId = $existing?->tenant_id;
        if ($tenantId === null && $subscriptionLocalId !== null) {
            $tenantId = Subscription::query()->whereKey($subscriptionLocalId)->value('tenant_id');
        }
        if ($tenantId === null) {
            $customerId = $object['customer'] ?? null;
            if (is_string($customerId)) {
                $tenantId = GatewayCustomer::query()
                    ->where('gateway', 'stripe')
                    ->where('gateway_customer_id', $customerId)
                    ->value('tenant_id');
            }
        }

        if ($tenantId === null) {
            return null;
        }

        $currency = strtoupper((string) ($object['currency'] ?? 'USD'));
        $attributes = [
            'tenant_id' => $tenantId,
            'subscription_id' => $subscriptionLocalId,
            'gateway' => 'stripe',
            'gateway_invoice_id' => $stripeId,
            'status' => (string) ($object['status'] ?? 'open'),
            'currency' => $currency,
            'subtotal_cents' => (int) ($object['subtotal'] ?? 0),
            'discount_cents' => (int) ($object['total_discount_amounts'][0]['amount'] ?? 0),
            'tax_cents' => (int) ($object['tax'] ?? 0),
            'total_cents' => (int) ($object['total'] ?? 0),
            'amount_paid_cents' => (int) ($object['amount_paid'] ?? 0),
            'amount_due_cents' => (int) ($object['amount_due'] ?? 0),
            'period_start' => isset($object['period_start']) ? CarbonImmutable::createFromTimestamp((int) $object['period_start']) : null,
            'period_end' => isset($object['period_end']) ? CarbonImmutable::createFromTimestamp((int) $object['period_end']) : null,
            'hosted_invoice_url' => $object['hosted_invoice_url'] ?? null,
            'metadata' => $object['metadata'] ?? [],
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing->fresh();
        }

        $attributes['number'] = (string) ($object['number'] ?? ('INV-'.now()->format('Y').'-'.uniqid()));
        $attributes['issued_at'] = now();

        $invoice = new Invoice;
        $invoice->forceFill($attributes)->save();

        return $invoice->fresh();
    }

    /**
     * @param  array<string, mixed>  $stripeObject  Stripe Subscription object as array.
     */
    private function subscriptionAttributesFromStripe(array $stripeObject): array
    {
        return [
            'status' => (string) ($stripeObject['status'] ?? 'incomplete'),
            'current_period_start' => isset($stripeObject['current_period_start'])
                ? CarbonImmutable::createFromTimestamp((int) $stripeObject['current_period_start'])
                : null,
            'current_period_end' => isset($stripeObject['current_period_end'])
                ? CarbonImmutable::createFromTimestamp((int) $stripeObject['current_period_end'])
                : null,
            'trial_starts_at' => isset($stripeObject['trial_start'])
                ? CarbonImmutable::createFromTimestamp((int) $stripeObject['trial_start'])
                : null,
            'trial_ends_at' => isset($stripeObject['trial_end'])
                ? CarbonImmutable::createFromTimestamp((int) $stripeObject['trial_end'])
                : null,
            'cancel_at_period_end' => (bool) ($stripeObject['cancel_at_period_end'] ?? false),
            'canceled_at' => isset($stripeObject['canceled_at'])
                ? CarbonImmutable::createFromTimestamp((int) $stripeObject['canceled_at'])
                : null,
            'ends_at' => isset($stripeObject['ended_at'])
                ? CarbonImmutable::createFromTimestamp((int) $stripeObject['ended_at'])
                : null,
        ];
    }

    private function upsertSubscription(Tenant $tenant, Plan $plan, $stripeSub): Subscription
    {
        $object = $stripeSub instanceof StripeObject
            ? $stripeSub->toArray()
            : (array) $stripeSub;

        $stripeId = (string) ($object['id'] ?? '');

        $existing = Subscription::query()
            ->where('gateway', 'stripe')
            ->where('gateway_subscription_id', $stripeId)
            ->first();

        $unitAmount = (int) ($object['items']['data'][0]['price']['unit_amount'] ?? $plan->price_cents);
        $currency = strtoupper((string) ($object['currency'] ?? $plan->currency));

        $attrs = array_merge([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'gateway_subscription_id' => $stripeId,
            'currency' => $currency,
            'unit_amount_cents' => $unitAmount,
            'quantity' => (int) ($object['items']['data'][0]['quantity'] ?? 1),
            'metadata' => $object['metadata'] ?? [],
        ], $this->subscriptionAttributesFromStripe($object));

        if ($existing !== null) {
            $existing->forceFill($attrs)->save();

            return $existing->fresh();
        }

        $subscription = new Subscription;
        $subscription->forceFill($attrs)->save();

        return $subscription->fresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function upsertPaymentFromIntent($intent, array $context = []): Payment
    {
        $object = $intent instanceof StripeObject ? $intent->toArray() : (array) $intent;

        $existing = Payment::query()
            ->where('gateway', 'stripe')
            ->where('gateway_payment_id', (string) ($object['id'] ?? ''))
            ->first();

        $attrs = [
            'gateway' => 'stripe',
            'gateway_payment_id' => (string) ($object['id'] ?? ''),
            'status' => $this->mapPaymentStatus((string) ($object['status'] ?? 'pending')),
            'amount_cents' => (int) ($object['amount'] ?? 0),
            'currency' => strtoupper((string) ($object['currency'] ?? 'USD')),
            'authorized_at' => $object['status'] === 'requires_capture' ? now() : null,
            'captured_at' => $object['status'] === 'succeeded' ? now() : null,
            'failure_code' => $object['last_payment_error']['code'] ?? null,
            'failure_message' => $object['last_payment_error']['message'] ?? null,
            'metadata' => $object['metadata'] ?? [],
        ];

        if (isset($context['idempotency_key'])) {
            $attrs['idempotency_key'] = $context['idempotency_key'];
        }

        if (isset($context['tenant_id'])) {
            $attrs['tenant_id'] = $context['tenant_id'];
        }

        if (isset($context['invoice_id'])) {
            $attrs['invoice_id'] = $context['invoice_id'];
        }

        if ($existing !== null) {
            $existing->forceFill($attrs)->save();

            return $existing->fresh();
        }

        if (! isset($attrs['tenant_id'])) {
            throw new RuntimeException('upsertPaymentFromIntent: tenant_id is required for new payments. Pass via $context.');
        }

        $payment = new Payment;
        $payment->forceFill($attrs)->save();

        return $payment->fresh();
    }

    private function mapPaymentStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded' => 'succeeded',
            'processing' => 'processing',
            'requires_payment_method' => 'failed',
            'requires_action' => 'requires_action',
            'requires_capture' => 'requires_action',
            'canceled' => 'canceled',
            default => 'pending',
        };
    }

    private function ensureCustomer(Tenant $tenant): GatewayCustomer
    {
        $existing = GatewayCustomer::query()
            ->where('tenant_id', $tenant->id)
            ->where('gateway', 'stripe')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $customer = $this->client->customers->create([
            'name' => $tenant->name,
            'metadata' => ['tenant_id' => (string) $tenant->id, 'tenant_slug' => $tenant->slug],
        ]);

        $row = new GatewayCustomer;
        $row->forceFill([
            'tenant_id' => $tenant->id,
            'gateway' => 'stripe',
            'gateway_customer_id' => $customer->id,
            'email' => $tenant->owner?->email,
            'metadata' => ['source' => 'auto_created_on_subscribe'],
        ])->save();

        return $row->fresh();
    }

    private function priceIdForPlan(Plan $plan): string
    {
        $configured = (array) ($plan->gateway_ids ?? []);
        $priceId = $configured['stripe'] ?? config("billing.plans.{$plan->slug}.gateway_prices.stripe");

        if (! is_string($priceId) || $priceId === '') {
            throw new RuntimeException("Plan [{$plan->slug}] has no Stripe price id configured. Set gateway_prices.stripe in config/billing.php or set the matching env var.");
        }

        return $priceId;
    }
}
