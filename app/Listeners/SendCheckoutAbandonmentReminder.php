<?php

namespace App\Listeners;

use App\Events\CheckoutAbandoned;
use App\Mail\CheckoutAbandonmentReminderMail;
use App\Models\CheckoutSession;
use Illuminate\Support\Facades\Mail;

class SendCheckoutAbandonmentReminder
{
    public function handle(CheckoutAbandoned $event): void
    {
        $session = $event->session->fresh(['plan', 'tenant', 'user']);
        if ($session === null) {
            return;
        }

        // Free-plan sessions never need a reminder; their completion is
        // synchronous and abandonment is meaningless.
        if ($session->gateway === 'free') {
            return;
        }

        // Already reminded for this session.
        if (! empty($session->metadata['reminder_sent_at'])) {
            return;
        }

        $recipient = $session->user ?? $session->tenant?->owner;
        if ($recipient === null || ! filled($recipient->email)) {
            return;
        }

        $resumeUrl = $session->tenant
            ? url('/t/'.$session->tenant->slug.'/billing/plans')
            : url('/get-started');

        Mail::to($recipient->email)
            ->send(new CheckoutAbandonmentReminderMail($recipient, $session, $resumeUrl));

        // Stamp metadata so a re-fired event (e.g. webhook replay) doesn't
        // double-send. The expireStale sweep only fires once per session, but
        // tests / manual replays can re-dispatch the event.
        $metadata = (array) $session->metadata;
        $metadata['reminder_sent_at'] = now()->toIso8601String();
        CheckoutSession::query()
            ->whereKey($session->id)
            ->update(['metadata' => json_encode($metadata)]);
    }
}
