<?php

namespace Tests\Feature\Compliance;

use App\Listeners\AlertOnNewDeviceLogin;
use App\Models\LoginHistory;
use App\Models\User;
use App\Notifications\NewDeviceLoginAlert;
use App\Support\Auth\LoginHistoryRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewDeviceLoginAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_fires_on_new_ip(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        LoginHistory::factory()->create([
            'user_id' => $user->id,
            'outcome' => 'succeeded',
            'ip' => '10.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now()->subDay(),
        ]);

        $request = Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);

        $listener = new AlertOnNewDeviceLogin(new LoginHistoryRecorder, $request);
        $listener->handle(new Login('web', $user, false));

        Notification::assertSentTo($user, NewDeviceLoginAlert::class);
    }

    public function test_alert_does_not_fire_on_first_login(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $request = Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);

        $listener = new AlertOnNewDeviceLogin(new LoginHistoryRecorder, $request);
        $listener->handle(new Login('web', $user, false));

        Notification::assertNothingSentTo($user);
    }

    public function test_alert_does_not_fire_on_same_device(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        LoginHistory::factory()->create([
            'user_id' => $user->id,
            'outcome' => 'succeeded',
            'ip' => '10.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now()->subHour(),
        ]);

        $request = Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);

        $listener = new AlertOnNewDeviceLogin(new LoginHistoryRecorder, $request);
        $listener->handle(new Login('web', $user, false));

        Notification::assertNothingSentTo($user);
    }

    public function test_login_history_is_recorded_after_alert(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $request = Request::create('/login', 'POST', server: [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ]);

        $listener = new AlertOnNewDeviceLogin(new LoginHistoryRecorder, $request);
        $listener->handle(new Login('web', $user, false));

        $this->assertDatabaseHas('login_history', [
            'user_id' => $user->id,
            'outcome' => 'succeeded',
            'ip' => '10.0.0.1',
        ]);
    }
}
