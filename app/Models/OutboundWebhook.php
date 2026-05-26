<?php

namespace App\Models;

use Database\Factories\OutboundWebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'created_by_id',
    'url',
    'description',
    'secret',
    'events',
    'is_active',
    'failure_count',
    'last_delivery_at',
    'disabled_at',
])]
#[Hidden(['secret'])]
class OutboundWebhook extends Model
{
    /** @use HasFactory<OutboundWebhookFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivery_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(OutboundWebhookDelivery::class);
    }
}
