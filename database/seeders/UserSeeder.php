<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Canonical test users — one per role surface in the app. Invoked explicitly:
 *
 *   docker compose exec app php artisan db:seed --class=UserSeeder
 *
 * Creates (idempotent):
 *
 *   super@example.test  password   Super Admin     (global, can access /admin/*)
 *   owner@acme.test     password   Owner of Acme   (full tenant control)
 *   admin@acme.test     password   Admin of Acme   (manage members + billing)
 *   member@acme.test    password   Member of Acme  (regular tenant user)
 *   user@example.test   password   no role         (plain authenticated user)
 *
 * The Acme tenant is created here too so the per-team Spatie roles have a
 * team to attach to. DemoSeeder calls this seeder to guarantee the surface
 * before adding subscription / invoice / login-history demo data.
 */
class UserSeeder extends Seeder
{
    /** @var array<int, array{email: string, name: string, role: ?string}> */
    private const TENANT_USERS = [
        ['email' => 'owner@acme.test', 'name' => 'Olivia Owner', 'role' => 'Owner'],
        ['email' => 'admin@acme.test', 'name' => 'Adam Admin', 'role' => 'Admin'],
        ['email' => 'member@acme.test', 'name' => 'Marta Member', 'role' => 'Member'],
    ];

    public function run(): void
    {
        // Ensure currencies + global "Super Admin" role + any-existing-tenant team roles exist.
        $this->call(CurrencySeeder::class);
        $this->call(RolesSeeder::class);

        /** @var TenantService $tenants */
        $tenants = app(TenantService::class);

        // 1) Global Super Admin (no tenant team).
        $super = $this->makeUser('super@example.test', 'Sasha Superadmin');
        setPermissionsTeamId(null);
        Role::findOrCreate('Super Admin', 'web');
        if (! $super->hasRole('Super Admin')) {
            $super->assignRole('Super Admin');
        }

        // 2) Acme tenant + Owner. TenantService::create() makes the owner role.
        $owner = $this->makeUser(self::TENANT_USERS[0]['email'], self::TENANT_USERS[0]['name']);
        $acme = Tenant::query()->where('slug', 'acme')->first();
        if ($acme === null) {
            $acme = $tenants->create($owner, [
                'name' => 'Acme Corp',
                'slug' => 'acme',
                'locale' => 'en',
                'timezone' => 'UTC',
                'currency' => 'USD',
            ]);
        }

        // Make sure the owner has the Owner role on the team even if the
        // tenant was created in a prior run with stale role assignments.
        setPermissionsTeamId($acme->id);
        if (! $owner->hasRole('Owner')) {
            $owner->assignRole('Owner');
        }

        // 3) Admin + Member memberships via invite() so the canonical seam is
        //    exercised (events fire, roles get assigned with team scope).
        foreach (array_slice(self::TENANT_USERS, 1) as $spec) {
            $user = $this->makeUser($spec['email'], $spec['name']);

            $isMember = $acme->members()->whereKey($user->id)->exists();
            if (! $isMember) {
                $tenants->invite($acme, $owner, $spec['email'], $spec['role'], autoAttach: true);
            } else {
                // Already a member — just make sure the role is correct.
                setPermissionsTeamId($acme->id);
                if (! $user->hasRole($spec['role'])) {
                    $user->syncRoles([$spec['role']]);
                }
            }
        }

        // 4) Plain authenticated user with no role.
        $this->makeUser('user@example.test', 'Penny Plain');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $this->command?->info('UserSeeder: 5 test users ready (password: password) — super@, owner@acme, admin@acme, member@acme, user@example.test.');
    }

    private function makeUser(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
