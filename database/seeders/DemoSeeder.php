<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\LoginHistory;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data on top of UserSeeder — call explicitly:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * UserSeeder creates the test users + the Acme tenant + role wiring. This
 * seeder adds: onboarded marker, owner's current_tenant_id, Pro plan +
 * subscription, three paid invoices + payments, one open invitation, and a
 * few login-history rows so the security page has something to show.
 * Idempotent — re-runs without dupes.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Guarantee users + Acme tenant + role assignments exist.
        $this->call(UserSeeder::class);

        /** @var TenantService $service */
        $service = app(TenantService::class);

        $owner = User::query()->where('email', 'owner@acme.test')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();

        // Mark as onboarded if not already, and set owner's current tenant.
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        if (! array_key_exists('onboarded_at', $settings)) {
            $settings['onboarded_at'] = now()->toIso8601String();
            $settings['logo_placeholder'] = true;
            $service->update($tenant, ['settings' => $settings]);
            $tenant = $tenant->fresh();
        }

        if ($owner->current_tenant_id !== $tenant->id) {
            $owner->forceFill(['current_tenant_id' => $tenant->id])->save();
        }

        // Sample subscription on a Pro plan.
        $plan = Plan::firstOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'Demo Pro plan',
                'price_cents' => 4900,
                'currency' => 'USD',
                'billing_period' => 'month',
                'billing_interval' => 1,
                'trial_days' => 0,
                'features' => ['unlimited_users', 'priority_support'],
                'gateway_ids' => [],
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 10,
            ],
        );

        if (! Subscription::query()->where('tenant_id', $tenant->id)->exists()) {
            Subscription::factory()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'unit_amount_cents' => $plan->price_cents,
            ]);
        }

        // 3 paid invoices + matching payments.
        $existing = Invoice::query()->where('tenant_id', $tenant->id)->count();
        if ($existing < 3) {
            $needed = 3 - $existing;
            foreach (range(1, $needed) as $i) {
                $invoice = Invoice::factory()->create([
                    'tenant_id' => $tenant->id,
                    'status' => 'paid',
                    'total_cents' => $plan->price_cents,
                    'amount_paid_cents' => $plan->price_cents,
                    'amount_due_cents' => 0,
                    'paid_at' => now()->subDays($i * 30),
                    'issued_at' => now()->subDays($i * 30),
                ]);

                Payment::factory()->create([
                    'tenant_id' => $tenant->id,
                    'invoice_id' => $invoice->id,
                    'status' => 'succeeded',
                    'amount_cents' => $plan->price_cents,
                    'captured_at' => $invoice->paid_at,
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            }
        }

        // One open invitation to demo the invitations table.
        $hasPending = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'pending@acme.test')
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasPending) {
            $service->invite(
                $tenant,
                $owner,
                'pending@acme.test',
                'Member',
                autoAttach: false,
            );
        }

        // A few login history rows so the security page has something to show.
        if (LoginHistory::query()->where('user_id', $owner->id)->count() < 3) {
            foreach (range(1, 3) as $i) {
                LoginHistory::factory()->create([
                    'user_id' => $owner->id,
                    'email' => $owner->email,
                    'outcome' => 'succeeded',
                    'ip' => '203.0.113.'.$i,
                    'user_agent' => 'Mozilla/5.0 DemoSeeder',
                    'created_at' => now()->subHours($i),
                ]);
            }
        }

        $this->command?->info('DemoSeeder: Acme Corp tenant + subscription + 3 paid invoices + 1 open invitation seeded.');
    }
}
