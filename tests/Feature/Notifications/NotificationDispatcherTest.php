<?php

namespace Tests\Feature\Notifications;

use App\Mail\EmailVerificationMail;
use App\Mail\LoginAlertMail;
use App\Mail\WelcomeMail;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_dispatches_mailable_and_database_entry_with_defaults(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $dispatcher = app(NotificationDispatcher::class);

        $dispatched = $dispatcher->send($user, 'welcome');

        $this->assertTrue($dispatched['email'] ?? false);
        $this->assertTrue($dispatched['database'] ?? false);

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertSame(1, $user->fresh()->notifications()->count());
        $this->assertSame('welcome', $user->fresh()->notifications()->first()->data['event']);
    }

    public function test_user_can_disable_an_event_entirely(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->setPreference($user, 'login_alert', 'email', false);
        $dispatcher->setPreference($user, 'login_alert', 'database', false);

        $dispatched = $dispatcher->send($user, 'login_alert');

        $this->assertSame([], $dispatched, 'Dispatcher should skip when every channel disabled.');
        Mail::assertNothingQueued();
        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_always_on_events_bypass_user_preferences(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $dispatcher = app(NotificationDispatcher::class);

        // Even when the user disables email for an always-on event the
        // dispatcher must still ship it (transactional).
        $dispatcher->setPreference($user, 'email_verification', 'email', false);

        $dispatched = $dispatcher->send($user, 'email_verification', [
            'verifyUrl' => 'https://example.test/verify',
        ]);

        $this->assertTrue($dispatched['email'] ?? false);
        Mail::assertQueued(EmailVerificationMail::class);
    }

    public function test_login_alert_mailable_renders_without_errors(): void
    {
        $user = User::factory()->create();
        $mail = new LoginAlertMail($user, [
            'ip' => '127.0.0.1',
            'userAgent' => 'PHPUnit',
        ]);

        $rendered = $mail->render();

        $this->assertNotEmpty($rendered);
        $this->assertStringContainsString('New sign-in', $rendered);
    }

    public function test_preferences_for_returns_config_defaults_when_unset(): void
    {
        $user = User::factory()->create();
        $matrix = app(NotificationDispatcher::class)->preferencesFor($user);

        $this->assertTrue($matrix['welcome']['email'] ?? false);
        $this->assertTrue($matrix['welcome']['database'] ?? false);
    }

    public function test_preferences_for_respects_stored_rows(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'event_type' => 'login_alert',
            'channel' => 'email',
            'enabled' => false,
        ]);

        $matrix = app(NotificationDispatcher::class)->preferencesFor($user);

        $this->assertFalse($matrix['login_alert']['email']);
        // database channel still defaults to enabled because we didn't touch it
        $this->assertTrue($matrix['login_alert']['database']);
    }
}
