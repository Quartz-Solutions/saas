<?php

namespace App\Models;

use Database\Factories\OutboundWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'outbound_webhook_id',
    'event_type',
    'event_id',
    'payload',
    'signature',
    'attempt',
    'status',
    'response_code',
    'response_body',
    'duration_ms',
    'delivered_at',
    'failed_at',
    'next_retry_at',
])]
class OutboundWebhookDelivery extends Model
{
    /** @use HasFactory<OutboundWebhookDeliveryFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ABANDONED = 'abandoned';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhook::class, 'outbound_webhook_id');
    }
}
