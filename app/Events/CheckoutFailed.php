<?php

namespace App\Events;

use App\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;

class CheckoutFailed
{
    use Dispatchable;

    public function __construct(
        public readonly CheckoutSession $session,
        public readonly string $reason,
    ) {}
}
