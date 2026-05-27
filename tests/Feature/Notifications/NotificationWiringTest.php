<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendCheckoutAbandonmentReminders;
use App\Jobs\SendTrialEndingReminders;
use App\Mail\CheckoutAbandonmentReminderMail;
use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use App\Mail\TrialEndingMail;
use App\Mail\TwoFactorRecoveryMail;
use App\Models\CheckoutSession;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Tests\TestCase;

class NotificationWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_uses_branded_mailable(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $user->sendPasswordResetNotification('test-token-123');

        Mail::assertQueued(PasswordResetMail::class, fn ($m) => $m->user->is($user));
    }

    public function test_email_verification_uses_branded_mailable(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        Mail::assertQueued(EmailVerificationMail::class, fn ($m) => $m->user->is($user));
    }

    public function test_two_factor_recovery_listener_dispatches_notification(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        event(new RecoveryCodeReplaced($user, 'replaced-code'));

        Mail::assertQueued(TwoFactorRecoveryMail::class, fn ($m) => $m->user->is($user));
    }

    public function test_tenant_invite_writes_inapp_for_existing_user(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.test']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        app(TenantService::class)->invite($tenant, $owner, 'invitee@example.test', 'Member', false);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $invitee->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_checkout_abandonment_job_sends_reminder_after_1h(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $plan = Plan::factory()->create();

        $session = new CheckoutSession;
        $session->forceFill([
            'public_id' => 'cs_test_'.uniqid(),
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => 'USD',
            'amount_cents' => 2000,
            'expires_at' => now()->addHours(1),
            'created_at' => now()->subMinutes(70),
            'updated_at' => now()->subMinutes(70),
        ])->save();
        // Re-set created_at after save (Eloquent overrides it).
        DB::table('checkout_sessions')
            ->where('id', $session->id)
            ->update(['created_at' => now()->subMinutes(70)]);

        (new SendCheckoutAbandonmentReminders)->handle(
            app(NotificationDispatcher::class),
        );

        Mail::assertQueued(CheckoutAbandonmentReminderMail::class, fn ($m) => $m->user->is($owner));
        $this->assertNotNull($session->fresh()->abandonment_reminder_sent_at);
    }

    public function test_checkout_abandonment_job_does_not_double_remind(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $plan = Plan::factory()->create();

        $session = new CheckoutSession;
        $session->forceFill([
            'public_id' => 'cs_test_'.uniqid(),
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'intent' => CheckoutSession::INTENT_SUBSCRIPTION,
            'status' => CheckoutSession::STATUS_PENDING,
            'currency' => 'USD',
            'amount_cents' => 2000,
            'expires_at' => now()->addHours(1),
            'abandonment_reminder_sent_at' => now()->subMinutes(10),
        ])->save();
        DB::table('checkout_sessions')
            ->where('id', $session->id)
            ->update(['created_at' => now()->subMinutes(70)]);

        (new SendCheckoutAbandonmentReminders)->handle(
            app(NotificationDispatcher::class),
        );

        Mail::assertNotQueued(CheckoutAbandonmentReminderMail::class);
    }

    public function test_trial_ending_job_fires_when_3_days_left(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $plan = Plan::factory()->create();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'status' => 'trialing',
            'currency' => 'USD',
            'unit_amount_cents' => 2000,
            'quantity' => 1,
            'trial_ends_at' => now()->addDays(2),
            'current_period_end' => now()->addDays(2),
        ]);

        (new SendTrialEndingReminders)->handle(
            app(NotificationDispatcher::class),
        );

        Mail::assertQueued(TrialEndingMail::class, fn ($m) => $m->user->is($owner));
    }

    public function test_trial_ending_job_dedupes(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $plan = Plan::factory()->create();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'status' => 'trialing',
            'currency' => 'USD',
            'unit_amount_cents' => 2000,
            'quantity' => 1,
            'trial_ends_at' => now()->addDays(2),
            'current_period_end' => now()->addDays(2),
            'metadata' => ['trial_reminder_sent_at' => now()->subDay()->toIso8601String()],
        ]);

        (new SendTrialEndingReminders)->handle(
            app(NotificationDispatcher::class),
        );

        Mail::assertNotQueued(TrialEndingMail::class);
    }
}
