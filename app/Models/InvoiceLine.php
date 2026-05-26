<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'subscription_item_id',
    'description',
    'quantity',
    'unit_amount_cents',
    'amount_cents',
    'tax_cents',
    'currency',
    'period_start',
    'period_end',
    'metadata',
])]
class InvoiceLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_cents' => 'integer',
            'amount_cents' => 'integer',
            'tax_cents' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
