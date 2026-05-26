<?php

namespace Tests\Feature\Billing;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2]
        );
    }

    public function test_invoices_index_renders_for_member(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $this->actingAs($owner)
            ->get(route('tenants.billing.invoices.index', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('billing/invoices')
                ->has('invoices.data')
                ->has('invoices.meta')
            );
    }

    public function test_invoices_index_lists_only_tenant_invoices(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $otherOwner = User::factory()->create();
        $otherTenant = app(TenantService::class)->create($otherOwner, ['name' => 'Beta']);

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'number' => 'INV-MINE-001']);
        Invoice::factory()->create(['tenant_id' => $otherTenant->id, 'number' => 'INV-THEIRS-001']);

        $this->actingAs($owner)
            ->get(route('tenants.billing.invoices.index', ['tenantSlug' => $tenant->slug]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('billing/invoices')
                ->has('invoices.data', 1)
                ->where('invoices.data.0.number', 'INV-MINE-001')
            );
    }

    public function test_invoices_index_forbidden_for_non_member(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.billing.invoices.index', ['tenantSlug' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_invoices_pdf_forbids_cross_tenant_download(): void
    {
        $owner = User::factory()->create();
        $tenant = app(TenantService::class)->create($owner, ['name' => 'Acme']);

        $otherOwner = User::factory()->create();
        $otherTenant = app(TenantService::class)->create($otherOwner, ['name' => 'Beta']);

        $invoice = Invoice::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($owner)
            ->get(route('tenants.billing.invoices.pdf', [
                'tenantSlug' => $tenant->slug,
                'invoice' => $invoice->id,
            ]))
            ->assertForbidden();
    }
}
