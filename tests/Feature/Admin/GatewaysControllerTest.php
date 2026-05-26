<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\Admin\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GatewaysControllerTest extends TestCase
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

    public function test_index_requires_super_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/gateways')
            ->assertStatus(403);
    }

    public function test_index_lists_all_catalog_gateways(): void
    {
        $admin = $this->makeSuperAdmin();
        $expected = count((array) config('billing.gateways', []));

        $this->actingAs($admin)
            ->get('/admin/gateways')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/gateways/index')
                ->has('gateways', $expected)
                ->where('gateways.0.id', 'stripe')
            );
    }

    public function test_index_marks_stripe_as_shipped_others_as_planned(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/gateways')
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('admin/gateways/index');

                $stripe = collect($page->toArray()['props']['gateways'])
                    ->firstWhere('id', 'stripe');
                $this->assertSame('shipped', $stripe['driver_status']);

                $paypal = collect($page->toArray()['props']['gateways'])
                    ->firstWhere('id', 'paypal');
                $this->assertSame('planned', $paypal['driver_status']);

                return $page;
            });
    }

    public function test_edit_renders_stripe_field_form(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/gateways/stripe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/gateways/edit')
                ->where('gateway.id', 'stripe')
                ->where('gateway.driver_status', 'shipped')
                ->has('fields.STRIPE_SECRET')
                ->where('fields.STRIPE_SECRET.is_secret', true)
            );
    }

    public function test_edit_renders_planned_gateway_with_field_catalog(): void
    {
        $admin = $this->makeSuperAdmin();

        // PayPal is planned but has field declarations so admins can pre-save credentials.
        $this->actingAs($admin)
            ->get('/admin/gateways/paypal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/gateways/edit')
                ->where('gateway.id', 'paypal')
                ->where('gateway.driver_status', 'planned')
                ->has('fields.PAYPAL_CLIENT_ID')
                ->where('fields.PAYPAL_CLIENT_SECRET.is_secret', true)
            );
    }

    public function test_edit_renders_gateway_with_no_fields_as_read_only(): void
    {
        $admin = $this->makeSuperAdmin();

        // Hand-craft an empty-fields catalog entry to exercise the read-only path.
        config(['billing.gateways.zzz_no_fields' => [
            'name' => 'Test gateway',
            'description' => 'Driver scaffold pending — no fields yet.',
            'regions' => ['Test'],
            'capabilities' => [],
            'driver_status' => 'planned',
            'documentation_url' => null,
            'enabled' => false,
            'fields' => [],
        ]]);
        app(AppSettingsService::class)->invalidate();

        $this->actingAs($admin)
            ->get('/admin/gateways/zzz_no_fields')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/gateways/edit')
                ->where('gateway.id', 'zzz_no_fields')
                ->where('fields', null)
            );
    }

    public function test_edit_404s_for_unknown_gateway(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/gateways/nonexistent')
            ->assertNotFound();
    }

    public function test_update_persists_stripe_credentials(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->patch('/admin/gateways/stripe', [
                'STRIPE_ENABLED' => true,
                'STRIPE_KEY' => 'pk_test_abc',
                'STRIPE_SECRET' => 'sk_test_xyz',
                'STRIPE_WEBHOOK_SECRET' => 'whsec_123',
                'STRIPE_API_VERSION' => '2024-11-20.acacia',
                'STRIPE_PRICE_PRO' => '',
                'STRIPE_PRICE_ENTERPRISE' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'key' => 'STRIPE_KEY',
            'group' => 'gateway_stripe',
        ]);

        $secret = AppSetting::query()->where('key', 'STRIPE_SECRET')->firstOrFail();
        $this->assertTrue((bool) $secret->is_secret);
        $this->assertSame('sk_test_xyz', $secret->value);
        // Encrypted in storage.
        $this->assertNotSame('sk_test_xyz', $secret->getRawOriginal('value'));
    }

    public function test_update_planned_gateway_forces_disabled_state(): void
    {
        $admin = $this->makeSuperAdmin();

        // Hand-craft a planned-gateway field set in config so the test isn't
        // coupled to which gateways have fields at any given moment.
        config([
            'billing.gateways.paypal.fields' => [
                'PAYPAL_ENABLED' => [
                    'config_path' => 'billing.gateways.paypal.enabled',
                    'type' => 'bool',
                    'rules' => 'boolean',
                    'label' => 'Enabled',
                ],
                'PAYPAL_CLIENT_ID' => [
                    'config_path' => 'billing.gateways.paypal.client_id',
                    'type' => 'string',
                    'rules' => 'nullable|string|max:255',
                    'label' => 'Client ID',
                ],
            ],
        ]);
        app(AppSettingsService::class)->invalidate();

        $this->actingAs($admin)
            ->patch('/admin/gateways/paypal', [
                'PAYPAL_ENABLED' => true, // server-side forced to false
                'PAYPAL_CLIENT_ID' => 'cred_123',
            ])
            ->assertRedirect();

        // Client id is saved …
        $this->assertDatabaseHas('app_settings', ['key' => 'PAYPAL_CLIENT_ID']);
        // … but the enable flag wasn't persisted as true.
        $stored = AppSetting::query()->where('key', 'PAYPAL_ENABLED')->first();
        $this->assertTrue($stored === null || $stored->value === '0');
    }

    public function test_secret_mask_does_not_overwrite_existing(): void
    {
        $admin = $this->makeSuperAdmin();

        AppSetting::create([
            'group' => 'gateway_stripe',
            'key' => 'STRIPE_SECRET',
            'is_secret' => true,
            'value' => 'sk_test_KEEP_ME',
        ]);

        $this->actingAs($admin)
            ->patch('/admin/gateways/stripe', [
                'STRIPE_ENABLED' => true,
                'STRIPE_KEY' => 'pk_new',
                'STRIPE_SECRET' => AppSettingsService::SECRET_MASK,
                'STRIPE_WEBHOOK_SECRET' => '',
                'STRIPE_API_VERSION' => '2024-11-20.acacia',
                'STRIPE_PRICE_PRO' => '',
                'STRIPE_PRICE_ENTERPRISE' => '',
            ])
            ->assertRedirect();

        $secret = AppSetting::query()->where('key', 'STRIPE_SECRET')->firstOrFail();
        $this->assertSame('sk_test_KEEP_ME', $secret->value);
    }

    public function test_apply_overrides_writes_stripe_enabled_to_runtime_config(): void
    {
        $this->makeSuperAdmin();

        AppSetting::create([
            'group' => 'gateway_stripe',
            'key' => 'STRIPE_ENABLED',
            'is_secret' => false,
            'value' => '1',
        ]);
        AppSetting::create([
            'group' => 'gateway_stripe',
            'key' => 'STRIPE_SECRET',
            'is_secret' => true,
            'value' => 'sk_test_zzz',
        ]);

        Cache::forget(AppSettingsService::CACHE_KEY);
        app(AppSettingsService::class)->applyOverrides();

        $this->assertTrue((bool) config('billing.gateways.stripe.enabled'));
        $this->assertSame('sk_test_zzz', config('billing.gateways.stripe.secret'));
    }

    public function test_validation_blocks_invalid_payload(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/gateways/stripe')
            ->patch('/admin/gateways/stripe', [
                'STRIPE_API_VERSION' => str_repeat('x', 200), // max:64
            ])
            ->assertRedirect('/admin/gateways/stripe')
            ->assertSessionHasErrors('STRIPE_API_VERSION');
    }
}
