<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * App settings are admin-configurable through /admin/settings. Defaults come
 * from .env (read by config/*.php) and are surfaced into the UI by
 * AppSettingsServiceProvider. This seeder is a placeholder hook in
 * DatabaseSeeder — fresh installs leave the `app_settings` table empty and
 * rely on env defaults until an admin saves an override.
 *
 * Add explicit rows here only if a fresh install must ship with a non-env
 * default for a specific key.
 */
class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally empty.
    }
}
