<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'invoice_id' => null,
            'payment_method_id' => null,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'pi_'.Str::lower(Str::random(14)).'_'.fake()->unique()->numerify('####'),
            'status' => 'succeeded',
            'amount_cents' => 999,
            'refunded_cents' => 0,
            'currency' => 'USD',
            'authorized_at' => now(),
            'captured_at' => now(),
            'failed_at' => null,
            'refunded_at' => null,
            'failure_code' => null,
            'failure_message' => null,
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ];
    }
}
