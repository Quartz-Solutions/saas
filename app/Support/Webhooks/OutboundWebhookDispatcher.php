<?php

namespace App\Support\Webhooks;

use App\Jobs\DeliverWebhookJob;
use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Fan-out service: for one application event, fan out to every matching
 * webhook endpoint subscribed to that event in the given tenant. Each
 * delivery is its own queued job so a slow endpoint doesn't block siblings.
 */
class OutboundWebhookDispatcher
{
    /**
     * Dispatch `$event` with `$payload` to every active subscriber in the tenant.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, OutboundWebhookDelivery>
     */
    public function dispatch(string $event, array $payload, Tenant $tenant): array
    {
        $eventId = (string) Str::uuid();
        $now = now();

        $envelope = [
            'id' => $eventId,
            'event' => $event,
            'tenant_id' => $tenant->id,
            'created_at' => $now->toIso8601String(),
            'data' => $payload,
        ];

        $webhooks = OutboundWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (OutboundWebhook $w) => in_array($event, (array) $w->events, true))
            ->values();

        $deliveries = [];

        foreach ($webhooks as $webhook) {
            $deliveries[] = $this->queueDelivery($webhook, $event, $eventId, $envelope);
        }

        return $deliveries;
    }

    /**
     * Queue a single delivery (used by both `dispatch()` and the test-fire UI).
     *
     * @param  array<string, mixed>  $envelope
     */
    public function queueDelivery(OutboundWebhook $webhook, string $event, string $eventId, array $envelope): OutboundWebhookDelivery
    {
        $body = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', (string) $body, (string) $webhook->secret);

        $delivery = new OutboundWebhookDelivery;
        $delivery->forceFill([
            'outbound_webhook_id' => $webhook->id,
            'event_type' => $event,
            'event_id' => $eventId,
            'payload' => $envelope,
            'signature' => $signature,
            'attempt' => 0,
            'status' => OutboundWebhookDelivery::STATUS_PENDING,
        ])->save();

        DeliverWebhookJob::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * Fire a synthetic test event to a single endpoint. Useful for the
     * "Test fire" button on the endpoints page.
     */
    public function testFire(OutboundWebhook $webhook): OutboundWebhookDelivery
    {
        $eventId = (string) Str::uuid();
        $envelope = [
            'id' => $eventId,
            'event' => 'test.ping',
            'tenant_id' => $webhook->tenant_id,
            'created_at' => now()->toIso8601String(),
            'data' => ['message' => 'This is a test event from the dashboard.'],
        ];

        return $this->queueDelivery($webhook, 'test.ping', $eventId, $envelope);
    }
}
