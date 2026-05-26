<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\Admin\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
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

    public function test_settings_index_requires_super_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertStatus(403);
    }

    public function test_settings_index_renders_groups_with_catalog(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/settings/index')
                ->has('groups.app')
                ->has('groups.mail')
                ->has('groups.oauth')
                ->has('groups.stripe')
                ->has('groups.sentry')
                ->has('groups.slack')
                ->has('groups.aws')
                ->where('groups.mail.fields.MAIL_HOST.key', 'MAIL_HOST')
            );
    }

    public function test_update_persists_values_and_encrypts_secrets(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->patch('/admin/settings/mail', [
                'MAIL_MAILER' => 'smtp',
                'MAIL_HOST' => 'smtp.mailgun.org',
                'MAIL_PORT' => 587,
                'MAIL_USERNAME' => 'user@example.com',
                'MAIL_PASSWORD' => 'super-secret',
                'MAIL_FROM_ADDRESS' => 'noreply@example.com',
                'MAIL_FROM_NAME' => 'Example',
            ])
            ->assertRedirect();

        $host = AppSetting::query()->where('key', 'MAIL_HOST')->firstOrFail();
        $this->assertSame('smtp.mailgun.org', $host->value);
        $this->assertFalse((bool) $host->is_secret);
        $this->assertSame('smtp.mailgun.org', $host->getRawOriginal('value'));

        $password = AppSetting::query()->where('key', 'MAIL_PASSWORD')->firstOrFail();
        $this->assertTrue((bool) $password->is_secret);
        $this->assertSame('super-secret', $password->value); // decrypted via cast
        $this->assertNotSame('super-secret', $password->getRawOriginal('value'));
        $this->assertSame('super-secret', Crypt::decryptString($password->getRawOriginal('value')));
        $this->assertSame($admin->id, $password->updated_by);
    }

    public function test_secret_mask_does_not_overwrite_existing_value(): void
    {
        $admin = $this->makeSuperAdmin();

        AppSetting::create([
            'group' => 'stripe',
            'key' => 'STRIPE_SECRET',
            'is_secret' => true,
            'value' => 'sk_test_KEEP_ME',
        ]);

        $this->actingAs($admin)
            ->patch('/admin/settings/stripe', [
                'STRIPE_KEY' => 'pk_test_new',
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

    public function test_empty_string_clears_override(): void
    {
        $admin = $this->makeSuperAdmin();

        AppSetting::create([
            'group' => 'sentry',
            'key' => 'SENTRY_DSN',
            'is_secret' => true,
            'value' => 'https://abc@sentry.io/1',
        ]);

        $this->actingAs($admin)
            ->patch('/admin/settings/sentry', [
                'SENTRY_DSN' => '',
                'SENTRY_ENVIRONMENT' => '',
                'SENTRY_TRACES_SAMPLE_RATE' => '',
                'SENTRY_PROFILES_SAMPLE_RATE' => '',
                'SENTRY_SEND_DEFAULT_PII' => false,
            ])
            ->assertRedirect();

        $this->assertNull(AppSetting::query()->where('key', 'SENTRY_DSN')->first());
    }

    public function test_index_renders_secrets_masked(): void
    {
        $admin = $this->makeSuperAdmin();

        AppSetting::create([
            'group' => 'mail',
            'key' => 'MAIL_PASSWORD',
            'is_secret' => true,
            'value' => 'secret-pw',
        ]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where(
                    'groups.mail.fields.MAIL_PASSWORD.value',
                    AppSettingsService::SECRET_MASK,
                )
                ->where('groups.mail.fields.MAIL_PASSWORD.has_value', true)
            );
    }

    public function test_apply_overrides_writes_runtime_config(): void
    {
        $this->makeSuperAdmin();

        AppSetting::create([
            'group' => 'app',
            'key' => 'APP_NAME',
            'is_secret' => false,
            'value' => 'Quartz',
        ]);

        Cache::forget(AppSettingsService::CACHE_KEY);
        app(AppSettingsService::class)->applyOverrides();

        $this->assertSame('Quartz', Config::get('app.name'));
    }

    public function test_update_invalidates_cache_so_next_request_reads_new_value(): void
    {
        $admin = $this->makeSuperAdmin();

        app(AppSettingsService::class)->allOverrides(); // prime cache

        $this->actingAs($admin)
            ->patch('/admin/settings/app', [
                'APP_NAME' => 'Acme',
                'APP_URL' => 'https://acme.test',
                'APP_LOCALE' => 'en',
                'APP_FALLBACK_LOCALE' => 'en',
            ])
            ->assertRedirect();

        $this->assertSame(
            'Acme',
            app(AppSettingsService::class)->allOverrides()['APP_NAME'] ?? null,
        );
    }

    public function test_validation_blocks_invalid_payload(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/settings')
            ->patch('/admin/settings/mail', [
                'MAIL_MAILER' => 'not-a-mailer',
                'MAIL_FROM_ADDRESS' => 'not-an-email',
                'MAIL_FROM_NAME' => '',
            ])
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors(['MAIL_MAILER', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME']);
    }

    public function test_test_mail_endpoint_sends_message_to_admin(): void
    {
        // Use the array transport so the call succeeds without external SMTP.
        config(['mail.default' => 'array']);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->postJson('/admin/settings/mail/test')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertGreaterThan(0, $sent->count());
        $this->assertStringContainsString($admin->email, $sent->first()->toString());
    }

    public function test_test_endpoint_reports_failure_when_credentials_missing(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->postJson('/admin/settings/stripe/test')
            ->assertOk()
            ->assertJson(['ok' => false]);
    }

    public function test_unknown_group_returns_404(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->patch('/admin/settings/nonexistent', [])
            ->assertNotFound();
    }

    public function test_audit_log_records_setting_change(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->patch('/admin/settings/app', [
                'APP_NAME' => 'Audited',
                'APP_URL' => 'https://audited.test',
                'APP_LOCALE' => 'en',
                'APP_FALLBACK_LOCALE' => 'en',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'created',
            'auditable_type' => AppSetting::class,
        ]);
    }
}
