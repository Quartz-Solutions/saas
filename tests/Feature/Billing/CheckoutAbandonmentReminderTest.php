<?php

namespace Tests\Feature\Billing;

use App\Events\CheckoutAbandoned;
use App\Jobs\ExpireStaleCheckouts;
use App\Mail\CheckoutAbandonmentReminderMail;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\User;
use App\Support\Billing\Checkout\CheckoutService;
use App\Support\Tenancy\TenantService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutAbandonmentReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_session_dispatches_event_and_sends_reminder_to_owner(): void
    {
        Mail::fake();

        $this->seed(PlansSeeder::class);
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $session = CheckoutSession::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_'.uniqid(),
            'currency' => $plan->currency,
            'amount_cents' => $plan->price_cents,
            'expires_at' => now()->subMinute(),
        ]);

        (new ExpireStaleCheckouts)->handle(app(CheckoutService::class));

        Mail::assertQueued(CheckoutAbandonmentReminderMail::class, function (CheckoutAbandonmentReminderMail $mail) use ($owner) {
            return $mail->hasTo('owner@example.test')
                && $mail->user->is($owner)
                && str_contains((string) ($mail->context['resumeUrl'] ?? ''), '/billing/plans');
        });

        $session->refresh();
        $this->assertNotEmpty($session->metadata['reminder_sent_at'] ?? null);
        $this->assertSame(CheckoutSession::STATUS_EXPIRED, $session->status);
    }

    public function test_free_plan_sessions_are_skipped(): void
    {
        Mail::fake();

        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $session = CheckoutSession::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => Plan::query()->where('slug', 'free')->first()->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_AWAITING_PAYMENT,
            'gateway' => 'free',
            'currency' => 'USD',
            'amount_cents' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        CheckoutAbandoned::dispatch($session);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_reminder_is_not_double_sent(): void
    {
        Mail::fake();

        $this->seed(PlansSeeder::class);
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $session = CheckoutSession::create([
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => Plan::query()->where('slug', 'pro')->first()->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_EXPIRED,
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_'.uniqid(),
            'currency' => 'USD',
            'amount_cents' => 2900,
            'expires_at' => now()->subMinute(),
            'metadata' => ['reminder_sent_at' => now()->toIso8601String()],
        ]);

        CheckoutAbandoned::dispatch($session);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }
}
