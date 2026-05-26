<?php

namespace App\Support\Admin;

use App\Jobs\ReplayWebhookEventJob;
use App\Models\WebhookEvent;

/**
 * Canonical service for replaying persisted gateway webhook events.
 *
 * The actual gateway dispatch lives in `ReplayWebhookEventJob` so the work
 * happens on the queue worker; this service is the single seam controllers
 * call to schedule the replay.
 */
class WebhookReplayService
{
    /**
     * Mark the event as queued-for-replay and dispatch the worker job.
     */
    public function replay(WebhookEvent $event): WebhookEvent
    {
        $event->forceFill([
            'status' => 'processing',
            'error_message' => null,
            'processed_at' => null,
        ])->save();

        ReplayWebhookEventJob::dispatch($event->id);

        return $event->refresh();
    }
}
