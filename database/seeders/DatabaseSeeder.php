<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CurrencySeeder::class);

        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        if (! User::where('email', 'admin@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $fakes = max(0, 15 - User::whereNotIn('email', ['test@example.com', 'admin@example.com'])->count());
        if ($fakes > 0) {
            User::factory()->count(max(1, $fakes - 3))->create();
            User::factory()->count(min(3, $fakes))->unverified()->create();
        }

        $this->call(RolesSeeder::class);
    }
}
