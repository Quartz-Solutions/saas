<?php

namespace App\Events;

use App\Models\CheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;

class CheckoutCompleted
{
    use Dispatchable;

    public function __construct(public readonly CheckoutSession $session) {}
}
