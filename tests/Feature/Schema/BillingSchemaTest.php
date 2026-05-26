<?php

namespace Tests\Feature\Schema;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_uses_code_as_primary_key(): void
    {
        $currency = Currency::create([
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimal_places' => 0,
        ]);

        $this->assertSame('JPY', $currency->getKey());
        $this->assertFalse($currency->incrementing);
    }

    public function test_exchange_rate_links_two_currencies(): void
    {
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'USD', 'symbol' => '$']);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'EUR', 'symbol' => '€']);

        $rate = ExchangeRate::create([
            'base_currency' => 'USD',
            'target_currency' => 'EUR',
            'rate' => 0.92,
            'source' => 'manual',
        ]);

        $this->assertSame('USD', $rate->baseCurrency->code);
        $this->assertSame('EUR', $rate->targetCurrency->code);
        $this->assertEquals(0.92, (float) $rate->rate);
    }

    public function test_plan_features_cast_to_array(): void
    {
        $plan = Plan::factory()->create([
            'features' => ['seats' => 10, 'storage_gb' => 50, 'flags' => ['api', 'sso']],
        ]);

        $fresh = $plan->fresh();
        $this->assertIsArray($fresh->features);
        $this->assertSame(10, $fresh->features['seats']);
        $this->assertSame(['api', 'sso'], $fresh->features['flags']);
    }

    public function test_subscription_chains_tenant_plan_and_status(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'status' => 'trialing',
        ]);

        $this->assertTrue($subscription->tenant->is($tenant));
        $this->assertTrue($subscription->plan->is($plan));
        $this->assertSame('trialing', $subscription->status);
    }

    public function test_invoice_uses_cents_for_money(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal_cents' => 1999,
            'tax_cents' => 200,
            'total_cents' => 2199,
            'amount_paid_cents' => 2199,
            'amount_due_cents' => 0,
        ]);

        $this->assertSame(1999, $invoice->subtotal_cents);
        $this->assertSame(2199, $invoice->total_cents);
        $this->assertIsInt($invoice->subtotal_cents);
    }

    public function test_payment_idempotency_key_is_unique(): void
    {
        Payment::factory()->create(['idempotency_key' => 'abc123']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Payment::factory()->create(['idempotency_key' => 'abc123']);
    }

    public function test_payment_metadata_jsonb_round_trip(): void
    {
        $payment = Payment::factory()->create([
            'metadata' => ['gateway_extra' => ['ref' => 'XYZ', 'channel' => 'card']],
        ]);

        $fresh = $payment->fresh();
        $this->assertIsArray($fresh->metadata);
        $this->assertSame('XYZ', $fresh->metadata['gateway_extra']['ref']);
    }

    public function test_subscription_gateway_subscription_id_uniqueness_within_gateway(): void
    {
        Subscription::factory()->create([
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_abc',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Subscription::factory()->create([
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_abc',
        ]);
    }
}
