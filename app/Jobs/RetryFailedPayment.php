<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\SubscriptionGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dunning — retry a failed invoice payment with exponential backoff.
 *
 * Backoff schedule lives in config('billing.dunning.backoff_days'). After
 * the final attempt without recovery, the linked subscription is moved
 * from past_due → cancelled.
 *
 * Triggered by Stripe `invoice.payment_failed` webhooks (see StripeGateway).
 */
class RetryFailedPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $attempt = 1,
    ) {}

    public function handle(GatewayRegistry $registry): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);
        if ($invoice === null) {
            return;
        }

        if ($invoice->status === 'paid') {
            return;
        }

        $gateway = $registry->find($invoice->gateway);
        if (! $gateway instanceof SubscriptionGateway) {
            Log::warning('RetryFailedPayment: gateway is not subscription-capable', [
                'invoice_id' => $invoice->id,
                'gateway' => $invoice->gateway,
            ]);

            return;
        }

        $backoff = (array) config('billing.dunning.backoff_days', [1, 3, 7]);
        $maxAttempts = (int) config('billing.dunning.max_attempts', count($backoff));

        // Re-sync the subscription status from the gateway. Stripe may
        // already have flipped the sub back to active if the customer
        // updated their card.
        $subscription = $invoice->subscription;
        if ($subscription !== null) {
            try {
                $subscription = $gateway->syncFromGateway($subscription);
            } catch (\Throwable $e) {
                Log::warning('RetryFailedPayment: sync failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($subscription->status === 'active') {
                return; // recovered, no further action.
            }
        }

        if ($this->attempt >= $maxAttempts) {
            // Out of retries — cancel and stop.
            if ($subscription !== null) {
                $subscription->forceFill([
                    'status' => 'canceled',
                    'cancellation_reason' => 'dunning_max_attempts',
                    'canceled_at' => now(),
                    'ends_at' => now(),
                ])->save();

                try {
                    $gateway->cancel($subscription, ['immediately' => true]);
                } catch (\Throwable $e) {
                    Log::warning('RetryFailedPayment: gateway cancel failed', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        // Schedule the next retry with the next backoff value.
        $nextIndex = min($this->attempt, count($backoff) - 1);
        $nextDelay = (int) ($backoff[$nextIndex] ?? end($backoff));

        self::dispatch($this->invoiceId, $this->attempt + 1)
            ->delay(now()->addDays($nextDelay));
    }
}
