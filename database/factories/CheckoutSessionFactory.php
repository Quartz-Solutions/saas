<?php

namespace Database\Factories;

use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    protected $model = CheckoutSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => 'USD',
            'amount_cents' => 2900,
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ];
    }

    public function awaitingPayment(string $gateway = 'stripe', string $kind = CheckoutSession::KIND_REDIRECT): static
    {
        return $this->state(fn () => [
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'gateway' => $gateway,
            'gateway_session_id' => 'cs_test_'.fake()->lexify('??????????'),
            'result_kind' => $kind,
            'result_payload' => match ($kind) {
                CheckoutSession::KIND_REDIRECT => ['url' => 'https://checkout.example.test/redir'],
                CheckoutSession::KIND_IFRAME => ['iframe_url' => 'https://checkout.example.test/iframe'],
                default => [],
            },
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CheckoutSession::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => CheckoutSession::STATUS_EXPIRED,
            'expires_at' => now()->subMinutes(1),
        ]);
    }
}
