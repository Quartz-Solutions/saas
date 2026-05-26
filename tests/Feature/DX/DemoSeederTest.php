<?php

namespace Tests\Feature\DX;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_expected_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'owner@acme.test']);
        $this->assertDatabaseHas('users', ['email' => 'admin@acme.test']);
        $this->assertDatabaseHas('users', ['email' => 'member@acme.test']);
        $this->assertDatabaseHas('tenants', ['slug' => 'acme', 'name' => 'Acme Corp']);

        $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();

        $this->assertSame(3, $tenant->members()->count());
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(3, Invoice::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(3, Payment::query()->where('tenant_id', $tenant->id)->count());
        $this->assertArrayHasKey('onboarded_at', $tenant->settings);
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'owner@acme.test')->count());
        $this->assertSame(1, Tenant::query()->where('slug', 'acme')->count());
    }
}
