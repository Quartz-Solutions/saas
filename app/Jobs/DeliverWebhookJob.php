<?php

namespace App\Jobs;

use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use App\Support\Webhooks\WebhookEndpointService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Queued job that POSTs a single webhook payload to a single endpoint.
 *
 * Retries follow an exponential-ish schedule of 1m / 5m / 30m / 2h, capped at
 * 4 attempts total. After the final failure the delivery is marked
 * `abandoned` and the endpoint's consecutive-failure counter is bumped.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Total attempts (initial + 3 retries). */
    public int $tries = 4;

    /** Seconds between retries. */
    public function backoff(): array
    {
        return [60, 300, 1800, 7200];
    }

    public function __construct(
        public int $deliveryId,
    ) {}

    public function handle(WebhookEndpointService $service): void
    {
        /** @var OutboundWebhookDelivery|null $delivery */
        $delivery = OutboundWebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        /** @var OutboundWebhook|null $webhook */
        $webhook = $delivery->webhook;

        if ($webhook === null || ! $webhook->is_active) {
            $delivery->forceFill([
                'status' => OutboundWebhookDelivery::STATUS_ABANDONED,
                'failed_at' => now(),
                'response_body' => 'Endpoint is disabled or missing.',
            ])->save();

            return;
        }

        $delivery->forceFill(['attempt' => $this->attempts()])->save();

        $start = microtime(true);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => config('app.name').' Webhooks/1.0',
                    'X-Webhook-Event' => $delivery->event_type,
                    'X-Webhook-Event-Id' => $delivery->event_id,
                    'X-Webhook-Signature' => 'sha256='.$delivery->signature,
                    'X-Webhook-Attempt' => (string) $this->attempts(),
                ])
                ->post($webhook->url, $delivery->payload);

            $duration = (int) round((microtime(true) - $start) * 1000);

            $delivery->forceFill([
                'response_code' => $response->status(),
                'response_body' => mb_substr((string) $response->body(), 0, 4096),
                'duration_ms' => $duration,
            ]);

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => OutboundWebhookDelivery::STATUS_SUCCEEDED,
                    'delivered_at' => now(),
                ])->save();

                $service->recordSuccess($webhook);

                return;
            }

            $delivery->save();
            $this->markFailureAndRetry($delivery, $service, $webhook);
        } catch (ConnectionException $e) {
            $delivery->forceFill([
                'response_body' => 'connection_error: '.mb_substr($e->getMessage(), 0, 1024),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ])->save();
            $this->markFailureAndRetry($delivery, $service, $webhook);
        } catch (Throwable $e) {
            $delivery->forceFill([
                'response_body' => 'exception: '.mb_substr($e->getMessage(), 0, 1024),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ])->save();
            $this->markFailureAndRetry($delivery, $service, $webhook);
        }
    }

    public function failed(?Throwable $e): void
    {
        $delivery = OutboundWebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->forceFill([
            'status' => OutboundWebhookDelivery::STATUS_ABANDONED,
            'failed_at' => now(),
        ])->save();

        $webhook = $delivery->webhook;
        if ($webhook !== null) {
            app(WebhookEndpointService::class)->recordFailure($webhook);
        }
    }

    private function markFailureAndRetry(
        OutboundWebhookDelivery $delivery,
        WebhookEndpointService $service,
        OutboundWebhook $webhook,
    ): void {
        if ($this->attempts() >= $this->tries) {
            $delivery->forceFill([
                'status' => OutboundWebhookDelivery::STATUS_ABANDONED,
                'failed_at' => now(),
            ])->save();
            $service->recordFailure($webhook);

            return;
        }

        $backoff = $this->backoff()[$this->attempts() - 1] ?? 60;

        $delivery->forceFill([
            'status' => OutboundWebhookDelivery::STATUS_FAILED,
            'next_retry_at' => now()->addSeconds($backoff),
        ])->save();

        $this->release($backoff);
    }
}
