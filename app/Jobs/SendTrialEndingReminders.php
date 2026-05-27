<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled daily. For every trialing subscription whose trial ends within
 * the next 3 days and whose tenant owner has not yet been reminded, fire
 * the `trial_ending` notification.
 *
 * Deduplication is tracked via metadata.trial_reminder_sent_at so we never
 * notify twice for the same trial.
 */
class SendTrialEndingReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const NOTIFY_DAYS_BEFORE = 3;

    public function handle(NotificationDispatcher $dispatcher): void
    {
        Subscription::query()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays(self::NOTIFY_DAYS_BEFORE))
            ->with(['tenant.owner', 'plan'])
            ->chunkById(100, function ($subs) use ($dispatcher) {
                foreach ($subs as $sub) {
                    $owner = $sub->tenant?->owner;
                    if ($owner === null) {
                        continue;
                    }

                    $meta = (array) ($sub->metadata ?? []);
                    if (! empty($meta['trial_reminder_sent_at'])) {
                        continue;
                    }

                    $dispatcher->send($owner, 'trial_ending', [
                        'tenant' => [
                            'id' => $sub->tenant->id,
                            'name' => $sub->tenant->name,
                            'slug' => $sub->tenant->slug,
                        ],
                        'planName' => $sub->plan?->name,
                        'trialEndsAt' => $sub->trial_ends_at->toDayDateTimeString(),
                        'daysRemaining' => max(0, now()->diffInDays($sub->trial_ends_at, false)),
                    ]);

                    $meta['trial_reminder_sent_at'] = now()->toIso8601String();
                    $sub->forceFill(['metadata' => $meta])->save();
                }
            });
    }
}
