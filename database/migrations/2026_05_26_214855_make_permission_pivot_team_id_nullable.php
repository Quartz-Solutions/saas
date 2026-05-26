<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie's default migration creates `model_has_roles.team_id` (and the
 * permissions pivot equivalent) as NOT NULL when teams are enabled. That
 * breaks any global role — including Phase 4's "Super Admin" — because
 * Spatie writes `team_id = NULL` when the current team is unset.
 *
 * We can't edit the historical migration (CLAUDE.md), so this migration
 * patches the pivot tables in place: drop the existing primary key, flip
 * the column to nullable, then recreate the primary key (Postgres treats
 * multiple NULLs as distinct so the constraint still functions for
 * tenant-scoped rows).
 *
 * Sqlite (used by the test suite) doesn't enforce ->change(), so we use a
 * raw rebuild for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        if (! config('permission.teams') || empty($columnNames['team_foreign_key'] ?? null)) {
            return;
        }

        $teamColumn = $columnNames['team_foreign_key'];
        $roleKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $permKey = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';
        $driver = DB::connection()->getDriverName();

        $patches = [
            ['model_has_roles', $roleKey],
            ['model_has_permissions', $permKey],
        ];

        foreach ($patches as [$tableKey, $pivotKey]) {
            $table = $tableNames[$tableKey] ?? null;
            if ($table === null || ! Schema::hasTable($table)) {
                continue;
            }

            if ($driver === 'pgsql') {
                $pk = DB::selectOne(
                    "SELECT conname FROM pg_constraint WHERE conrelid = ?::regclass AND contype = 'p'",
                    [$table]
                );
                $primaryName = $pk?->conname ?? "{$table}_pkey";

                DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$primaryName}\"");
                DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN \"{$teamColumn}\" DROP NOT NULL");
                DB::statement(
                    "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$primaryName}\" "
                    ."PRIMARY KEY (\"{$pivotKey}\", \"{$modelKey}\", \"model_type\")"
                );
            } else {
                // sqlite (tests) — sqlite refuses to drop a PK column, so rebuild
                // the table without it then re-create with nullable team_id.
                $isRoleTable = $tableKey === 'model_has_roles';
                Schema::drop($table);
                Schema::create($table, function ($blueprint) use ($teamColumn, $pivotKey, $modelKey, $isRoleTable, $tableNames) {
                    $blueprint->unsignedBigInteger($pivotKey);
                    $blueprint->string('model_type');
                    $blueprint->unsignedBigInteger($modelKey);
                    $blueprint->unsignedBigInteger($teamColumn)->nullable();
                    $blueprint->index([$modelKey, 'model_type']);
                    $blueprint->index($teamColumn);
                    $blueprint->foreign($pivotKey)
                        ->references('id')
                        ->on($isRoleTable ? ($tableNames['roles'] ?? 'roles') : ($tableNames['permissions'] ?? 'permissions'))
                        ->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // No-op — re-tightening the column would orphan any global roles.
    }
};
