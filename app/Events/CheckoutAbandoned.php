<?php

namespace App\Events;

use App\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by ExpireStaleCheckouts when a session times out before completion.
 * Distinct from CheckoutFailed (which is a hard payment failure).
 */
class CheckoutAbandoned
{
    use Dispatchable;

    public function __construct(public readonly CheckoutSession $session) {}
}
