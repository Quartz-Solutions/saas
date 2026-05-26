<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_view_preferences(): void
    {
        $this->get('/settings/notifications')->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_matrix(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/notifications')
                ->has('events')
                ->has('channels')
                ->has('preferences'));
    }

    public function test_patch_saves_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/notifications', [
                'preferences' => [
                    'login_alert' => [
                        'email' => '0',
                        'database' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'event_type' => 'login_alert',
            'channel' => 'email',
            'enabled' => false,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'event_type' => 'login_alert',
            'channel' => 'database',
            'enabled' => true,
        ]);
    }

    public function test_patch_ignores_always_on_events(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/notifications', [
                'preferences' => [
                    'email_verification' => [
                        'email' => '0',
                    ],
                ],
            ])
            ->assertRedirect();

        // No row was written for the always-on event — the dispatcher will
        // continue to ship verification emails.
        $this->assertDatabaseMissing('notification_preferences', [
            'user_id' => $user->id,
            'event_type' => 'email_verification',
        ]);
    }

    public function test_patch_silently_drops_unknown_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/notifications', [
                'preferences' => [
                    'made_up_event' => ['email' => '1'],
                    'welcome' => ['ghost_channel' => '1'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(0, NotificationPreference::count());
    }
}
