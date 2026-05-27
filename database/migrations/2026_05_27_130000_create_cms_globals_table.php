<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide CMS singletons: brand, header_menu, footer_menu, announcement,
 * analytics, cookie_banner, contact, social, seo_defaults.
 *
 * One row per `key`. Payload shape is enforced at the application layer
 * by GlobalsService against config('cms.globals.{key}').
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::create('cms_globals', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label');
            $table->string('locale', 8)->default('en');
            if ($driver === 'pgsql') {
                $table->jsonb('payload')->default('{}');
            } else {
                $table->json('payload')->nullable();
            }
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_globals');
    }
};
