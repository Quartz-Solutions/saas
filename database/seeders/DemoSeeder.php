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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo seeder — call explicitly with `php artisan db:seed --class=DemoSeeder`.
 *
 * Creates the canonical "Acme Corp" tenant + three users with the per-team
 * roles (Owner / Admin / Member) so a freshly-cloned boilerplate has a usable
 * dataset to log in to. Idempotent — re-runs without dupes.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Currencies (Currency table is referenced by tenants + plans).
        $this->call(CurrencySeeder::class);

        /** @var TenantService $service */
        $service = app(TenantService::class);

        $owner = User::firstOrCreate(
            ['email' => 'owner@acme.test'],
            [
                'name' => 'Olivia Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@acme.test'],
            [
                'name' => 'Adam Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $member = User::firstOrCreate(
            ['email' => 'member@acme.test'],
            [
                'name' => 'Marta Member',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $tenant = Tenant::query()->where('slug', 'acme')->first();

        if ($tenant === null) {
            $tenant = $service->create($owner, [
                'name' => 'Acme Corp',
                'slug' => 'acme',
                'locale' => 'en',
                'timezone' => 'UTC',
                'currency' => 'USD',
                'settings' => [
                    'onboarded_at' => now()->toIso8601String(),
                    'logo_placeholder' => true,
                ],
            ]);
        } else {
            // Mark as onboarded if not already.
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            if (! array_key_exists('onboarded_at', $settings)) {
                $settings['onboarded_at'] = now()->toIso8601String();
                $service->update($tenant, ['settings' => $settings]);
                $tenant = $tenant->fresh();
            }
        }

        // Attach admin + member if not already members.
        if (! $tenant->members()->whereKey($admin->id)->exists()) {
            $service->invite($tenant, $owner, 'admin@acme.test', 'Admin');
        }

        if (! $tenant->members()->whereKey($member->id)->exists()) {
            $service->invite($tenant, $owner, 'member@acme.test', 'Member');
        }

        // Set the owner's "current tenant" for nicer first-login UX.
        $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

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
        if (Invoice::query()->where('tenant_id', $tenant->id)->count() < 3) {
            $needed = 3 - Invoice::query()->where('tenant_id', $tenant->id)->count();
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
        if (
            ! TenantInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', 'pending@acme.test')
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->exists()
        ) {
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

        $this->command?->info('Demo data seeded: tenant "Acme Corp" with 3 users (owner/admin/member@acme.test, password: password).');
    }
}
