<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

class ImpersonationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_logs_in_as_target_and_records_log(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        Auth::loginUsingId($admin->id);

        $service = app(ImpersonationService::class);
        $log = $service->start($admin, $target, request(), 'support ticket #42');

        $this->assertSame($target->id, Auth::id());
        $this->assertTrue($service->isImpersonating());
        $this->assertSame($admin->id, $service->impersonatorId());
        $this->assertDatabaseHas('impersonation_logs', [
            'id' => $log->id,
            'impersonator_id' => $admin->id,
            'impersonated_id' => $target->id,
            'reason' => 'support ticket #42',
            'ended_at' => null,
        ]);
    }

    public function test_stop_restores_impersonator(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        Auth::loginUsingId($admin->id);

        $service = app(ImpersonationService::class);
        $service->start($admin, $target);

        $restored = $service->stop();

        $this->assertNotNull($restored);
        $this->assertSame($admin->id, Auth::id());
        $this->assertFalse($service->isImpersonating());
    }

    public function test_stop_is_noop_when_not_impersonating(): void
    {
        $admin = User::factory()->create();
        Auth::loginUsingId($admin->id);

        $service = app(ImpersonationService::class);
        $this->assertNull($service->stop());
        $this->assertSame($admin->id, Auth::id());
    }

    public function test_start_rejects_self_impersonation(): void
    {
        $this->expectException(RuntimeException::class);

        $admin = User::factory()->create();
        Auth::loginUsingId($admin->id);

        app(ImpersonationService::class)->start($admin, $admin);
    }
}
