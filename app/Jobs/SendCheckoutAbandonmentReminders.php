<?php

namespace App\Jobs;

use App\Models\CheckoutSession;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled every 10 minutes. For every CheckoutSession that's been pending
 * for at least 1h but hasn't been reminded yet (and isn't terminal), fire
 * the `checkout_abandonment_reminder` notification. The session is then
 * picked up by ExpireStaleCheckouts at the 2h mark (matching the default
 * checkout.timeout_minutes = 120).
 */
class SendCheckoutAbandonmentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const REMIND_AFTER_MINUTES = 60;

    public function handle(NotificationDispatcher $dispatcher): void
    {
        CheckoutSession::query()
            ->whereIn('status', [
                CheckoutSession::STATUS_PENDING,
                CheckoutSession::STATUS_AWAITING_PAYMENT,
            ])
            ->whereNull('completed_at')
            ->whereNull('canceled_at')
            ->whereNull('abandonment_reminder_sent_at')
            ->where('created_at', '<=', now()->subMinutes(self::REMIND_AFTER_MINUTES))
            ->with(['plan', 'tenant'])
            ->chunkById(100, function ($sessions) use ($dispatcher) {
                foreach ($sessions as $session) {
                    $user = User::find($session->user_id);
                    if ($user === null) {
                        continue;
                    }

                    $resumeUrl = $session->tenant
                        ? url(route(
                            'tenants.billing.plans',
                            ['tenantSlug' => $session->tenant->slug],
                            false,
                        ))
                        : url('/');

                    $dispatcher->send($user, 'checkout_abandonment_reminder', [
                        'planName' => $session->plan?->name ?? 'a plan',
                        'resumeUrl' => $resumeUrl,
                        'cancelAt' => $session->expires_at?->toDayDateTimeString(),
                    ]);

                    $session->forceFill([
                        'abandonment_reminder_sent_at' => now(),
                    ])->save();
                }
            });
    }
}
