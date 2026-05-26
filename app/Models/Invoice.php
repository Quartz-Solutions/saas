<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'subscription_id',
    'gateway',
    'gateway_invoice_id',
    'number',
    'status',
    'currency',
    'subtotal_cents',
    'discount_cents',
    'tax_cents',
    'total_cents',
    'amount_paid_cents',
    'amount_due_cents',
    'period_start',
    'period_end',
    'issued_at',
    'due_at',
    'paid_at',
    'voided_at',
    'hosted_invoice_url',
    'pdf_path',
    'metadata',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany('App\\Models\\InvoiceLine');
    }
}
