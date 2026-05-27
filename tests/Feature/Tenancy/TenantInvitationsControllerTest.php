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

    public function test_guest_visit_renders_pending_landing(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'pending@example.com',
            'role' => 'Member',
            'token' => str_repeat('p', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->get(route('account.invitations.accept', ['token' => str_repeat('p', 64)]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('account/invitation-pending')
                ->where('invitedEmail', 'pending@example.com')
                ->where('tenant.slug', $tenant->slug)
                ->where('hasAccount', false),
            );

        // Intended URL is stashed so post-auth redirect lands back here.
        $this->assertSame(
            url(route('account.invitations.accept', ['token' => str_repeat('p', 64)])),
            session('url.intended'),
        );
    }

    public function test_accept_renders_invalid_page_when_token_unknown(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.invitations.accept', ['token' => str_repeat('x', 64)]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('account/invitation-invalid')
                ->where('reason', 'not_found'),
            );
    }

    public function test_accept_renders_invalid_page_when_expired(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'expired@example.com']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'expired@example.com',
            'role' => 'Member',
            'token' => str_repeat('e', 64),
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($invitee)
            ->get(route('account.invitations.accept', ['token' => str_repeat('e', 64)]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('account/invitation-invalid')
                ->where('reason', 'expired')
                ->where('tenant.slug', $tenant->slug),
            );
    }

    public function test_accept_renders_invalid_page_when_revoked(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'revoked@example.com']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'revoked@example.com',
            'role' => 'Member',
            'token' => str_repeat('r', 64),
            'expires_at' => now()->addDays(7),
            'revoked_at' => now()->subHour(),
        ]);

        $this->actingAs($invitee)
            ->get(route('account.invitations.accept', ['token' => str_repeat('r', 64)]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('account/invitation-invalid')
                ->where('reason', 'revoked'),
            );
    }

    public function test_accept_renders_invalid_page_when_wrong_email(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create(['email' => 'not-invitee@example.com']);
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'someone@example.com',
            'role' => 'Member',
            'token' => str_repeat('w', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($stranger)
            ->get(route('account.invitations.accept', ['token' => str_repeat('w', 64)]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('account/invitation-invalid')
                ->where('reason', 'wrong_email')
                ->where('invitedEmail', 'someone@example.com'),
            );
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
