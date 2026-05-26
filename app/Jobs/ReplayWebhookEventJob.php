<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-runs the gateway-specific webhook handler for a previously-received event.
 *
 * Phase 3 will land a `GatewayRegistry` whose driver knows how to re-process
 * the stored payload. Until then this job just bumps the attempt counter and
 * marks the event as "received" so a real handler can pick it up on the next
 * deploy. The point of shipping it now is to exercise the admin "Replay" UI
 * end-to-end and to give the test suite a real Bus assertion target.
 */
class ReplayWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $webhookEventId) {}

    public function handle(): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        $event->forceFill([
            'status' => 'received',
            'processed_at' => null,
            'error_message' => null,
            'processing_attempts' => $event->processing_attempts + 1,
        ])->save();

        // When the billing GatewayRegistry lands (Phase 3), the registry's
        // PaymentGateway::handleWebhook() will be invoked here with the
        // stored payload + headers. Until then we just log so the queue
        // worker has visible activity in dev.
        Log::info('Webhook replay queued', [
            'webhook_event_id' => $event->id,
            'gateway' => $event->gateway,
            'event_type' => $event->event_type,
        ]);
    }
}
