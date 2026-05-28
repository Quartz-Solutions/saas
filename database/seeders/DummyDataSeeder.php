<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Large-volume demo data for populating the UI:
 *
 *   docker compose exec app php artisan db:seed --class=DummyDataSeeder
 *
 * Generates ~10,000 users spread across 150 tenants, each tenant with a
 * subscription on a varied plan + status, plus invoices and payments for the
 * paying ones. Used to exercise the admin Users / Tenants tables, the
 * tenant member rosters, and the billing screens with realistic volume.
 *
 * Idempotent guard: everything it creates uses the `@dummy.test` email
 * domain and `dummy-` slug prefix. If any dummy user already exists the
 * seeder no-ops, so re-running won't duplicate. To regenerate, wipe first:
 *
 *   docker compose exec app php artisan migrate:fresh --seed --seeder=DummyDataSeeder
 *
 * NOT wired into DatabaseSeeder — this is heavy, opt-in demo data.
 */
class DummyDataSeeder extends Seeder
{
    private const TENANT_COUNT = 1500;

    private const USER_COUNT = 1000000;

    private const EMAIL_DOMAIN = 'dummy.test';

    private const SLUG_PREFIX = 'dummy-';

    /** Chunk size for bulk inserts. */
    private const CHUNK = 1000;

    public function run(): void
    {
        if (User::query()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            $this->command?->warn('DummyDataSeeder: dummy data already present — skipping. Run `migrate:fresh` to regenerate.');

            return;
        }

        // Keep memory flat during the big inserts.
        DB::connection()->disableQueryLog();

        // Make sure the plan catalog + currencies exist.
        $this->call(PlansSeeder::class);

        $plans = Plan::query()
            ->whereIn('slug', ['free', 'pro', 'enterprise'])
            ->get()
            ->keyBy('slug');

        if ($plans->isEmpty()) {
            $this->command?->error('DummyDataSeeder: no plans found — aborting.');

            return;
        }

        $password = Hash::make('password');

        $this->command?->info('DummyDataSeeder: creating '.self::TENANT_COUNT.' tenants + owners…');
        $tenants = $this->createTenantsWithOwners($password);

        $this->command?->info('DummyDataSeeder: creating member users + memberships…');
        $this->createMembersAndMemberships($tenants, $password);

        $this->command?->info('DummyDataSeeder: creating subscriptions + invoices + payments…');
        $this->createBilling($tenants, $plans);

        $this->command?->info('DummyDataSeeder: promoting a few admins…');
        $this->promoteRandomAdmins($tenants);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $userCount = User::query()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->count();
        $this->command?->info(
            "DummyDataSeeder: done — {$userCount} users across ".self::TENANT_COUNT.' tenants. Login: any with password "password".'
        );
    }

    /**
     * Create one owner user + one tenant per slot via the canonical service
     * (so per-team roles + the owner membership are wired correctly).
     *
     * @return array<int, Tenant>
     */
    private function createTenantsWithOwners(string $password): array
    {
        /** @var TenantService $service */
        $service = app(TenantService::class);
        $tenants = [];

        for ($i = 1; $i <= self::TENANT_COUNT; $i++) {
            $owner = User::query()->create([
                'name' => fake()->name(),
                'email' => "owner{$i}@".self::EMAIL_DOMAIN,
                'email_verified_at' => now(),
                'password' => $password,
            ]);

            $tenant = $service->create($owner, [
                'name' => fake()->unique()->company(),
                'slug' => self::SLUG_PREFIX.$i.'-'.Str::lower(Str::random(5)),
                'currency' => 'USD',
                'locale' => 'en',
                'timezone' => fake()->timezone(),
            ]);

            // Owner lands on their own tenant + an onboarded marker so the
            // dashboard renders without the onboarding wizard.
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $settings['onboarded_at'] = now()->toIso8601String();
            $tenant->forceFill(['settings' => $settings])->save();
            $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

            $tenants[] = $tenant->fresh();
        }

        return $tenants;
    }

    /**
     * Bulk-insert the remaining users and spread them across tenants as
     * members. The owner users already created count toward USER_COUNT.
     *
     * @param  array<int, Tenant>  $tenants
     */
    private function createMembersAndMemberships(array $tenants, string $password): void
    {
        $tenantIds = array_map(fn (Tenant $t) => $t->id, $tenants);
        $memberCount = self::USER_COUNT - count($tenants);

        // 1) Bulk-insert member users, each pre-assigned a home tenant via
        //    current_tenant_id (memberships are derived from it below).
        $rows = [];
        for ($i = 1; $i <= $memberCount; $i++) {
            $createdAt = Carbon::now()->subDays(random_int(0, 365))->subMinutes(random_int(0, 1440));
            $verified = random_int(1, 100) <= 90;     // ~10% unverified
            $suspended = random_int(1, 100) <= 3;     // ~3% suspended
            $loggedIn = random_int(1, 100) <= 70;     // ~70% have logged in

            $rows[] = [
                'name' => fake()->name(),
                'email' => "member{$i}@".self::EMAIL_DOMAIN,
                'email_verified_at' => $verified ? $createdAt : null,
                'password' => $password,
                'locale' => 'en',
                'timezone' => 'UTC',
                'current_tenant_id' => $tenantIds[array_rand($tenantIds)],
                'last_login_at' => $loggedIn ? Carbon::now()->subDays(random_int(0, 30)) : null,
                'last_login_ip' => $loggedIn ? fake()->ipv4() : null,
                'suspended_at' => $suspended ? Carbon::now()->subDays(random_int(0, 60)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) >= self::CHUNK) {
                DB::table('users')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('users')->insert($rows);
        }

        // 2) Derive memberships from each member's home tenant.
        User::query()
            ->where('email', 'like', 'member%@'.self::EMAIL_DOMAIN)
            ->select(['id', 'current_tenant_id', 'created_at'])
            ->chunkById(2000, function ($users): void {
                $memberships = [];
                foreach ($users as $u) {
                    $joined = $u->created_at ?? now();
                    $memberships[] = [
                        'tenant_id' => $u->current_tenant_id,
                        'user_id' => $u->id,
                        'invited_by_id' => null,
                        'joined_at' => $joined,
                        'last_seen_at' => random_int(1, 100) <= 60
                            ? Carbon::now()->subDays(random_int(0, 14))
                            : null,
                        'created_at' => $joined,
                        'updated_at' => $joined,
                    ];
                }
                DB::table('tenant_memberships')->insert($memberships);
            });
    }

    /**
     * One subscription per tenant with a varied plan + status, plus invoices
     * and payments for the paying ones.
     *
     * @param  array<int, Tenant>  $tenants
     * @param  Collection<string, Plan>  $plans
     */
    private function createBilling(array $tenants, $plans): void
    {
        foreach ($tenants as $tenant) {
            $planSlug = $this->weighted(['free' => 30, 'pro' => 50, 'enterprise' => 20]);
            $plan = $plans->get($planSlug) ?? $plans->first();

            $status = $planSlug === 'free'
                ? 'active'
                : $this->weighted(['active' => 55, 'trialing' => 15, 'past_due' => 10, 'canceled' => 20]);

            $this->makeSubscription($tenant, $plan, $status);

            // Paid, non-trial subscriptions get a short invoice + payment
            // history so the billing screens aren't empty.
            if ($planSlug !== 'free' && in_array($status, ['active', 'past_due', 'canceled'], true)) {
                $this->makeInvoiceHistory($tenant, $plan, $status);
            }
        }
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, string $status): void
    {
        $now = Carbon::now();
        $attrs = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'gateway' => (int) $plan->price_cents === 0 ? 'free' : 'stripe',
            'gateway_subscription_id' => 'sub_'.Str::lower(Str::random(14)),
            'status' => $status,
            'currency' => 'USD',
            'unit_amount_cents' => (int) $plan->price_cents,
            'quantity' => 1,
            'current_period_start' => $now->copy()->subDays(random_int(0, 25)),
            'current_period_end' => $now->copy()->addDays(random_int(5, 30)),
            'cancel_at_period_end' => false,
            'metadata' => [],
        ];

        if ($status === 'trialing') {
            $attrs['trial_starts_at'] = $now->copy()->subDays(random_int(1, 6));
            $attrs['trial_ends_at'] = $now->copy()->addDays(random_int(1, 7));
        }

        if ($status === 'canceled') {
            $attrs['canceled_at'] = $now->copy()->subDays(random_int(1, 40));
            $attrs['ends_at'] = $now->copy()->subDays(random_int(0, 5));
            $attrs['cancellation_reason'] = fake()->randomElement(['too_expensive', 'missing_features', 'switched_service', 'other']);
        }

        Subscription::query()->create($attrs);
    }

    private function makeInvoiceHistory(Tenant $tenant, Plan $plan, string $status): void
    {
        $count = random_int(1, 3);
        for ($n = $count; $n >= 1; $n--) {
            $issuedAt = Carbon::now()->subDays($n * 30 + random_int(0, 5));

            // The most recent invoice reflects the subscription state.
            $isLatest = $n === 1;
            $invoiceStatus = match (true) {
                $isLatest && $status === 'past_due' => 'open',
                $isLatest && $status === 'canceled' => 'void',
                default => 'paid',
            };

            $paid = $invoiceStatus === 'paid';

            $invoice = Invoice::factory()->create([
                'tenant_id' => $tenant->id,
                'status' => $invoiceStatus,
                'currency' => 'USD',
                'subtotal_cents' => (int) $plan->price_cents,
                'total_cents' => (int) $plan->price_cents,
                'amount_paid_cents' => $paid ? (int) $plan->price_cents : 0,
                'amount_due_cents' => $paid ? 0 : (int) $plan->price_cents,
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->copy()->addDays(14),
                'paid_at' => $paid ? $issuedAt->copy()->addDays(random_int(0, 3)) : null,
                'voided_at' => $invoiceStatus === 'void' ? $issuedAt->copy()->addDays(2) : null,
            ]);

            if ($paid) {
                Payment::factory()->create([
                    'tenant_id' => $tenant->id,
                    'invoice_id' => $invoice->id,
                    'status' => 'succeeded',
                    'currency' => 'USD',
                    'amount_cents' => (int) $plan->price_cents,
                    'authorized_at' => $invoice->paid_at,
                    'captured_at' => $invoice->paid_at,
                ]);
            } elseif ($invoiceStatus === 'open' && $status === 'past_due') {
                // A failed charge attempt to explain the past-due state.
                Payment::factory()->create([
                    'tenant_id' => $tenant->id,
                    'invoice_id' => $invoice->id,
                    'status' => 'failed',
                    'currency' => 'USD',
                    'amount_cents' => (int) $plan->price_cents,
                    'authorized_at' => null,
                    'captured_at' => null,
                    'failed_at' => $issuedAt->copy()->addDays(1),
                    'failure_code' => 'card_declined',
                    'failure_message' => 'Your card was declined.',
                ]);
            }
        }
    }

    /**
     * Promote one random member per tenant to Admin so the member roster
     * shows a mix of roles (Owner / Admin / Member).
     *
     * @param  array<int, Tenant>  $tenants
     */
    private function promoteRandomAdmins(array $tenants): void
    {
        foreach ($tenants as $tenant) {
            $member = User::query()
                ->where('current_tenant_id', $tenant->id)
                ->where('email', 'like', 'member%@'.self::EMAIL_DOMAIN)
                ->inRandomOrder()
                ->first();

            if ($member === null) {
                continue;
            }

            setPermissionsTeamId($tenant->id);
            if (! $member->hasRole('Admin')) {
                $member->assignRole('Admin');
            }
        }
    }

    /**
     * Pick a key from a [key => weight] map, weighted-randomly.
     *
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $cursor = 0;
        foreach ($weights as $key => $weight) {
            $cursor += $weight;
            if ($roll <= $cursor) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
