<?php

namespace App\Jobs;

use App\Support\Billing\Checkout\CheckoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled every 5 minutes (see bootstrap/app.php). Marks any pending or
 * awaiting_payment CheckoutSession whose expires_at has passed as expired,
 * and dispatches CheckoutAbandoned so notification listeners can react.
 */
class ExpireStaleCheckouts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CheckoutService $service): void
    {
        $service->expireStale();
    }
}
