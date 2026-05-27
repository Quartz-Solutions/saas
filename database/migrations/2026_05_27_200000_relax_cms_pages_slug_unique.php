<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the global unique on cms_pages.slug and replaces it with a
 * composite (slug, locale) unique. Multi-locale pages need the same
 * slug across locale variants ("about" exists in EN + AR + FR).
 *
 * `path` already has unique(path, locale) — this brings slug in line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropUnique('cms_pages_slug_unique');
        });

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            Schema::table('cms_pages', function (Blueprint $table) {
                $table->unique(['slug', 'locale'], 'cms_pages_slug_locale_unique');
            });
        } else {
            // sqlite in tests: composite unique via raw to avoid driver quirks.
            DB::statement('CREATE UNIQUE INDEX cms_pages_slug_locale_unique ON cms_pages (slug, locale)');
        }
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropUnique('cms_pages_slug_locale_unique');
            $table->unique('slug', 'cms_pages_slug_unique');
        });
    }
};
