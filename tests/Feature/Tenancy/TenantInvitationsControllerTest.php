<?php

namespace Tests\Feature\Tenancy;

use App\Models\TenantInvitation;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantInvitationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_invitation(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->post(route('tenants.invitations.store', ['tenantSlug' => $tenant->slug]), [
                'email' => 'someone@example.com',
                'role' => 'Member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'someone@example.com',
            'role' => 'Member',
        ]);
    }

    public function test_member_cannot_create_invitation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        app(TenantService::class)->invite($tenant, $owner, $member->email, 'Member');

        $this->actingAs($member)
            ->post(route('tenants.invitations.store', ['tenantSlug' => $tenant->slug]), [
                'email' => 'foo@example.com',
                'role' => 'Member',
            ])
            ->assertForbidden();
    }

    public function test_invite_validation_rejects_bad_email_or_role(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->from(route('tenants.invitations.index', ['tenantSlug' => $tenant->slug]))
            ->post(route('tenants.invitations.store', ['tenantSlug' => $tenant->slug]), [
                'email' => 'not-an-email',
                'role' => 'Hacker',
            ])
            ->assertSessionHasErrors(['email', 'role']);
    }

    public function test_invitation_accept_endpoint_attaches_user(): void
    {
        $owner = User::factory()->create();
        $newbie = User::factory()->create(['email' => 'newbie@example.com']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $invite = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'newbie@example.com',
            'role' => 'Member',
            'token' => str_repeat('z', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($newbie)
            ->get(route('account.invitations.accept', ['token' => $invite->token]))
            ->assertRedirect(route('tenants.dashboard', ['tenantSlug' => $tenant->slug]));

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $newbie->id,
        ]);
    }

    public function test_owner_can_revoke_invitation(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);
        $invite = app(TenantService::class)->invite($tenant, $owner, 'pending@example.com');

        $this->actingAs($owner)
            ->delete(route('tenants.invitations.destroy', [
                'tenantSlug' => $tenant->slug,
                'invitation' => $invite->id,
            ]))
            ->assertRedirect();

        $this->assertNotNull($invite->fresh()->revoked_at);
    }
}
