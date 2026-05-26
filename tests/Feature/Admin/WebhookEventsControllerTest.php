<?php

namespace Tests\Feature\Admin;

use App\Jobs\ReplayWebhookEventJob;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebhookEventsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_index_lists_webhook_events(): void
    {
        $admin = $this->makeSuperAdmin();
        WebhookEvent::factory()->create(['gateway' => 'stripe', 'event_type' => 'invoice.paid']);
        WebhookEvent::factory()->create(['gateway' => 'paypal', 'event_type' => 'PAYMENT.SALE.COMPLETED']);

        $this->actingAs($admin)
            ->get(route('admin.webhooks.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/webhooks/index')
                ->has('webhookEvents.data', 2)
            );
    }

    public function test_index_filter_by_gateway(): void
    {
        $admin = $this->makeSuperAdmin();
        WebhookEvent::factory()->create(['gateway' => 'stripe']);
        WebhookEvent::factory()->create(['gateway' => 'paypal']);

        $this->actingAs($admin)
            ->get(route('admin.webhooks.index', ['filter' => ['gateway' => 'stripe']]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('webhookEvents.data', 1));
    }

    public function test_show_returns_payload(): void
    {
        $admin = $this->makeSuperAdmin();
        $event = WebhookEvent::factory()->create([
            'gateway' => 'stripe',
            'payload' => ['object' => 'event', 'id' => 'evt_123'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.webhooks.show', ['webhookEvent' => $event->id]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('admin/webhooks/show')
                ->where('webhookEvent.id', $event->id)
                ->where('webhookEvent.payload.id', 'evt_123')
            );
    }

    public function test_replay_dispatches_job_and_marks_processing(): void
    {
        Bus::fake();

        $admin = $this->makeSuperAdmin();
        $event = WebhookEvent::factory()->create([
            'status' => 'failed',
            'error_message' => 'previous error',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.webhooks.replay', ['webhookEvent' => $event->id]))
            ->assertRedirect();

        Bus::assertDispatched(ReplayWebhookEventJob::class, fn ($job) => $job->webhookEventId === $event->id);

        $this->assertDatabaseHas('webhook_events', [
            'id' => $event->id,
            'status' => 'processing',
            'error_message' => null,
        ]);
    }

    public function test_replay_rejected_for_non_admin(): void
    {
        $regular = User::factory()->create();
        $event = WebhookEvent::factory()->create();

        $this->actingAs($regular)
            ->post(route('admin.webhooks.replay', ['webhookEvent' => $event->id]))
            ->assertForbidden();
    }

    public function test_replay_job_increments_attempts(): void
    {
        $event = WebhookEvent::factory()->create([
            'status' => 'failed',
            'processing_attempts' => 1,
        ]);

        (new ReplayWebhookEventJob($event->id))->handle();

        $this->assertDatabaseHas('webhook_events', [
            'id' => $event->id,
            'status' => 'received',
            'processing_attempts' => 2,
        ]);
    }
}
