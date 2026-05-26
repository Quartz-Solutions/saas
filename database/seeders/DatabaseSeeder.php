<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Default seed run by `php artisan db:seed` — keep this lean. Only data that
 * every install needs lives here. Test users live in UserSeeder, demo data
 * lives in DemoSeeder; both are invoked explicitly:
 *
 *   php artisan db:seed --class=UserSeeder
 *   php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CurrencySeeder::class);
        $this->call(RolesSeeder::class);
        $this->call(DemoSeeder::class);
    }
}
