<?php

namespace Tests\Feature\Notifications;

use App\Mail\TenantInviteMail;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantInviteEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_queues_tenant_invite_mail_to_the_invitee(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        app(TenantService::class)->invite($tenant, $owner, 'pending@example.test', 'Member', false);

        Mail::assertQueued(TenantInviteMail::class, function (TenantInviteMail $mail) {
            return $mail->hasTo('pending@example.test');
        });
    }
}
