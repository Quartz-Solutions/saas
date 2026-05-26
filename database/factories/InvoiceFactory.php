<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2]
        );

        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => null,
            'gateway' => 'stripe',
            'gateway_invoice_id' => 'in_'.Str::lower(Str::random(14)),
            'number' => 'INV-'.fake()->unique()->numerify('######').'-'.Str::upper(Str::random(4)),
            'status' => 'paid',
            'currency' => 'USD',
            'subtotal_cents' => 999,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 999,
            'amount_paid_cents' => 999,
            'amount_due_cents' => 0,
            'period_start' => null,
            'period_end' => null,
            'issued_at' => now(),
            'due_at' => null,
            'paid_at' => now(),
            'voided_at' => null,
            'hosted_invoice_url' => null,
            'pdf_path' => null,
            'metadata' => [],
        ];
    }
}
