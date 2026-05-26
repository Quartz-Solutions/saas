<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class TenantServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantService::class);
    }

    public function test_create_provisions_tenant_owner_membership_and_roles(): void
    {
        $user = User::factory()->create();

        $tenant = $this->service->create($user, ['name' => 'Acme Inc']);

        $this->assertSame('acme-inc', $tenant->slug);
        $this->assertSame($user->id, $tenant->owner_id);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);
        setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->hasRole('Owner'));
    }

    public function test_create_rejects_blank_name(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->create($user, ['name' => '   ']);
    }

    public function test_create_disambiguates_slug_when_taken(): void
    {
        $owner = User::factory()->create();
        Tenant::factory()->create(['slug' => 'acme', 'owner_id' => $owner->id]);

        $tenant = $this->service->create($owner, ['name' => 'Acme', 'slug' => 'acme']);

        $this->assertSame('acme-2', $tenant->slug);
    }

    public function test_invite_creates_pending_invitation_for_unknown_email(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $invite = $this->service->invite($tenant, $owner, 'newcomer@example.com');

        $this->assertNotNull($invite->token);
        $this->assertNull($invite->accepted_at);
        $this->assertSame('Member', $invite->role);
        $this->assertSame('newcomer@example.com', $invite->email);
    }

    public function test_invite_auto_attaches_existing_user(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'mate@example.com']);
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $invite = $this->service->invite($tenant, $owner, 'mate@example.com', 'Admin');

        $this->assertNotNull($invite->accepted_at);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_invite_rejects_unknown_role(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->invite($tenant, $owner, 'x@example.com', 'GodMode');
    }

    public function test_accept_invitation_creates_membership(): void
    {
        $owner = User::factory()->create();
        $newbie = User::factory()->create(['email' => 'newbie@example.com']);
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $invite = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'newbie@example.com',
            'role' => 'Member',
            'token' => str_repeat('a', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $membership = $this->service->acceptInvitation($invite->token, $newbie);

        $this->assertSame($tenant->id, $membership->tenant_id);
        $this->assertSame($newbie->id, $membership->user_id);
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_accept_invitation_rejects_expired(): void
    {
        $owner = User::factory()->create();
        $newbie = User::factory()->create(['email' => 'newbie@example.com']);
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $invite = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'newbie@example.com',
            'role' => 'Member',
            'token' => str_repeat('b', 64),
            'expires_at' => now()->subDay(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->acceptInvitation($invite->token, $newbie);
    }

    public function test_accept_invitation_rejects_mismatched_email(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['email' => 'someone-else@example.com']);
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $invite = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'invited_by_id' => $owner->id,
            'email' => 'newbie@example.com',
            'role' => 'Member',
            'token' => str_repeat('c', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->acceptInvitation($invite->token, $other);
    }

    public function test_transfer_ownership_two_step_flow(): void
    {
        $current = User::factory()->create();
        $next = User::factory()->create();
        $tenant = $this->service->create($current, ['name' => 'Acme']);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $next->id,
            'joined_at' => now(),
        ]);

        $transfer = $this->service->transferOwnership($tenant, $current, $next);
        $this->assertNotNull($transfer->token);
        $this->assertNull($transfer->accepted_at);

        $updated = $this->service->acceptOwnerTransfer($transfer->token, $next);

        $this->assertSame($next->id, $updated->owner_id);
        setPermissionsTeamId($tenant->id);
        $this->assertTrue($next->fresh()->hasRole('Owner'));
        $this->assertTrue($current->fresh()->hasRole('Admin'));
    }

    public function test_transfer_ownership_rejects_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $this->expectException(RuntimeException::class);

        $this->service->transferOwnership($tenant, $other, $owner);
    }

    public function test_soft_delete_marks_status_and_deletes_at(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->service->create($owner, ['name' => 'Acme']);

        $this->service->softDelete($tenant);

        $fresh = Tenant::withTrashed()->find($tenant->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame('pending_deletion', $fresh->status);
    }
}
