<?php

namespace Tests\Feature\Schema;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\TenantOwnerTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_be_created_via_factory(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
        $this->assertNotNull($tenant->slug);
        $this->assertInstanceOf(User::class, $tenant->owner);
    }

    public function test_tenant_settings_cast_to_array(): void
    {
        $tenant = Tenant::factory()->create(['settings' => ['theme' => 'dark', 'features' => ['beta']]]);

        $fresh = $tenant->fresh();
        $this->assertIsArray($fresh->settings);
        $this->assertSame('dark', $fresh->settings['theme']);
        $this->assertSame(['beta'], $fresh->settings['features']);
    }

    public function test_tenant_uses_soft_deletes(): void
    {
        $tenant = Tenant::factory()->create();

        $tenant->delete();

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertNotNull(Tenant::withTrashed()->find($tenant->id));
    }

    public function test_tenant_membership_links_user_and_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($membership->tenant->is($tenant));
        $this->assertTrue($membership->user->is($user));
    }

    public function test_user_can_belong_to_many_tenants(): void
    {
        $user = User::factory()->create();
        $tenants = Tenant::factory()->count(3)->create();

        foreach ($tenants as $tenant) {
            TenantMembership::factory()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
            ]);
        }

        $this->assertCount(3, $user->tenants);
    }

    public function test_tenant_invitation_has_unique_token(): void
    {
        $invitation = TenantInvitation::factory()->create();

        $this->assertNotNull($invitation->token);
        $this->assertSame(64, strlen($invitation->token));
        $this->assertTrue($invitation->inviter->exists);
    }

    public function test_owner_transfer_carries_both_users(): void
    {
        $transfer = TenantOwnerTransfer::factory()->create();

        $this->assertInstanceOf(User::class, $transfer->currentOwner);
        $this->assertInstanceOf(User::class, $transfer->newOwner);
        $this->assertTrue($transfer->currentOwner->isNot($transfer->newOwner));
    }

    public function test_user_current_tenant_relationship(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);

        $this->assertTrue($user->currentTenant->is($tenant));
    }
}
