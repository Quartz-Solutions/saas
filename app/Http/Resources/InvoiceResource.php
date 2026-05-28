<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'subscription_id' => $this->subscription_id,
            'gateway' => $this->gateway,
            'gateway_invoice_id' => $this->gateway_invoice_id,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal_cents' => (int) $this->subtotal_cents,
            'discount_cents' => (int) $this->discount_cents,
            'tax_cents' => (int) $this->tax_cents,
            'total_cents' => (int) $this->total_cents,
            'amount_paid_cents' => (int) $this->amount_paid_cents,
            'amount_due_cents' => (int) $this->amount_due_cents,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'hosted_invoice_url' => $this->hosted_invoice_url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
