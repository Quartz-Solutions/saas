<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_share_exposes_count_and_latest_items(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        app(NotificationDispatcher::class)->send($user, 'welcome');

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.unreadNotificationsCount', 1)
                ->has('auth.notifications', 1));
    }

    public function test_mark_single_notification_read_flips_read_at(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        app(NotificationDispatcher::class)->send($user, 'welcome');

        $notification = $user->fresh()->notifications()->first();
        $this->assertNull($notification->read_at);

        $this->actingAs($user)
            ->patch("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_notifications_read_flips_every_unread(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->send($user, 'welcome');
        $dispatcher->send($user, 'login_alert', ['ip' => '127.0.0.1']);

        $this->assertSame(2, $user->fresh()->unreadNotifications()->count());

        $this->actingAs($user)
            ->patch('/notifications/read-all')
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $other = User::factory()->create();
        app(NotificationDispatcher::class)->send($owner, 'welcome');
        $notification = $owner->fresh()->notifications()->first();

        $this->actingAs($other)
            ->patch("/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
